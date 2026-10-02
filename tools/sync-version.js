/**
 * 主题版本「单一真源」同步工具。
 *
 * 真源：package.json 的 version（npm version 会连带对齐 package-lock）。
 * 本脚本把它同步到所有会展示或校验版本的文件：
 *   - style.css 头部 `Version:`  —— wp_get_theme()->get('Version') 读的就是它，主题规范强制静态字段
 *   - readme.txt `Stable tag:`   —— WP.org 要求与 style.css 完全一致
 *   - readme.txt `== Changelog ==` 顶部的 `= x.y.z =` 条目（缺则补空条目，高于目标则报错）
 *   - tools/readme-wporg.txt 的 `Stable tag:` 与 changelog —— w.org 发行变体的 readme 模板，
 *     由 gulp copyWporg 复制成包内 readme.txt。不在主 readme 同步范围内会长期漂移
 *     （曾停在 1.2.3 而主仓库已 1.2.7，提交包 Stable tag 与 style.css 对不上）
 *   - package-lock.json 顶层 version（兜住手工改 package.json 的情况）
 *
 * 用法：
 *   node tools/sync-version.js           同步并打印变更
 *   node tools/sync-version.js --check   只校验不写盘，不一致 exit 1（CI / 构建前置用）
 *
 * 注：不要改用运行时注入版本号。style.css 的 `Version:` 必须是仓库内的静态文本，
 * PHP 运行时改写主题头信息违反 WordPress 主题规范。
 */

'use strict';

const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const FILES = {
    style: path.join(ROOT, 'style.css'),
    readme: path.join(ROOT, 'readme.txt'),
    // w.org 发行变体的 readme 模板：copyWporg 会把它复制成包内 readme.txt。
    // 它不在主 readme 的同步范围内，所以版本号天然会漂移——曾长期停在 1.2.3 而主仓库
    // 已到 1.2.7，提交包里的 Stable tag 与 style.css 对不上（w.org 会要求重新上传）。
    // 故纳入同一套同步 + 校验。
    readmeWporg: path.join(ROOT, 'tools', 'readme-wporg.txt'),
    pkg: path.join(ROOT, 'package.json'),
    lock: path.join(ROOT, 'package-lock.json'),
};

const CHECK_ONLY = process.argv.includes('--check');
const only = (p) => (CHECK_ONLY ? ' --check' : '') + ' ' + path.relative(ROOT, p);

/* ---------------------------------------------------------------- 底层工具 */

/** 读文件，剥掉 BOM（保留信息以便原样写回）。 */
function readText(file) {
    const buf = fs.readFileSync(file);
    const hasBom = buf[0] === 0xef && buf[1] === 0xbb && buf[2] === 0xbf;
    return { text: buf.toString('utf8').replace(/^﻿/, ''), hasBom };
}

function writeText(file, text, hasBom) {
    fs.writeFileSync(file, (hasBom ? '﻿' : '') + text, 'utf8');
}

/**
 * 精确替换「以 prefix 开头」的整行，保留原换行符（LF / CRLF 都行）。
 * prefix 需为正则安全字面量。
 */
function replaceLine(text, prefix, value) {
    const re = new RegExp('(' + prefix + ')[^\\r\\n]*(?=\\r?\\n)');
    if (!re.test(text)) {
        throw new Error('line starting with "' + prefix.trim() + '" not found, file structure changed');
    }
    return text.replace(re, '$1' + value);
}

/** 语义化版本比较：返回正数 = a 更新。 */
function cmpVer(a, b) {
    const pa = String(a).split(/[.-]/);
    const pb = String(b).split(/[.-]/);
    for (let i = 0; i < 3; i++) {
        const d = (parseInt(pa[i], 10) || 0) - (parseInt(pb[i], 10) || 0);
        if (d !== 0) return d;
    }
    return 0;
}

/* ------------------------------------------------------------------ 同步 */

const changes = [];
const problems = [];

/** 1. style.css 头部 Version */
function syncStyle(version) {
    const f = FILES.style;
    const { text, hasBom } = readText(f);
    const m = text.match(/^Version:[^\S\r\n]*(\S+)/m);
    const current = m ? m[1] : null;
    if (current === version) return;
    if (CHECK_ONLY) {
        problems.push('style.css Version is ' + current + ', expected ' + version);
        return;
    }
    writeText(f, replaceLine(text, 'Version: *', version), hasBom);
    changes.push('style.css: Version ' + current + ' -> ' + version);
}

/** 2. readme.txt Stable tag（主 readme + w.org 变体 readme 模板，两者必须一致） */
function syncStableTag(version) {
    for (const [key, label] of [[FILES.readme, 'readme.txt'], [FILES.readmeWporg, 'tools/readme-wporg.txt']]) {
        if (!fs.existsSync(key)) {
            problems.push(label + ' 不存在，无法同步 Stable tag');
            continue;
        }
        const { text, hasBom } = readText(key);
        const m = text.match(/^Stable tag:[^\S\r\n]*(\S+)/m);
        const current = m ? m[1] : null;
        if (current === version) continue;
        if (CHECK_ONLY) {
            problems.push(label + ' Stable tag is ' + current + ', expected ' + version);
            continue;
        }
        writeText(key, replaceLine(text, 'Stable tag: *', version), hasBom);
        changes.push(label + ': Stable tag ' + current + ' -> ' + version);
    }
}

/**
 * 3. readme.txt changelog 顶部条目（主 readme + w.org 变体 readme 模板）。
 * 内容仍需人工补；这里只负责标题就位，避免发版时又多改一次文件。
 */
function syncChangelog(version) {
    for (const [key, label] of [[FILES.readme, 'readme.txt'], [FILES.readmeWporg, 'tools/readme-wporg.txt']]) {
        if (!fs.existsSync(key)) continue; // 文件缺失已在 syncStableTag 报过
        const { text, hasBom } = readText(key);
        const marker = text.indexOf('== Changelog ==');
        if (marker === -1) {
            problems.push(label + ' has no "== Changelog ==" section');
            continue;
        }
        const head = text.slice(0, marker);
        const body = text.slice(marker);
        const m = body.match(/^= ([^\r\n]+) =/m);
        const current = m ? m[1].trim() : null;

        if (current === version) continue;

        if (CHECK_ONLY) {
            problems.push(label + ' changelog top entry is ' + current + ', expected ' + version);
            continue;
        }
        if (current && cmpVer(current, version) > 0) {
            // 目标版本比 changelog 顶部还旧：不猜用户意图，直接停
            problems.push(
                label + ' changelog top entry ' + current + ' is newer than target ' + version + ', refuse to downgrade'
            );
            continue;
        }
        // 插到首个 "= x.y.z =" 条目之上；changelog 还是空的时候紧跟标题行
        // 只吃掉条目行前的那个换行，再补 block，避免多出空行
        const block = '= ' + version + ' =\n\n';
        let next = body.replace(/\r?\n(= [^\r\n]+ =)/, '\n' + block + '$1');
        if (next === body) {
            next = body.replace(/(\r?\n)/, '\n' + block + '$1');
        }
        writeText(key, head + next, hasBom);
        changes.push(label + ': changelog top entry ' + current + ' -> ' + version + ' (body TODO)');
    }
}

/** 4. package-lock.json 顶层 version（两处：根、packages[""]） */
function syncLock(version) {
    const f = FILES.lock;
    if (!fs.existsSync(f)) return;
    const { text, hasBom } = readText(f);
    let json;
    try {
        json = JSON.parse(text);
    } catch (e) {
        problems.push('package-lock.json parse error: ' + e.message);
        return;
    }
    const root = json.version;
    const rootPkg = json.packages ? json.packages[''] : null;
    const empty = rootPkg ? rootPkg.version : null;
    if (root === version && empty === version) return;

    if (CHECK_ONLY) {
        problems.push('package-lock.json version is ' + root + ' (packages[""] ' + empty + '), expected ' + version);
        return;
    }
    json.version = version;
    if (rootPkg) rootPkg.version = version;
    writeText(f, JSON.stringify(json, null, 2) + '\n', hasBom);
    changes.push('package-lock.json: version ' + root + ' -> ' + version);
}

/* ------------------------------------------------------------------ 入口 */

function main() {
    const pkg = JSON.parse(fs.readFileSync(FILES.pkg, 'utf8'));
    const version = pkg.version;
    if (!/^\d+\.\d+\.\d+(?:[-+][\w.-]+)?$/.test(version)) {
        problems.push('package.json version is not semver: ' + version);
        return 1;
    }

    syncStyle(version);
    syncStableTag(version);
    syncChangelog(version);
    syncLock(version);

    if (CHECK_ONLY) {
        if (problems.length) {
            console.log('[version-check] FAIL expected ' + version + ':');
            problems.forEach((p) => console.log('  - ' + p));
            console.log('[version-check] fix: node tools/sync-version.js');
            return 1;
        }
        console.log('[version-check] OK all in sync (' + version + ')');
        return 0;
    }

    if (changes.length) {
        console.log('[version-sync] ' + version + ', ' + changes.length + ' file(s) updated:');
        changes.forEach((c) => console.log('  * ' + c));
    } else {
        console.log('[version-sync] OK nothing to change (' + version + ')');
    }
    if (problems.length) {
        console.log('[version-sync] notes:');
        problems.forEach((p) => console.log('  - ' + p));
    }
    return 0;
}

process.exit(main());
