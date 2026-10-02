#!/usr/bin/env node
/**
 * 后台配置页脚本（admin.js）国际化契约校验。
 *
 * 背景：配置中心整页是 admin.js 动态拼装的，走不到 PHP 的 __()，词条靠
 * wp_localize_script 注入的 JINYU_ADMIN_I18N（词条表见 inc/fun/admin-i18n.php）。
 * 2026-10-02 出过两类事故：
 *
 *   ① 把 PHP 的 `__()` 写进 JS（为了让 i18n 契约能校验）→ 运行时 ReferenceError
 *      → init() 中断 → **配置页整块白屏**，顶栏在、内容全无。
 *   ② 只改了面板静态文案，漏了「点按钮后才触发」的一批（try/catch 提示、
 *      confirm 弹窗、进度条状态）→ 中文站仍显示英文。
 *
 * 所以这里做三条机械检查，不依赖「记得检查」：
 *   1. admin.js 里不得出现 PHP 函数调用（__, _e, esc_html__, esc_attr__ …）
 *   2. admin.js 里不得有「未包在 t()/tf() 里」的界面英文字面量
 *   3. admin.js 用到的每个 t()/tf() 键，都必须存在于 inc/fun/admin-i18n.php
 *
 * 退出码：0 通过；1 有问题。
 */

'use strict';

const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const JS = path.join(ROOT, 'assets', 'js', 'admin.js');
const TABLE = path.join(ROOT, 'inc', 'fun', 'admin-i18n.php');

const src = fs.readFileSync(JS, 'utf8');
const php = fs.readFileSync(TABLE, 'utf8');
const problems = [];

/* ----------------------------------------- 0. 先做语法检查（白屏的直接原因） */

try {
  // 用 new Function 构造做语法解析：不执行代码，只看能不能通过解析。
  // 比 spawnSync 子进程更可靠（沙箱环境下 spawnSync 可能 EBUSY）。
  // eslint-disable-next-line no-new-func
  new Function(src);
} catch (e) {
  problems.push(
    `admin.js 语法错误（会导致配置页整块白屏）：\n      ${e.message}\n` +
    `      → 语法错误会让 admin.min.js 构建失败或运行时抛异常，init() 中断后整页只剩顶栏`
  );
}

/* ------------------------------------ 1. 不得混入 PHP 函数（ReferenceError 白屏） */

// 逐行扫，先剔除 // 与 /* */ 注释行，避免把注释里的说明当成真实调用。
const codeOnly = src
  .split('\n')
  .map((l) => l.replace(/(^|[^:])\/\/.*$/, '$1').replace(/\/\*.*?\*\//g, ''))
  .join('\n');

const PHP_FNS = ['__', '_e', 'esc_html__', 'esc_attr__', 'esc_html_e', 'esc_attr_e', 'esc_html_x', 'translate'];
for (const fn of PHP_FNS) {
  const re = new RegExp(`(^|[^\\w.$])${fn.replace(/\$/g, '\\$')}\\s*\\(`, 'gm');
  const m = codeOnly.match(re);
  if (m) {
    const lines = codeOnly.split('\n');
    const hits = [];
    lines.forEach((l, i) => {
      if (new RegExp(`(^|[^\\w.$])${fn.replace(/\$/g, '\\$')}\\s*\\(`).test(l)) hits.push(i + 1);
    });
    problems.push(
      `admin.js 里出现 PHP 函数 ${fn}() —— JS 运行时没有它，会抛 ReferenceError 导致配置页白屏。\n` +
      `      行号：${hits.join(', ')}`
    );
  }
}

/* ------------------------------- 2. 不得有未包进 t()/tf() 的界面英文字面量 */

// 允许出现在 t()/tf() 的 fallback 参数里（那是设计：缺译文时回落英文原文）。
// 做法：先把所有 t()/tf(...) 的整个调用片段挖掉，再扫剩下的。
let stripped = codeOnly;
// 匹配 t('key', 'fallback') / tf('key', [a,b], 'fallback...')，允许 fallback 内含单引号转义
const CALL = /\btf?\(\s*'(?:[^'\\]|\\.)*'\s*(?:\[[^\]]*\]\s*)?(?:,\s*)?(?:'(?:[^'\\]|\\.)*'\s*)?\)/g;
stripped = stripped.replace(CALL, "''");

/*
 * 判定「界面文案」而不是「所有英文」——
 * admin.js 里英文大多是标识符（class 名、事件名、CSS 属性），不能一锅端。
 *
 * 早期版本有三个致命缺陷，导致 2026-10-02 那 17 处遗漏**全部漏检**：
 *   ① NON_UI 按整行豁免 → admin.js 绝大多数渲染代码是 `return '<div>Clear</button>'`
 *      这种 return 开头的拼接式，全被跳过；
 *   ② `line.includes('t(')` 是**行级**判断 → 一行里既有合规 t() 又有裸英文，整行放过；
 *   ③ EN_PHRASE 要求「首字母大写 + 含空格 + 单引号」→ `>Clear<`、`aria-label="Remove"`
 *      （双引号）、`' copy'`（无空格）一个都匹配不到。
 *
 * 现在改成**形态定位**而非行级匹配：只看「用户能看到的位置」。
 */

// 形态 1：>文本</button> —— 按钮/标签/选项的可见文本。
// 关键：排除 `>` 与 `<` 之间是**拼接表达式**的情况（如 `>' + esc(x) + '>`）——
// 那种片段里没有真正的字面量文案，匹配到的只是表达式碎片。
const RE_TEXT_NODE = />([^<>{}'+"`]{2,60})</g;
// 形态 2：属性值 —— placeholder="..." / title="..." / aria-label="..."
const RE_ATTR = /\b(?:placeholder|title|aria-label|alt)\s*=\s*"([^"+'`]{2,80})"/g;
// 形态 3：赋值 —— textContent='...' / innerHTML='...'
const RE_ASSIGN = /(?:textContent|innerHTML)\s*=\s*'([^'+]{3,80})'/g;

const ALLOW = new Set([
  'hidden', 'button', 'submit', 'text', 'password', 'checkbox',
  'undefined', 'application/json,.json', 'text/plain',
  // 以下是被 >Text< 形态误匹配到的结构片段（标签名 / CSS 类 / 百分比），非界面文案
  '0%', '<span class="jinyu-ms-chip-txt">', '<div class="jinyu-layout">',
]);

const bare = [];
const lines = stripped.split('\n');

const push = (lineIdx, v) => {
  const t = String(v).trim();
  if (!t || t.length < 2) return;
  if (ALLOW.has(t)) return;
  if (/[一-鿿　-〿＀-￯]/.test(t)) return;          // 已汉化
  if (/^[a-z][\w-]*$/.test(t)) return;              // 纯小写标识符
  if (/^[.#]?[\w-]+([\s>+~]+[\w-]+)*;?$/.test(t)) return; // CSS 选择器片段
  bare.push({ line: lineIdx, v: t });
};

lines.forEach((line, idx) => {
  let m;
  RE_TEXT_NODE.lastIndex = 0;
  while ((m = RE_TEXT_NODE.exec(line)) !== null) push(idx + 1, m[1]);
  RE_ATTR.lastIndex = 0;
  while ((m = RE_ATTR.exec(line)) !== null) push(idx + 1, m[1]);
  RE_ASSIGN.lastIndex = 0;
  while ((m = RE_ASSIGN.exec(line)) !== null) push(idx + 1, m[1]);
});

const seenBare = new Set();
const bareUniq = bare.filter((x) => {
  const k = x.line + ':' + x.v;
  if (seenBare.has(k)) return false;
  seenBare.add(k);
  return true;
});

if (bareUniq.length) {
  problems.push(
    `admin.js 有 ${bareUniq.length} 处界面英文字面量未包在 t()/tf() 里（中文站会显示英文）：\n` +
    bareUniq.slice(0, 25).map((x) => `      ${x.line}: '${x.v}'`).join('\n') +
    (bareUniq.length > 25 ? `\n      … 其余 ${bareUniq.length - 25} 处省略` : '') +
    `\n      → 改成 t('key', '原文') 并在 inc/fun/admin-i18n.php 补对应词条`
  );
}

/* ---------------------------------- 3. t()/tf() 用到的键必须都在词条表里 */

const usedKeys = new Set();
{
  const re = /\btf?\(\s*'([A-Za-z0-9_]+)'/g;
  let m;
  while ((m = re.exec(src)) !== null) usedKeys.add(m[1]);
}
const tableKeys = new Set();
// 允许 => 与 __( 之间夹 /* translators */ 注释
const reKey = new RegExp("'([A-Za-z0-9_]+)'\\s*=>\\s*(?:/\\*[\\s\\S]*?\\*/\\s*)?__\\(", 'g');
let m2;
while ((m2 = reKey.exec(php)) !== null) tableKeys.add(m2[1]);

const missing = [...usedKeys].filter((k) => !tableKeys.has(k)).sort();
if (missing.length) {
  problems.push(
    `admin.js 用了 ${missing.length} 个词条键但 inc/fun/admin-i18n.php 里没有：\n` +
    missing.map((k) => `      ${k}`).join('\n') +
    `\n      → 这些键会静默回退英文（缺键不影响白屏，但中文站显示英文）`
  );
}

/* --------------------------------------------------------------------- main */

if (problems.length) {
  console.error(`✗ 后台 JS 国际化契约校验失败：\n\n${problems.join('\n\n')}\n`);
  process.exit(1);
}
console.log(
  `✓ 后台 JS 国际化契约校验通过（无 PHP 函数混入、${usedKeys.size} 个词条键全部收录、无裸英文界面文案）`
);
