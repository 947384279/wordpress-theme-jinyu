/* 直编构建：less + clean-css + uglify-js，绕过 stale 的 gulp 产物。
   用法：node _build.js   （在主题根目录运行）
   仅处理 jinyu 主题前端资源，不上传。 */
const fs = require('fs');
const path = require('path');
const less = require('less');
const CleanCSS = require('clean-css');
const UglifyJS = require('uglify-js');

const ROOT = __dirname;
const STYLE_DIR = path.join(ROOT, 'assets/style');
const JS_DIR = path.join(ROOT, 'assets/js');
const DIST_STYLE = path.join(ROOT, 'assets/dist/style');
const DIST_JS = path.join(ROOT, 'assets/dist/js');

function ensureDir(d) { if (!fs.existsSync(d)) fs.mkdirSync(d, { recursive: true }); }

async function buildLess(srcName, outName) {
  const src = path.join(STYLE_DIR, srcName);
  const css = fs.readFileSync(src, 'utf8');
  const out = await less.render(css, {
    filename: src,
    paths: [STYLE_DIR],
    compress: false
  });
  const min = new CleanCSS({ level: 2 }).minify(out.css);
  if (min.errors && min.errors.length) {
    console.error('[less:' + srcName + '] errors:', min.errors);
    process.exitCode = 1;
    return;
  }
  ensureDir(DIST_STYLE);
  fs.writeFileSync(path.join(DIST_STYLE, outName), min.styles);
  console.log('  ✓ ' + outName + '  (' + (min.styles.length / 1024).toFixed(1) + ' KB)');
}

function buildJs(srcName, outName) {
  const src = path.join(JS_DIR, srcName);
  const code = fs.readFileSync(src, 'utf8');
  const result = UglifyJS.minify(code, { compress: true, mangle: true });
  if (result.error) {
    console.error('[js:' + srcName + '] error:', result.error);
    process.exitCode = 1;
    return;
  }
  ensureDir(DIST_JS);
  fs.writeFileSync(path.join(DIST_JS, outName), result.code);
  console.log('  ✓ ' + outName + '  (' + (result.code.length / 1024).toFixed(1) + ' KB)');
}

(async function () {
  console.log('Building LESS…');
  await buildLess('style.less', 'style.min.css');
  await buildLess('critical.less', 'critical.min.css');
  await buildLess('admin.less', 'admin.min.css');

  console.log('Building JS…');
  buildJs('jinyu.js', 'jinyu.min.js');
  buildJs('admin.js', 'admin.min.js');
  buildJs('ai-chat.js', 'ai-chat.min.js');
  buildJs('shortcodes.js', 'shortcodes.min.js');
  buildJs('shortcodes-gb.js', 'shortcodes-gb.min.js');

  console.log('Done.');
})();
