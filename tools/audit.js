#!/usr/bin/env node
/**
 * 审计门禁：扫描结果 = raw(全量) − baseline(已判定)，**只报新增**。
 *
 * 背景（本项目「每次扫描都扫出新问题」的根因，别改坏）：
 *  - phpcs 的排除项是由上一轮结果长出来的（Exception-Driven）：扫出一批 → 加一条
 *    <exclude> → 规则集变了 → 结果集跟着变 → 又一批，永远收敛不了；
 *  - 平时只跑一把尺子（phpcs / precheck / 线上探针），看到的都是不同集合，
 *    于是每次都像「又冒出来一批新问题」。
 * 本脚本把尺度钉死：用不含任何 exclude 的 phpcs-all.xml.dist 取全集
 * （取全集由 tools/refresh-raw.sh 在服务器跑），再减掉
 * tools/audit-baseline.json 里已判定的条目，**剩下的才是真新增**。
 *
 * 用法（纯本地文件读写，不 spawn 任何子进程）：
 *   node tools/audit.js           对最近的 raw 快照跑减法（秒级）
 *   node tools/audit.js --all     对全部快照跑，输出汇总
 *   node tools/audit.js --list    列出所有快照及其规模
 *   node tools/audit.js --check   校验 phpcs.xml.dist 的 exclude 与 baseline.whole 一一对应
 *   node tools/audit.js --stats   只出统计
 *
 * 刷新快照（需要服务器，走 shell 脚本，本脚本不碰网络）：
 *   bash tools/refresh-raw.sh
 *
 * 退出码：0=无新增；1=有新增（门禁拦）；2=用法/环境错误。
 */
'use strict';

const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const AUDIT_DIR = path.join(ROOT, '.workbuddy', 'audit');
// 基线必须随代码入库（.workbuddy/ 整目录被 gitignore），换机器/换仓库才不会断链。
const BASELINE = path.join(__dirname, 'audit-baseline.json');

/* ─────────────────────────── 工具 ─────────────────────────── */

/** 引号感知的 CSV 行切分（phpcs 的 Message 里可能有逗号与转义双引号）。 */
function splitCsvLine(line) {
	const out = [];
	let cur = '';
	let inQuotes = false;
	for (let i = 0; i < line.length; i++) {
		const c = line[i];
		if (inQuotes) {
			if (c === '"') {
				if (line[i + 1] === '"') { cur += '"'; i++; } else { inQuotes = false; }
			} else { cur += c; }
		} else if (c === '"') {
			inQuotes = true;
		} else if (c === ',') {
			out.push(cur); cur = '';
		} else { cur += c; }
	}
	out.push(cur);
	return out;
}

/** 读 raw CSV → [{file,line,type,message,source}]，自动跳过 phpcs 写死的表头。 */
function readRawCsv(file) {
	const text = fs.readFileSync(file, 'utf8').replace(/^\uFEFF/, '');
	const rows = [];
	for (const line of text.split(/\r?\n/)) {
		if (line.trim() === '') { continue; }
		const f = splitCsvLine(line);
		// phpcs --report=csv 固定 8 列；表头首列是 "File"，靠列数过滤掉
		if (f.length < 8 || f[0] === 'File') { continue; }
		rows.push({ file: f[0], line: f[1], type: f[3], message: f[4], source: f[5] });
	}
	return rows;
}

function listRawFiles() {
	if (!fs.existsSync(AUDIT_DIR)) { return []; }
	return fs.readdirSync(AUDIT_DIR)
		.filter((n) => /^raw-.*\.csv$/.test(n))
		.sort()
		.map((n) => path.join(AUDIT_DIR, n));
}

function loadBaseline() {
	const raw = JSON.parse(fs.readFileSync(BASELINE, 'utf8'));
	if (!raw.whole) { raw.whole = {}; }
	if (!raw.scoped) { raw.scoped = {}; }
	return raw;
}

/* ─────────────────────────── 减法 ─────────────────────────── */

/**
 * 判定一条 raw issue：
 *  - whole：source 以某条 whole 前缀开头 → 整类豁免（官方标准反对 / 纯文档 / 刻意架构）
 *  - scoped：文件 + 完整 source + 消息命中 → 逐条豁免（带理由，锁死具体文件与语句）
 *  - 其余 → 新增（真问题，必须修）
 */
function classify(row, base) {
	for (const [prefix, shape] of Object.entries(base.whole)) {
		if (!(row.source === prefix || row.source.startsWith(prefix + '.'))) { continue; }
		// 族级豁免可带「合法落点」白名单：命中文件不在其中 → 判为新增。
		// 堵住新功能/新文件蹭整类豁免被吞（安全类尤其致命）。
		const allowed = shape && shape.files;
		if (Array.isArray(allowed) && !allowed.some((d) => row.file.startsWith(d))) {
			return { state: 'new', reason: `${row.source} 落在整类豁免「${prefix}」范围内，但该 sniff 的合法落点仅 ${allowed.join(' / ')}，${row.file} 不在其中 —— 须就地 phpcs:ignore 或走集中式 guard` };
		}
		return { state: 'whole' };
	}
	const fileScope = base.scoped[row.file];
	if (fileScope && fileScope[row.source]) {
		const msgScope = fileScope[row.source];
		if (msgScope.msgs.some((m) => m === row.message)) {
			return { state: 'scoped' };
		}
		return { state: 'unscoped', reason: `${row.source} 在 ${row.file} 有豁免，但该条消息不在清单里` };
	}
	return { state: 'new', reason: '' };
}

function diff(rawRows, base) {
	const added = [];
	const wholeHits = new Map();
	const scopedHits = new Map();
	const scopedSeen = new Set();
	let wholeCount = 0;
	let scopedCount = 0;
	for (const row of rawRows) {
		const r = classify(row, base);
		if (r.state === 'new' || r.state === 'unscoped') { added.push({ ...row, why: r.reason }); continue; }
		if (r.state === 'whole') {
			wholeCount++;
			wholeHits.set(row.source, (wholeHits.get(row.source) || 0) + 1);
		} else {
			scopedCount++;
			const key = row.file + ' :: ' + row.source;
			scopedHits.set(key, (scopedHits.get(key) || 0) + 1);
			scopedSeen.add(row.file + '\u0000' + row.source + '\u0000' + row.message);
		}
	}
	// 基线上已消失的 scoped 条目 → 提示可清理（不是错误）
	const stale = [];
	for (const file of Object.keys(base.scoped)) {
		for (const source of Object.keys(base.scoped[file])) {
			for (const msg of base.scoped[file][source].msgs) {
				if (!scopedSeen.has(file + '\u0000' + source + '\u0000' + msg)) {
					stale.push({ file, source, msg });
				}
			}
		}
	}
	return { added, wholeHits, scopedHits, stale, total: rawRows.length, wholeCount, scopedCount };
}

function report(rel, d, { showDetail = true, statsOnly = false } = {}) {
	console.log(`\n[audit] ${rel}：raw 全量 ${d.total} 条`);
	console.log(`        整类豁免 ${d.wholeCount} 条（${d.wholeHits.size} 个 sniff）| 逐条豁免 ${d.scopedCount} 条`);
	if (d.stale.length) {
		console.log(`  [idle] 基线上已不存在 ${d.stale.length} 条（可清理）：`);
		d.stale.slice(0, 5).forEach((s) => console.log(`         ${s.file} :: ${s.source}`));
		if (d.stale.length > 5) { console.log(`         …还有 ${d.stale.length - 5} 条`); }
	}
	if (d.added.length) {
		console.log(`  ❌ 新增 ${d.added.length} 条：`);
		if (showDetail && !statsOnly) {
			d.added.slice(0, 40).forEach((r) => {
				console.log(`         ${r.file}:${r.line}  [${r.source}] ${r.message}`);
			});
			if (d.added.length > 40) { console.log(`         …还有 ${d.added.length - 40} 条`); }
		}
		return 1;
	}
	if (!statsOnly) {
		console.log('  ✅ 无新增：raw − baseline = 0，当前版本静态问题已清空。');
	}
	return 0;
}

/* ─────────────────────────── 规则集一致性 ─────────────────────────── */

function checkRuleset() {
	const xml = fs.readFileSync(path.join(ROOT, 'phpcs.xml.dist'), 'utf8');
	const excludes = new Set();
	for (const m of xml.matchAll(/<exclude\s+name="([^"]+)"\s*\/>/g)) { excludes.add(m[1]); }
	const base = loadBaseline();
	// 前缀互相覆盖即可（Squiz.Commenting 覆盖 Squiz.Commenting.InlineComment.InvalidEndChar 这类子项）
	const covers = (a, b) => a === b || a.startsWith(b + '.') || b.startsWith(a + '.');
	const problems = [];
	for (const prefix of Object.keys(base.whole)) {
		if (![...excludes].some((name) => covers(prefix, name))) {
			problems.push(`baseline.whole 含「${prefix}」，phpcs.xml.dist 没有对应 exclude → 两把尺子口径不一致`);
		}
	}
	for (const name of excludes) {
		if (!Object.keys(base.whole).some((p) => covers(p, name))) {
			problems.push(`phpcs.xml.dist 排除了「${name}」，但 baseline.whole 里没有对应条目 → 两把尺子口径不一致`);
		}
	}
	if (!problems.length) { console.log('✅ 规则集一致：phpcs.xml.dist 的 exclude 与 baseline.whole 一一对应。'); return 0; }
	console.log('⚠️ 规则集不一致：');
	problems.forEach((p) => console.log('   - ' + p));
	return 1;
}

/* ─────────────────────────── 主流程 ─────────────────────────── */

function main() {
	const argv = process.argv.slice(2);
	const flag = (f) => argv.includes(f);
	const base = loadBaseline();

	if (flag('--check')) { return checkRuleset(); }

	if (flag('--list')) {
		const files = listRawFiles();
		if (!files.length) { console.error('没有 raw 快照，先跑：bash tools/refresh-raw.sh'); return 2; }
		for (const f of files) {
			console.log(`${path.relative(ROOT, f)}  ${readRawCsv(f).length} 条`);
		}
		return 0;
	}

	const files = listRawFiles();
	if (!files.length) { console.error('没有 raw 快照，先跑：bash tools/refresh-raw.sh'); return 2; }
	if (flag('--all')) {
		let code = 0;
		for (const f of files) { code = report(path.relative(ROOT, f), diff(readRawCsv(f), base), { statsOnly: flag('--stats') }) || code; }
		return code;
	}

	const cur = files[files.length - 1];
	return report(path.relative(ROOT, cur), diff(readRawCsv(cur), base), { statsOnly: flag('--stats') });
}

process.exit(main());
