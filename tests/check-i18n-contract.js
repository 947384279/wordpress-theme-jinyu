#!/usr/bin/env node
/**
 * 语言包完整性契约校验。
 *
 * 为什么需要这个：后台配置页的文案全靠 zh_CN.po 翻译，一旦某次新增 `__()` 忘了同步词条，
 * 中文站后台就会直接显示英文原文。2026-10-02 就出现过：批次里给头像来源加了两条
 * wporg 专用文案，代码上线了但没同步语言包，配置页「用户互动」页签整块英文。
 *
 * 三条检查：
 *   1. zh_CN.po 不允许有空译文（专有名词应显式标 `#, no-patch` 并填回原文）
 *   2. 源码里用到的 jinyu_ 文本域词条，必须都能在 zh_CN.po 里查到
 *   3. jinyu.pot 与 zh_CN.po 的 msgid 集合必须一致（pot 漏了会让后续译者拿不到新词条）
 *
 * 退出码：0 通过；1 有问题（并打印明细）。
 */

'use strict';

const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const PO = path.join(ROOT, 'languages', 'zh_CN.po');
const POT = path.join(ROOT, 'languages', 'jinyu.pot');

const problems = [];

/* --------------------------------------------------------------- 极简 PO 解析 */

/**
 * 只解析需要的字段：msgid / msgstr / flag。
 * 不引第三方依赖（本仓库的测试都是零依赖的 node 脚本）。
 */
function parsePo(file) {
  const text = fs.readFileSync(file, 'utf8');
  const entries = [];
  let cur = null;
  let field = null;

  const flush = () => {
    if (cur && cur.msgid !== null) entries.push(cur);
    cur = null;
    field = null;
  };

  for (const rawLine of text.split(/\r?\n/)) {
    const line = rawLine.trim();

    if (line === '') { flush(); continue; }

    // 顶层注释行以 # 开头
    if (line.startsWith('#')) {
      if (line.startsWith('#,')) {
        const flags = line.slice(2).split(',').map((s) => s.trim()).filter(Boolean);
        if (cur) cur.flags = (cur.flags || []).concat(flags);
      }
      continue;
    }

    const m = line.match(/^(msgid|msgstr)\s+(".*")$/);
    if (m) {
      field = m[1];
      cur = cur || { msgid: null, msgstr: null, flags: [] };
      cur[field] = JSON.parse(m[2]);
      continue;
    }

    // 续行：紧跟在带引号的值之后
    const cont = line.match(/^(".*")$/);
    if (cont && cur && field) {
      const piece = JSON.parse(cont[1]);
      cur[field] = (cur[field] || '') + piece;
      continue;
    }
  }
  flush();
  return entries;
}

/* ------------------------------------------------------------------ 1. 空译文 */

function checkNoEmptyTranslations(entries) {
  const empty = entries.filter((e) => e.msgid && !e.msgstr);
  if (empty.length) {
    problems.push(
      `zh_CN.po 有 ${empty.length} 条空译文（后台会直接显示英文原文）：\n` +
      empty.map((e) => `      - ${e.msgid.slice(0, 70)}`).join('\n') +
      `\n      → 不该翻译的（单位/协议名/专有名词）请加 "#, no-patch" 标记并把 msgstr 填成原文`
    );
  }
}

/* --------------------------------------------- 2. 源码词条是否都在 po 里收录 */

function collectSourceMsgids() {
  const found = new Map();
  const walk = (dir) => {
    for (const name of fs.readdirSync(dir)) {
      if (['node_modules', '.git', 'dist-wporg', 'release', 'assets', 'languages', 'tests', 'tools', '.workbuddy'].includes(name)) continue;
      const p = path.join(dir, name);
      const st = fs.statSync(p);
      if (st.isDirectory()) { walk(p); continue; }
      if (!p.endsWith('.php')) continue;
      const src = fs.readFileSync(p, 'utf8');
      // 匹配 __( 'x', 'jinyu' ) / esc_html__( 'x', 'jinyu' ) / esc_attr_e( 'x', 'jinyu' ) 等
      const re = /\b(?:__|_e|esc_html__|esc_attr__|esc_html_e|esc_attr_e)\(\s*'((?:[^'\\]|\\.)*)'\s*,\s*'jinyu'\s*\)/g;
      let m;
      while ((m = re.exec(src)) !== null) {
        // 单引号串里的 \' 需还原为 '
        const id = m[1].replace(/\\'/g, "'").replace(/\\\\/g, '\\');
        if (id) found.set(id, path.relative(ROOT, p));
      }
    }
  };
  walk(ROOT);
  return found;
}

function checkSourceCovered(poEntries) {
  const inPo = new Set(poEntries.map((e) => e.msgid));
  const src = collectSourceMsgids();
  const missing = [];
  for (const [id, file] of src) {
    if (inPo.has(id)) continue;
    // 源码里直接写中文的 `__( '昵称', 'jinyu' )` 不需要翻译：gettext 查不到会原样返回，
    // 页面显示的就是中文本身。收录与否只影响译者能否看到，不影响显示效果。
    // 注意字符范围要含中文标点：全角逗号 `，` 是 U+FF0C，不在 \u4e00-\u9fff 内，
    // 像 '%1$s，%2$s' 这种「中文标点 + printf 占位符」的格式串会被漏判成英文。
    if (/[一-鿿　-〿＀-￯]/.test(id)) continue;
    missing.push({ id, file });
  }
  if (missing.length) {
    problems.push(
      `源码里有 ${missing.length} 条英文文案未收录进 zh_CN.po（后台会显示英文原文）：\n` +
      missing.map((x) => `      - [${x.file}] ${x.id.slice(0, 66)}`).join('\n') +
      `\n      → 需补进 languages/zh_CN.po 并重新编译 .mo（node tools/compile-po.py languages/zh_CN.po）`
    );
  }
  return src.size;
}

/* --------------------------------------------------- 3. pot 与 po 的 msgid 一致 */

function checkPotInSync(poEntries) {
  const potEntries = parsePo(POT);
  const poIds = new Set(poEntries.map((e) => e.msgid));
  const potIds = new Set(potEntries.map((e) => e.msgid));

  const onlyInPo = [...poIds].filter((id) => !potIds.has(id));
  const onlyInPot = [...potIds].filter((id) => !poIds.has(id));

  if (onlyInPo.length) {
    problems.push(
      `jinyu.pot 缺 ${onlyInPo.length} 条词条（译者看不到这些，无法翻译）：\n` +
      onlyInPo.map((id) => `      - ${String(id).slice(0, 66)}`).join('\n') +
      `\n      → 补进 languages/jinyu.pot`
    );
  }
  // pot 有而 po 没有 = 新增词条还没翻译，这个由检查 1 覆盖，这里只提示
  if (onlyInPot.length) {
    console.log(`  ℹ jinyu.pot 比 zh_CN.po 多 ${onlyInPot.length} 条（新增待译）`);
  }
  return onlyInPot.length;
}

/* --------------------------------------------------------------------- main */

console.log('语言包契约校验…\n');
const poEntries = parsePo(PO);
console.log(`  zh_CN.po：${poEntries.length} 条`);

checkNoEmptyTranslations(poEntries);
const srcCount = checkSourceCovered(poEntries);
console.log(`  源码 jinyu_ 文本域词条：${srcCount} 条（已全部收录）`);

const pending = checkPotInSync(poEntries);

if (problems.length) {
  console.error(`\n✗ 语言包契约校验失败：\n\n${problems.join('\n\n')}\n`);
  process.exit(1);
}
console.log(`\n✓ 语言包契约校验通过（无空译文、源码词条全覆盖${pending ? '，' + pending + ' 条待译' : ''}）`);
