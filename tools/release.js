/**
 * 发版一条龙：npm run release <1.2.3 | patch | minor | major>
 *
 * 1) npm version 递增 package.json + package-lock.json（--no-git-tag-version：tag 与提交分开，由你决定何时打）
 * 2) 跑 tools/sync-version.js，把版本同步到 style.css / readme.txt / changelog
 * 3) 构建 assets/dist
 *
 * 例：
 *   npm run release 1.2.3
 *   npm run release patch        # 1.2.2 -> 1.2.3
 *
 * 输出一律用 ASCII：本脚本常由 npm 在 cmd.exe 下调用（GBK 控制台），中文会变乱码。
 */

'use strict';

const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const ROOT = path.resolve(__dirname, '..');

/** Windows 上 node.exe 常装在 "C:\Program Files\..."（带空格），必须整体加引号。 */
function quote(cmd) {
    return /[\s"]/.test(cmd) ? '"' + cmd + '"' : cmd;
}

const NODE = quote(process.execPath);

function run(label, cmd, args) {
    console.log('\n[release] ' + label + ': ' + cmd + ' ' + args.join(' '));
    const r = spawnSync(cmd, args, { cwd: ROOT, stdio: 'inherit', shell: true });
    if (r.error) {
        console.error('[release] FAILED: ' + r.error.message);
        process.exit(1);
    }
    if (r.status === null) {
        console.error('[release] FAILED: ' + label + ' was interrupted');
        process.exit(1);
    }
    return r.status;
}

const target = process.argv[2];
if (!target) {
    console.error('usage: npm run release <1.2.3 | patch | minor | major>');
    process.exit(1);
}

// 已是目标版本时跳过 bump（npm version 会因 "Version not changed" 直接失败）
const current = JSON.parse(fs.readFileSync(path.join(ROOT, 'package.json'), 'utf8')).version;
let code = current === target
    ? 0
    : run('bump version', 'npm', ['version', target, '--no-git-tag-version', '--force']);
if (code !== 0) process.exit(code);

// 版本已变，先同步再构建，避免构建产物与主题版本对不上
code = run('sync version files', NODE, [path.join('tools', 'sync-version.js')]);
if (code !== 0) process.exit(code);

code = run('build dist', NODE, [path.join('node_modules', 'gulp', 'bin', 'gulp.js'), 'build']);
if (code !== 0) process.exit(code);

console.log('\n[release] done, next steps:');
console.log('  1) fill in the "' + target + '" section of readme.txt changelog');
console.log('  2) npm run zip');
