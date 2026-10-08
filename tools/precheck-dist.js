#!/usr/bin/env node
/**
 * wp.org 上传包「打包前合规闸门」（pre-check gate）。
 *
 * 用法（两种输入，别混）：
 *   1) 目录扫描（产物目录 / 严格）：node tools/precheck-dist.js <dir> [--slug=<slug>] [--mode=theme|plugin]
 *      直接对磁盘目录全量扫，顺带查顶层结构。适用于「zip 的 base 就是该目录」的产物目录。
 *   2) 清单扫描（打包源目录 / 推荐）：
 *      node tools/precheck-dist.js . --slug=jinyu --flat --list=- < files.txt
 *      只检查「实际会被打进包的文件」，源目录里那些已被 glob 排除的东西不再误伤闸门。
 *
 *   退出码 0=放行；1=有阻断项（打包必须中止，不得产出 zip）；2=用法/目录错误。
 *   --flat：源目录模式，跳过「顶层单一目录」检查（zip 会把整体 rename 成单一目录）。
 *
 * 背景（别删这些案例，都是真踩过的）：
 * - phpcs.xml.dist 曾混进 wporg 包，白带 1.18MB 开发配置；
 * - 插件 readme 的 Tested up to 写成 7.1.2，wp.org 判 invalid_tested_upto_minor 直接拒包；
 * - 本地凭证 / 签名密钥对（wp-creds.json、jinyu-update.key|pub）与 wpcss-*.csv 一类
 *   调试扫描产物一度散在仓库里。
 * glob 排除清单是「人写的」，会漏；本脚本对内容做全量扫描，漏不漏只取决于规则本身。
 *
 * 附：解耦契约（项目铁律「主题 = 纯呈现层」）。主题对配套插件（jinyu-theme-companion）
 * 的所有能力需求，必须走 apply_filters 广播，不得直调插件私有符号。本脚本守两条：
 *   ① 主题源码不得出现插件私有符号（jinyu_companion_*() / jyc_* / jinyu_sl_* / jinyu_oauth_*()）；
 *   ② 五个关键能力点必须保留对应的 apply_filters 广播，缺一即阻断。
 * 规则只针对 .php，且先剔除注释；符号名必须紧邻左括号才算「直调」，
 * 因此 ajax action / user meta 一类的**字符串**用法不会误伤。
 */
'use strict';

const fs = require('fs');
const path = require('path');

const args = process.argv.slice(2);
const dir = args.find((a) => !a.startsWith('--'));
if (!dir) {
	console.error('用法：node tools/precheck-dist.js <dir> [--slug=<slug>] [--mode=theme|plugin] [--flat] [--list=-|<file>]');
	process.exit(2);
}
const opt = (k, d) => {
	const hit = args.find((a) => a.startsWith('--' + k + '='));
	return hit ? hit.slice(k.length + 3) : d;
};
const SLUG = opt('slug', 'jinyu');
const MODE = opt('mode', 'theme');
const FLAT = args.includes('--flat');

const ROOT = path.resolve(dir);
if (!fs.existsSync(ROOT) || !fs.statSync(ROOT).isDirectory()) {
	console.error('目录不存在：' + ROOT);
	process.exit(2);
}

/* ───────────────────────── 规则表 ───────────────────────── */

// 不进入扫描的目录（依赖产物 / 版本库 / 本地工作区，本就不进包）
const SKIP_DIRS = new Set([
	'node_modules', '.git', '.svn', '.hg', '.workbuddy', '.trae',
	'.idea', '.vscode', '__pycache__', 'vendor', '.cache',
]);

// A. 私密信息：文件名
const NAME_LEAK = [
	[/(^|\/)(wp-)?creds?[^/]*$/i, '站点凭证文件'],
	[/(^|\/)[^/]*\.env$/i, '环境变量文件'],
	[/(^|\/)[^/]*\.pem$/i, 'PEM 证书/私钥'],
	[/(^|\/)[^/]*\.p12$/i, '证书包'],
	[/(^|\/)[^/]*\.(key|pub)$/i, '签名密钥对（jinyu-update.key/.pub 绝不能进包）'],
	[/(^|\/)[^/]*\.sql$/i, '数据库导出'],
	[/(^|\/)[^/]*[-_.](secret|token|passwd|password)[^/]*$/i, '疑似凭证文件'],
	[/(^|\/)id_[^/]*\.pem$/i, 'SSH 私钥'],
	[/(^|\/)\.ht(access|passwd)$/i, 'Apache 访问控制'],
];

// B. 私密信息：内容
// 注：正则一律由字符拼接构造 —— 本文件里的规则字面量不能自我命中。
const D = String.fromCharCode(45); // -
const CONTENT_LEAK = [
	[new RegExp('^' + D.repeat(5) + 'BEGIN [A-Z ]*PRIVATE KEY' + D.repeat(5)), 'PEM 私钥'],
	[new RegExp('^' + D.repeat(5) + 'BEGIN PGP PRIVATE KEY' + D.repeat(5) + ' BLOCK' + D.repeat(5)), 'PGP 私钥'],
	[/\bAKIA[0-9A-Z]{16}\b/, 'AWS Access Key ID'],
	[/\bgh[pousr]_[A-Za-z0-9]{30,}\b/, 'GitHub Token'],
	[/\bgithub_pat_[A-Za-z0-9_]{60,}\b/, 'GitHub Fine-grained PAT'],
	[/\bxox[baprs]-[A-Za-z0-9-]{10,}\b/, 'Slack Token'],
	[/\bAIza[0-9A-Za-z_\-]{35}\b/, 'Google API Key'],
	[/\bsk-[A-Za-z0-9]{32,}\b/, 'OpenAI/Anthropic API Key'],
	[/["']?password["']?\s*[:=]\s*["'][^"']{4,}["']/i, '明文密码赋值'],
	[/\bDB_PASSWORD[,)\s]*['"][^'"]{4,}['"]/, 'wp-config 数据库密码'],
];

// C. 测试 / 调试文件：文件名
const NAME_DEBUG = [
	[/\.log$/i, '日志'],
	[/\.tmp$/i, '临时文件'],
	[/\.(bak|orig|save|swp|swo)$/i, '编辑器备份'],
	[/^(nul|con|prn|aux|clock\$)$/i, 'Windows 保留设备名残留（shell 误重定向产物）'],
	[/(_|\b)(probe|debug|scratch|wip)[._-]/i, '调试脚本'],
	// 一次性探针的 echo 输出快照：jy_diag_out.html / jy_ft_out.txt 之类。
	// 名字既不含 probe|debug 也不含 .log，历史上真的差点进包，
	// 且内容常带线上绝对路径、水印配置等运行态信息。
	[/(^|\/)jy_[a-z0-9_]*_(out|trace|result)\.(html?|txt|log|json)$/i, '一次性调试输出快照'],
	[/(^|\/)(phpunit|jest|karma|cypress|playwright)\.xml$/i, '测试运行器配置'],
	[/^_[A-Za-z0-9][A-Za-z0-9._-]*\.(csv|txt|log|json|tmp)$/i, '下划线开头的临时扫描产物'],
	[/(^|\/)(tests?|__tests__|spec|fixtures?)\//i, '测试目录'],
	[/\.(test|spec)\.(js|mjs|cjs|php|py)$/i, '测试文件'],
];

// D. 开发配置 / 构建产物：wp.org 不收，且 phpcs.xml.dist 曾真实误入。
// 正则覆盖带后缀的规则集（phpcs.xml.dist / phpcs-all.xml.dist 等），曾因只匹配首份漏掉 -all 变体。
// ⚠️ 关键：必须允许「目录前缀」（/(^|[\\/])…/），因为变体包文件都落在 jinyu-lite/ 子目录下，
//    写成 ^phpcs…$ 会被前缀挡死、永远匹配不到，等于没查。NAME_LEAK/NAME_DEBUG 已是此约定。
const NAME_DEVCONF = [
	[/(^|[\\/])phpcs(-[a-z0-9]+)?(\.xml)?(\.dist)?$/i, 'PHPCS 开发配置'],
	[/(^|[\\/])(\.babelrc|\.editorconfig|\.eslintrc.*|\.prettierrc.*|codekit-config\.json)$/i, '构建/编辑器配置'],
	[/(^|[\\/])(gulpfile|webpack\.config|rollup\.config|vite\.config)\.(js|cjs|mjs|ts)$/i, '构建脚本'],
	[/\.map$/, 'source map（调试产物）'],
];

// E. 路径合规
const NAME_ASCII = [[/[^\x00-\x7F]/, '非 ASCII 名称（wp.org 上传包路径一律 ASCII）']];

/* ─────────────── F. 解耦契约（项目铁律「主题 = 纯呈现层」） ───────────────
 * ① 主题源码不得直调配套插件（jinyu-theme-companion）私有符号；
 * ② 五个关键能力点必须保留 apply_filters 广播，插件才能接管。
 * 规则用字符串拼接构造，避免本文件里的规则字面量自我命中；
 * 且要求符号紧邻左括号才算「直调」，ajax action / user meta 一类的**字符串**用法不误伤。
 */
const LP = String.fromCharCode(40); // (
const DECOUPLE_CALL = [
	['配套插件函数 jinyu_companion_*()', 'jinyu_companion_' + '[A-Za-z0-9_]+'],
	['插件私有前缀 jyc_*', 'jyc_' + '[A-Za-z0-9_]+'],
	['插件短链前缀 jinyu_sl_*', 'jinyu_sl_' + '[A-Za-z0-9_]+'],
	['插件 OAuth 函数 jinyu_oauth_*()', 'jinyu_oauth_' + '[A-Za-z0-9_]*'],
].map(([why, body]) => [new RegExp('\\b' + body + '\\s*\\' + LP), why]);

// 插件必须能接管的五个广播点；删其一即解耦契约破损（改这条前先确认插件侧读取的是同名 filter）
const REQUIRED_FILTERS = [
	['functions.php', 'jinyu_perf_options'],
	['inc/fun/security.php', 'jinyu_client_ip'],
	['inc/fun/security.php', 'jinyu_rate_limit_check'],
	['inc/fun/crypto.php', 'jinyu_encrypt'],
	['inc/fun/crypto.php', 'jinyu_decrypt'],
];

// 剔除注释后再判符号，避免文档/注释里提到插件函数名就误报
const stripComments = (code) =>
	code.replace(/\/\*[\s\S]*?\*\//g, '').replace(/^[ \t]*(\/\/|#).*$/gm, '');

/* ───────────────────────── 报告容器 ───────────────────────── */

const blockers = [];
const warns = [];
const add = (list, rel, why) => list.push({ rel, why });
const checkName = (rel, rules) => {
	for (const [re, why] of rules) if (re.test(rel)) return why;
	return null;
};

/* ───────────────────────── 单文件检查 ───────────────────────── */

const CONTENT_MAX = 2 * 1024 * 1024;
const TEXT_EXT = new Set([
	'.php', '.js', '.mjs', '.cjs', '.css', '.scss', '.less', '.json', '.yml', '.yaml',
	'.xml', '.txt', '.md', '.rst', '.csv', '.tsv', '.html', '.htm', '.env', '.ini',
	'.conf', '.config', '.po', '.pot', '.svg', '.htaccess',
]);
// 示例配置 / 文档里的占位说明，命中内容规则不算泄漏
const CONTENT_EXEMPT = new Set(['readme.txt', 'readme.md', 'license.txt', 'license.md', 'LICENSE']);
const SELF = 'tools/precheck-dist.js'; // 本脚本含规则字面量，自豁免

function scanFile(rel, abs) {
	const why = checkName(rel, NAME_LEAK);
	if (why) return add(blockers, rel, '私密文件：' + why);
	// 注意：这几条不 return —— 文件名命中后仍要把内容扫完，
	// 否则「命中调试名 + 内含密钥」只会报出前者，掩盖真正的泄露来源。
	const whyD = checkName(rel, NAME_DEBUG);
	if (whyD) add(blockers, rel, '测试/调试文件：' + whyD);
	// 生产主题包（非 flat＝wporg 变体产物目录）严禁夹带开发配置：wp.org 自动扫描判 REQUIRED 直接拒包。
	// 由「警告」升级为「阻断」，且正则已覆盖 phpcs-all.xml.dist 这类带后缀规则集（见 NAME_DEVCONF）。
	if (!FLAT && checkName(rel, NAME_DEVCONF)) {
		add(blockers, rel, '开发配置/调试产物（wp.org REQUIRED：生产主题包不得包含）：' + checkName(rel, NAME_DEVCONF));
	}
	const whyA = checkName(rel, NAME_ASCII);
	if (whyA) return add(blockers, rel, whyA);

	if (rel === SELF || CONTENT_EXEMPT.has(path.basename(rel))) return;
	if (!TEXT_EXT.has(path.extname(rel).toLowerCase())) return;

	let st;
	try {
		st = fs.statSync(abs);
	} catch (e) {
		return;
	}
	if (st.size > CONTENT_MAX || st.size === 0) return;

	let buf;
	try {
		buf = fs.readFileSync(abs);
	} catch (e) {
		return;
	}
	if (buf.includes(0)) return; // 二进制，跳过

	const lines = buf.toString('utf8').split('\n');
	for (const [re, why] of CONTENT_LEAK) {
		for (let i = 0; i < lines.length; i++) {
			if (re.test(lines[i])) {
				add(blockers, rel, '疑似私密信息：' + why + '（第 ' + (i + 1) + ' 行）');
				break;
			}
		}
	}

	if (rel !== SELF && path.extname(rel).toLowerCase() === '.php') {
		const code = stripComments(buf.toString('utf8'));
		for (const [re, why] of DECOUPLE_CALL) {
			const m = code.match(re);
			if (m) {
				add(blockers, rel, '主题直调配套插件私有符号（违背「主题＝纯呈现层」铁律）：' + why + ' ← ' + m[0]);
			}
		}
	}
}

/* ───────────────────────── 收集待检文件 ───────────────────────── */

const listHit = args.find((a) => a.startsWith('--list='));
let listMode = false;
const entries = []; // { rel, abs }

if (listHit) {
	// 清单模式：只查真正会进包的文件（读自 stdin('--list=-') 或文件）
	listMode = true;
	const v = listHit.slice('--list='.length);
	const raw = v === '-' ? fs.readFileSync(0, 'utf8') : fs.readFileSync(path.resolve(v), 'utf8');
	for (const line of raw.split('\n')) {
		const rel = line.trim();
		if (!rel) continue;
		entries.push({ rel, abs: path.join(ROOT, rel) });
	}
} else {
	// 目录模式：递归遍历（跳过依赖与本地工作区）
	const stack = [{ cur: ROOT, base: '' }];
	while (stack.length) {
		const { cur, base: b } = stack.pop();
		let ents;
		try {
			ents = fs.readdirSync(cur, { withFileTypes: true });
		} catch (e) {
			add(warns, (b || '/') + '（无法读取）', e.message);
			continue;
		}
		for (const ent of ents) {
			const rel = b ? b + '/' + ent.name : ent.name;
			if (ent.isDirectory()) {
				if (SKIP_DIRS.has(ent.name)) continue;
				stack.push({ cur: path.join(cur, ent.name), base: rel });
			} else if (ent.isFile()) {
				entries.push({ rel, abs: path.join(cur, ent.name) });
			}
		}
	}
}

for (const e of entries) scanFile(e.rel, e.abs);

/* ───────────────────────── 结构合规 ───────────────────────── */

const names = entries.map((e) => e.rel);
const depth = (rel) => (rel.match(/\//g) || []).length;
// 头部文件允许出现在根，或「唯一的顶层目录」里（zip 的 base 边界不同，两种都算合法）
const hasHeader = (file) => names.some((n) => (n === file || n.endsWith('/' + file)) && depth(n) <= 1);
const relOfHeader = (file) => names.find((n) => (n === file || n.endsWith('/' + file)) && depth(n) <= 1);

if (!listMode && !FLAT) {
	// 产物目录：zip 的 base 常是 dist-wporg，打包内容落在它唯一的子目录里，要下钻
	const top0 = fs.readdirSync(ROOT, { withFileTypes: true }).filter((e) => e.isDirectory());
	if (top0.length !== 1) {
		add(blockers, '/', '顶层应恰好一个目录，实得 ' + top0.length + ' 个：' + top0.map((e) => e.name).join(', '));
	} else if (top0[0].name !== SLUG) {
		add(warns, '/', '顶层目录名为 ' + top0[0].name + '，与 --slug=' + SLUG + ' 不一致');
	}
}

if (MODE === 'theme' && !hasHeader('style.css')) add(blockers, '/', '打包内容里缺少 style.css（主题包必需）');
if (!hasHeader('readme.txt')) add(warns, '/', '打包内容里缺少 readme.txt（wp.org 必收）');

const readmeRel = hasHeader('readme.txt') ? relOfHeader('readme.txt') : null;
if (readmeRel) {
	const abs = path.join(ROOT, readmeRel);
	if (fs.existsSync(abs)) {
		const m = fs.readFileSync(abs, 'utf8').match(/^[ \t]*Tested up to:[ \t]*([^\r\n]+)/im);
		if (!m) {
			add(warns, readmeRel, '未找到 Tested up to 字段');
		} else {
			const v = m[1].trim();
			// 只认 主版本.次版本；带 patch 位 = invalid_tested_upto_minor，wp.org 直接拒包
			if (/^\d+\.\d+\.\d+$/.test(v)) add(blockers, readmeRel, 'Tested up to 带 patch 位（' + v + '），wp.org 判 invalid_tested_upto_minor');
			else if (!/^\d+\.\d+$/.test(v)) add(warns, readmeRel, 'Tested up to 格式异常：' + v + '（应为 X.Y）');
		}
	} else {
		add(warns, readmeRel, 'readme.txt 在清单里但磁盘上取不到，跳过 Tested up to 校验');
	}
}

/* ───────────────────────── 解耦契约：插件接管点必须仍在 ───────────────────────── */

for (const [rel, filter] of REQUIRED_FILTERS) {
	// 边界必须卡在「顶层目录名」上：只写 endsWith('/'+rel) 会把构建输出副本
	// （dist-wporg/jinyu-lite/inc/fun/crypto.php）当成源码命中，扫的是没改过的那份副本。
	const hit = entries.find((e) => {
		if (e.rel === rel) return true;
		return e.rel.startsWith(SLUG + '/') && e.rel.endsWith('/' + rel);
	});
	if (!hit) {
		// 文件不在「入包清单」里 → 广播无从校验。gate 口径＝打包口径，这里给警告而非阻断：
		// 该文件可能是文件名变了（此时上面的符号规则仍会照常拦），不是必然破损。
		add(warns, rel, '未进入待检清单，' + filter + ' 的 apply_filters 广播无从校验');
		continue;
	}
	if (!TEXT_EXT.has(path.extname(hit.rel).toLowerCase())) continue;
	let code;
	try {
		code = fs.readFileSync(hit.abs, 'utf8');
	} catch (e) {
		add(warns, hit.rel, '读不到文件，跳过 ' + filter + ' 广播校验');
		continue;
	}
	if (!new RegExp("apply_filters\\(\\s*['\"]" + filter + "['\"]").test(code)) {
		add(blockers, hit.rel, '缺少 apply_filters 广播 ' + filter + '（插件据此接管该能力，删掉即解耦契约破损）');
	}
}

/* ───────────────────────── 报告 ───────────────────────── */

const pad = (n) => (n < 10 ? ' ' + n : String(n));
const mode = listMode ? '清单模式' : FLAT ? '源目录模式' : '产物目录模式';
console.log('[precheck] ' + mode + '，共 ' + entries.length + ' 个文件：' + path.relative(process.cwd(), ROOT).split(path.sep).join('/') + '/');

if (warns.length) {
	console.log('\n⚠️  警告 ' + warns.length + ' 项（不阻断，必须人工确认）：');
	warns.forEach((w, i) => console.log('  ' + pad(i + 1) + '. ' + w.rel + '\n      → ' + w.why));
}

if (blockers.length) {
	console.error('\n❌ 阻断 ' + blockers.length + ' 项，禁止打包（修掉再跑）：');
	blockers.forEach((b, i) => console.error('  ' + pad(i + 1) + '. ' + b.rel + '\n      → ' + b.why));
	console.error('\n提示：  闸门只查「将进包的文件」。核对 glob 排除是否遗漏，看本清单条目数与实际 zip 内容是否一致。');
	process.exit(1);
}

console.log('\n✅ 通过：无私密文件、无测试/调试文件、结构合规。');
process.exit(0);
