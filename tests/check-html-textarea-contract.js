/**
 * 回归测试：锁死「允许 HTML 的 textarea 字段」契约，防止 sanitize 再次误删 HTML。
 * 不依赖 PHP / WordPress，纯静态校验，可在任意有 node 的环境运行：
 *   node tests/check-html-textarea-contract.js
 * 失败退出码为 1，成功为 0。
 */
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const OPT_FOOTER = path.join(ROOT, 'inc/setting/options/Jinyu_OptionFooter.php');
const OPT_BASIC = path.join(ROOT, 'inc/setting/options/Jinyu_OptionBasic.php');
const SETTING = path.join(ROOT, 'inc/setting/Jinyu_Setting.php');

const errors = [];

// 契约字段 → 所在定义文件
const HTML_TEXTAREAS = [
  { id: 'footer_about', file: OPT_FOOTER },
  { id: 'footer_copyright', file: OPT_FOOTER },
  { id: 'single_copyright', file: OPT_BASIC },
];

// A) 每个 HTML textarea 字段必须声明 'html'=>true
for (const { id, file } of HTML_TEXTAREAS) {
  const src = fs.readFileSync(file, 'utf8');
  // 在字段定义附近（id 出现后 1200 字符内）查找 html=>true
  const idx = src.search(new RegExp(`'id'\\s*=>\\s*'${id}'`));
  if (idx === -1) {
    errors.push(`[A] 字段定义缺失: ${id} 未在 ${path.basename(file)} 找到`);
    continue;
  }
  const window = src.slice(idx, idx + 1200);
  if (!/['"]html['"]\s*=>\s*true/.test(window)) {
    errors.push(`[A] 字段 ${id} 未声明 'html'=>true（输出层用 wp_kses_post，必须放行 HTML）`);
  }
}

// A2) 允许 HTML 的 string 字段（当前：top_notice，输出层 header.php）
const HTML_STRINGS = [{ id: 'top_notice', file: OPT_BASIC, output: 'header.php' }];
for (const { id, file, output } of HTML_STRINGS) {
  const optSrc = fs.readFileSync(file, 'utf8');
  const idx = optSrc.search(new RegExp(`'id'\\s*=>\\s*'${id}'`));
  if (idx === -1) {
    errors.push(`[A2] 字段定义缺失: ${id} 未在 ${path.basename(file)} 找到`);
    continue;
  }
  if (!/['"]html['"]\s*=>\s*true/.test(optSrc.slice(idx, idx + 1200))) {
    errors.push(`[A2] 字段 ${id} 未声明 'html'=>true（输出层用 wp_kses_post，必须放行 HTML）`);
  }
  const outSrc = fs.readFileSync(path.join(ROOT, output), 'utf8');
  if (!/wp_kses_post/.test(outSrc)) {
    errors.push(`[A2] ${output} 中 ${id} 的输出未使用 wp_kses_post`);
  }
}

// B) sanitize_fields() 的 textarea / string 分支：html 字段必须走 wp_kses_post
const settingSrc = fs.readFileSync(SETTING, 'utf8');
// string 类型落到 default 分支（源码里写作 `default: // string`），故按注释锚点定位。
for (const [anchor, label] of [['case \'textarea\':', 'textarea'], ['default: // string', 'string']]) {
  const pos = settingSrc.indexOf(anchor);
  if (pos === -1) {
    errors.push(`[B] 未在 Jinyu_Setting.php 找到 ${label} sanitize 分支`);
    continue;
  }
  const block = settingSrc.slice(pos, pos + 700);
  if (!/!\s*empty\s*\(\s*\$f\s*\[\s*['"]html['"]\s*\]\s*\)/.test(block)) {
    errors.push(`[B] ${label} 分支未读 'html' 标记`);
  }
  if (!/wp_kses_post/.test(block)) {
    errors.push(`[B] ${label} 分支的 html 字段未使用 wp_kses_post（会再次误删 HTML）`);
  }
}

if (errors.length) {
  console.error('✗ HTML textarea 契约回归校验失败：');
  for (const e of errors) console.error('  - ' + e);
  process.exit(1);
}
console.log('✓ HTML 契约校验通过（footer_about / footer_copyright / single_copyright 为 html textarea，top_notice 为 html string，sanitize 与输出均走 wp_kses_post）');
process.exit(0);
