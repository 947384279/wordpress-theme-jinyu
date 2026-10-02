/**
 * 契约回归测试：主题「纯呈现层」的解耦契约。
 *
 * 锁死的四条不变量（每条都对应一次真实踩坑）：
 *   1. 主题不得出现任何配套插件的私有符号（jinyu_companion_* / jyc_* / jinyu_sl_* / jinyu_oauth_*）。
 *   2. 主题对外的能力需求必须走 apply_filters 广播，而不是 function_exists 探测 + 直调。
 *   3. 密文前缀判定必须集中到 jinyu_is_encrypted()，不得再硬编码单一前缀
 *      （曾因硬编码 'jinyu_enc::' 而漏判实际格式 'jinyu_enc2::'，导致二次加密）。
 *   4. 配套插件必须监听这些过滤器（否则主题广播永远无人应答，静默降级）。
 *
 * 纯静态校验，不依赖 PHP / WordPress：
 *   node tests/check-decoupling-contract.js
 * 失败退出码 1，成功 0。
 */
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const PLUGIN = path.resolve(ROOT, '..', 'jinyu-theme-companion');

const errors = [];
const notes = [];

/** 递归列出指定目录下所有 PHP 文件（跳过构建产物与依赖）。 */
function phpFiles(dir, skip = []) {
  const out = [];
  if (!fs.existsSync(dir)) return out;
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      if (skip.includes(entry.name)) continue;
      out.push(...phpFiles(p, skip));
    } else if (entry.name.endsWith('.php')) {
      out.push(p);
    }
  }
  return out;
}

const themeFiles = phpFiles(ROOT, ['node_modules', 'dist-wporg', 'release', '.git', '.workbuddy', 'tests', 'tools']);
const rel = (p) => path.relative(ROOT, p).replace(/\\/g, '/');

/** 取注释之外的代码（粗略剔除 // 与 /* *\/，避免注释里的符号名造成误报）。 */
function stripComments(src) {
  return src
    .replace(/\/\*[\s\S]*?\*\//g, '')
    .replace(/^\s*(\/\/|#).*$/gm, '');
}

/* ── 1. 主题不得引用插件私有符号 ─────────────────────────────────────── */

const FORBIDDEN = [
  { re: /\bjinyu_companion_[a-z_]+\s*\(/, why: '配套插件私有函数' },
  { re: /\bjyc_[a-z_]+/, why: '配套插件私有 option/符号前缀' },
  { re: /\bjinyu_sl_[a-z_]+/, why: '社交登录子系统的私有数据/符号' },
  { re: /\bjinyu_oauth_[a-z_]*\s*\(/, why: '社交登录私有函数' },
];

for (const file of themeFiles) {
  const code = stripComments(fs.readFileSync(file, 'utf8'));
  for (const { re, why } of FORBIDDEN) {
    const m = code.match(re);
    if (m) {
      errors.push(`[1] ${rel(file)} 引用了${why}：${m[0]} —— 主题只应通过 apply_filters 广播需求`);
    }
  }
}

/* ── 2. 关键能力必须走过滤器广播，而非探测插件函数 ───────────────────── */

const MUST_BROADCAST = [
  { file: 'functions.php', needle: "apply_filters( 'jinyu_perf_options', [] )", what: '性能开关' },
  { file: 'inc/fun/security.php', needle: "apply_filters( 'jinyu_client_ip'", what: '客户端 IP' },
  { file: 'inc/fun/security.php', needle: "apply_filters( 'jinyu_rate_limit_check'", what: '速率限制' },
  { file: 'inc/fun/crypto.php', needle: "apply_filters( 'jinyu_encrypt'", what: '敏感字段加密' },
  { file: 'inc/fun/crypto.php', needle: "apply_filters( 'jinyu_decrypt'", what: '敏感字段解密' },
];

for (const { file, needle, what } of MUST_BROADCAST) {
  const p = path.join(ROOT, file);
  if (!fs.existsSync(p)) {
    errors.push(`[2] 缺少文件 ${file}（${what} 的广播点）`);
    continue;
  }
  if (!fs.readFileSync(p, 'utf8').includes(needle)) {
    errors.push(`[2] ${file} 未广播${what}：应包含 ${needle}`);
  }
}

/* ── 3. 密文前缀判定必须集中，禁止硬编码单一前缀 ─────────────────────── */

// crypto.php 是前缀判定的唯一定义处（jinyu_is_encrypted 内必须同时出现两代前缀），
// 故把它排除后再扫描其余文件。
const PREFIX_RE = /(?:strpos|str_starts_with|0\s*===)\s*\([^)]{0,80}['"]jinyu_enc::['"]/;
for (const file of themeFiles) {
  if (rel(file) === 'inc/fun/crypto.php') continue;
  const code = stripComments(fs.readFileSync(file, 'utf8'));
  if (PREFIX_RE.test(code)) {
    errors.push(`[3] ${rel(file)} 硬编码了旧格式前缀 'jinyu_enc::' —— 应改用 jinyu_is_encrypted()`);
  }
}
const cryptoPath = path.join(ROOT, 'inc/fun/crypto.php');
if (!fs.existsSync(cryptoPath) || !fs.readFileSync(cryptoPath, 'utf8').includes('function jinyu_is_encrypted')) {
  errors.push('[3] inc/fun/crypto.php 缺少 jinyu_is_encrypted() 统一前缀判定');
} else {
  const cryptoSrc = fs.readFileSync(cryptoPath, 'utf8');
  for (const p of ['jinyu_enc2::', 'jinyu_enc::']) {
    if (!cryptoSrc.includes(`'${p}'`)) {
      errors.push(`[3] jinyu_is_encrypted() 未覆盖格式 ${p}`);
    }
  }
}

/* ── 4. 配套插件必须监听全部广播点，否则主题静默降级 ─────────────────── */

const PLUGIN_FILTERS = [
  'jinyu_perf_options',
  'jinyu_client_ip',
  'jinyu_rate_limit_check',
  'jinyu_encrypt',
  'jinyu_decrypt',
  // 扩展插槽（主题 inc/fun/extension.php 定义的三类入口）
  'jinyu_ext_markup_captcha',
  'jinyu_ext_markup_oauth_login',
  'jinyu_ext_markup_oauth_bindings',
  'jinyu_ext_value_captcha_verify',
  'jinyu_ext_value_unread_count',
  'jinyu_ext_value_following_users',
  'jinyu_ext_value_following_terms',
  'jinyu_ext_value_notifications_page',
  'jinyu_ext_value_mark_notifications_read',
];

// 主题广播出去的每个插槽，插件都必须应答，否则该功能静默消失（最难排查的一类退化）。
const PLUGIN_ACTIONS = ['jinyu_login_failed', 'jinyu_login_succeeded'];

if (!fs.existsSync(PLUGIN)) {
  notes.push('配套插件目录不在同级（' + PLUGIN + '），跳过第 4 组断言');
} else {
  const pluginSrc = phpFiles(PLUGIN, ['.git', '.workbuddy', 'node_modules'])
    .map((p) => fs.readFileSync(p, 'utf8'))
    .join('\n');
  for (const f of PLUGIN_FILTERS) {
    if (!new RegExp(`add_filter\\(\\s*['"]${f}['"]`).test(pluginSrc)) {
      errors.push(`[4] 配套插件未 add_filter( '${f}' ) —— 主题广播将无人应答`);
    }
  }
  for (const a of PLUGIN_ACTIONS) {
    if (!new RegExp(`add_action\\(\\s*['"]${a}['"]`).test(pluginSrc)) {
      errors.push(`[4] 配套插件未 add_action( '${a}' ) —— 主题广播的事件无人监听`);
    }
  }
  // 插件不得再用同名函数与主题抢定义（function_exists 守卫 = 谁先加载谁赢）。
  for (const dup of ['jinyu_encrypt', 'jinyu_decrypt', 'jinyu_client_ip', 'jinyu_rate_limit_check']) {
    const re = new RegExp(`^\\s*function\\s+${dup}\\s*\\(`, 'm');
    if (re.test(pluginSrc)) {
      errors.push(`[4] 配套插件仍定义同名函数 ${dup}() —— 会与主题抢定义，须用 jinyu_companion_* / jinyu_jy_* 前缀`);
    }
  }
}

/* ── 4b. 扩展插槽 API 自洽：用到 jinyu_ext_* 就必须有定义且被加载 ──── */

const extUsers = themeFiles.filter((f) => /\bjinyu_ext_(markup|value|enabled)\s*\(/.test(stripComments(fs.readFileSync(f, 'utf8'))));
const extDef = path.join(ROOT, 'inc/fun/extension.php');
if (extUsers.length && !fs.existsSync(extDef)) {
  errors.push('[4b] 模板/代码在调用 jinyu_ext_*，但缺少 inc/fun/extension.php（扩展插槽 API 定义）');
} else if (fs.existsSync(extDef)) {
  const defSrc = fs.readFileSync(extDef, 'utf8');
  for (const fn of ['jinyu_ext_markup', 'jinyu_ext_value', 'jinyu_ext_enabled']) {
    if (!defSrc.includes(`function ${fn}(`)) {
      errors.push(`[4b] extension.php 缺少 ${fn}() 定义`);
    }
  }
  // 必须被 core.php 加载，否则调用点在模板渲染时才 fatal。
  const coreSrc = fs.readFileSync(path.join(ROOT, 'inc/fun/core.php'), 'utf8');
  if (!coreSrc.includes("/extension.php")) {
    errors.push('[4b] inc/fun/core.php 未 require extension.php —— 模板调用 jinyu_ext_* 会致命');
  }
  notes.push('使用扩展插槽的主题文件数：' + extUsers.length);
}

/* ── 5. 数据与逻辑分离：内置词库不得留在 functions.php ───────────────── */

const fnSrc = fs.readFileSync(path.join(ROOT, 'functions.php'), 'utf8');
const spamFile = path.join(ROOT, 'inc/data/spam-words.php');
if (!fs.existsSync(spamFile)) {
  errors.push('[5] 缺少 inc/data/spam-words.php（内置词库应从主入口外迁）');
} else {
  if (/\$jinyu_builtin_spam\s*=\s*\[/.test(fnSrc)) {
    errors.push('[5] functions.php 仍内联垃圾词库数组，应 require inc/data/spam-words.php');
  }
  if (!fnSrc.includes("inc/data/spam-words.php")) {
    errors.push('[5] functions.php 未引用 inc/data/spam-words.php');
  }
  // 词库文件必须是「返回数组」的纯数据，且有 ABSPATH 守卫。
  const spamSrc = fs.readFileSync(spamFile, 'utf8');
  if (!/defined\(\s*'ABSPATH'\s*\)/.test(spamSrc)) {
    errors.push('[5] inc/data/spam-words.php 缺少 ABSPATH 直接访问守卫');
  }
  if (!/^\s*return \[/m.test(spamSrc)) {
    errors.push('[5] inc/data/spam-words.php 应以 return [...] 返回词条数组');
  }
  notes.push('内置词库词条数：' + (spamSrc.match(/^\s*'[^']+',$/gm) || []).length);
}

// functions.php 体量护栏：主入口不该再塞数据（历史上 961 行里 286 行是词库）。
const fnLines = fnSrc.split('\n').length;
if (fnLines > 700) {
  errors.push(`[5] functions.php 涨到 ${fnLines} 行（护栏 700）—— 数据/配置类内容应外迁到 inc/data 或 inc/fun`);
}
notes.push('functions.php 行数：' + fnLines);

if (errors.length) {
  console.error('✗ 解耦契约回归校验失败：');
  for (const e of errors) console.error('  - ' + e);
  process.exit(1);
}
console.log('✓ 解耦契约校验通过');
for (const n of notes) console.log('  · ' + n);
process.exit(0);