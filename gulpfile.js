const gulp = require('gulp');
const less = require('gulp-less');
const cleanCSS = require('gulp-clean-css');
const babel = require('gulp-babel');
const uglify = require('gulp-uglify');
const rename = require('gulp-rename');
const zip = require('gulp-zip');
const { spawnSync } = require('child_process');
const path = require('path');
const fs = require('fs');
const os = require('os');

/**
 * 版本一致性闸门：style.css 的 Version / readme.txt 的 Stable tag 必须由 package.json 派生。
 * 不一致就停在这里，绝不允许带着漂移的版本构建或打包。
 */
function versionCheck(done) {
    const r = spawnSync(process.execPath, [require('path').join(__dirname, 'tools', 'sync-version.js'), '--check'], {
        stdio: 'inherit',
    });
    if (r.status === 0) return done();
    process.exitCode = r.status;
    done(new Error('版本不一致，请先执行 npm run version:sync'));
}

function buildStyle() {
    return gulp.src('assets/style/style.less')
        .pipe(less())
        .pipe(gulp.dest('assets/dist/style'))            // unminified companion (wp.org REQUIRED: minified must ship with its original)
        .pipe(cleanCSS({ compatibility: 'ie8' }))
        .pipe(rename({ basename: 'style.min' }))
        .pipe(gulp.dest('assets/dist/style'));
}

function buildAdminStyle() {
    return gulp.src('assets/style/admin.less')
        .pipe(less())
        .pipe(cleanCSS({ compatibility: 'ie8' }))
        .pipe(rename({ basename: 'admin.min' }))
        .pipe(gulp.dest('assets/dist/style'));
}

// 设置页顶栏报警图标：不能并进只在设置页加载的 admin.min.css
function buildAlertStyle() {
    return gulp.src('assets/style/admin-alert.less')
        .pipe(less())
        .pipe(gulp.dest('assets/dist/style'))            // unminified companion (wp.org REQUIRED)
        .pipe(cleanCSS({ compatibility: 'ie8' }))
        .pipe(rename({ basename: 'admin-alert.min' }))
        .pipe(gulp.dest('assets/dist/style'));
}

function buildJs() {
    return gulp.src(['assets/js/*.js', '!assets/js/admin.js'])
        .pipe(babel({ presets: ['@babel/preset-env'] }))
        .pipe(gulp.dest('assets/dist/js'))               // unminified companion (wp.org REQUIRED)
        .pipe(uglify())
        .pipe(rename({ suffix: '.min' }))
        .pipe(gulp.dest('assets/dist/js'));
}

function buildAdminJs() {
    return gulp.src('assets/js/admin.js')
        .pipe(babel({ presets: ['@babel/preset-env'] }))
        .pipe(uglify())
        .pipe(rename({ suffix: '.min' }))
        .pipe(gulp.dest('assets/dist/js'));
}

function buildCritical() {
    return gulp.src('assets/style/critical.less')
        .pipe(less())
        .pipe(gulp.dest('assets/dist/style'))            // unminified companion (wp.org REQUIRED)
        .pipe(cleanCSS())
        .pipe(rename({ basename: 'critical.min' }))
        .pipe(gulp.dest('assets/dist/style'));
}

function watch() {
    // critical.less 是首屏关键 CSS 的镜像，改它必须同步重建，否则线上与源码不一致
    gulp.watch('assets/style/*.less', gulp.series(buildStyle, buildAdminStyle, buildAlertStyle, buildCritical));
    gulp.watch('assets/js/*.js', gulp.series(buildJs, buildAdminJs));
}

/**
 * 「上传主题」只认顶层单一 <主题目录>/ 的 zip；扁平结构会被判为无效主题包。
 * 文件名同样带版本，与 releases 附件的命名对齐（jinyu-theme-1.2.2.zip）。
 * 版本取自 package.json，与 versionCheck 同一真源，避免附件名与包内版本漂移。
 */
/**
 * 打包前合规闸门（wp.org 上传铁律，勿撤）：
 * 每次打包都必须先跑 tools/precheck-dist.js，对「将要进包的全部文件」做内容级扫描
 * —— ① 不得夹带私密信息（凭证 / 密钥对 / 明文密码 / 各类 token）；
 *     ② 不得夹带测试与调试文件（tests/、*.log、probe/debug 脚本、开发配置如 phpcs.xml.dist）；
 *     ③ 结构合规（单一顶层目录、无中文路径、style.css / readme.txt / Tested up to 格式）。
 * 闸门不通过就拒绝产出 zip，绝不生成「先打了再说」的包。
 */
function precheckGate(dir, slug, flat, list) {
    const script = path.join(__dirname, 'tools', 'precheck-dist.js');
    const argv = [script, dir, '--slug=' + slug].concat(flat ? ['--flat'] : []);
    // list 非空＝清单模式：只检查真正会进包的文件。
    // 否则闸门扫磁盘会把已被 glob 排除的东西（tests/ 等）也算进去，闸门自己把自己锁死。
    if (list && list.length) {
        // Windows 下 spawnSync 的 stdin 传参不稳（子进程输出常被吞），改用临时文件喂清单
        const tmp = path.join(os.tmpdir(), 'jinyu-gate-' + process.pid + '.lst');
        fs.writeFileSync(tmp, list.join('\n'));
        argv.push('--list=' + tmp);
        console.log('[闸门] 待检文件 ' + list.length + ' 个（与打包同一份入包清单）');
        var listFile = tmp;
    } else {
        console.log('[闸门] 警告：入包清单为空，退回全目录扫描');
        var listFile = null;
    }
    const r = require('child_process').spawnSync(process.execPath, argv, { stdio: 'inherit' });
    if (listFile) {
        try {
            fs.unlinkSync(listFile);
        } catch (e) { /* 临时文件删不掉不影响，放 tmpdir 由系统回收 */ }
    }
    if (r.status !== 0) {
        throw new Error('打包前合规闸门未通过（' + dir + '）。修掉泄露/调试文件再打包，不要绕过闸门。');
    }
    return Promise.resolve();
}

// 按同一份 glob 取「将进包的文件清单」：闸门与打包必须用完全相同的口径
function collectFiles(globList) {
    return new Promise((resolve, reject) => {
        const out = [];
        gulp.src(globList, { base: '.', read: false })
            .on('data', (f) => out.push(path.relative(f.base, f.path).split(path.sep).join('/')))
            .on('end', () => resolve(out))
            .on('error', reject);
    });
}

/**
 * 自托管完整版的入包清单，闸门与打包共用同一份（口径必须一致）。
 * package.json 必须随包发布：tools/ 里的 release.js / sync-version.js 依赖它读取 version。
 */
function buildZipGlob() {
    return [
        // package.json 必须随包发布：tools/ 里的 release.js / sync-version.js 依赖它读取 version。
        '**/*', '!node_modules/**', '!package-lock.json',
        '!gulpfile.js', '!.git/**', '!.git*',
        '!cache/**', '!*.log', '!*.zip', '!*.tmp',
        '!.workbuddy/**', '!.trae/**',
        '!.DS_Store', '!Thumbs.db',
        // w.org 变体构建产物、本仓库打包产物、临时脚本：都不是主题运行期文件（曾因漏排除让包体凭空翻倍）
        '!dist-wporg/**', '!release/**', '!tmp-*', '!tmp/**',
        // FontAwesome 子集化中间产物（.gitignore 已列，tools/fa-collect.py 可重新生成）
        '!assets/fonts/fa/icons.all.raw.txt', '!assets/fonts/fa/icons.raw.txt', '!assets/fonts/fa/icons.map.json',
        // 英文版白名单只是 w.org 构建期的中转文件（gulp build:wporg 用它覆盖包内中文版），
        // 本身不进任何包。
        '!assets/fonts/fa/icons.extra.en.txt',
        // 英文版语料与词库同理，只是 w.org 构建期的中转文件。
        '!inc/data/quotes.en.php', '!inc/data/spam-words.en.php',
        // —— 以下三项 copyWporg 已排除，自托管版曾漏掉，白带 1.5MB 死副本 ——
        // 未子集的完整 FA 压缩样式（102KB）：运行期只加载 subset.min.css，全仓库无引用。
        // 未压缩的 all.css 会随包发布，作为 all.min.css 的配对原始文件。
        '!assets/fonts/fa/all.min.css',
        // 死副本：assets/dist/img 与 assets/img 内容逐字节相同，而代码只走 assets/img/
        // （post-meta.php 的类目封面用 get_theme_file_uri('assets/img/cat-cover/…')）
        '!assets/dist/img/**',
        // 死副本：编译后 CSS 里的 url(../fonts/jinyu-text/…) 相对 assets/dist/style/ 解析，
        // 落在 assets/dist/fonts/；assets/fonts/jinyu-text/ 这份无任何引用
        '!assets/fonts/jinyu-text/**',
        // 契约检查脚本是开发期资产，不是主题运行期文件（打包铁律：测试文件不得进包）
        '!tests/**',
        // 中文说明文件：随包发布会造成非 ASCII 路径，wp.org 不收（且是本地恢复说明，非主题文档）
        '!_先读我-恢复说明.md',
        // 审计门禁的开发期资产（全量 raw 规则集 / 减法门禁 / 基线 / 刷新链路）——注意本仓库 glob 是
        // 白名单之外的「**/*」兜底，新增文件默认会进包，必须逐条排除（2026-10-03 审计门禁上线后补）：
        // 包里已有 phpcs.xml.dist 一份开发配置，再带 phpcs-all.xml.dist 会出现第二份规则集。
        '!phpcs-all.xml.dist', '!tools/audit*', '!tools/refresh-raw.sh', '!tools/remote-run-raw.sh'
    ];
}

async function buildZip() {
    const version = require('./package.json').version;
    const files = buildZipGlob();
    // 源目录自检：zip 会把整体 rename 成 jinyu/，故用 flat（跳过顶层结构检查）
    await precheckGate('.', 'jinyu', true, await collectFiles(files));
    return gulp.src(files)
        .pipe(rename((p) => { p.dirname = path.posix.join('jinyu', p.dirname || ''); }))
        .pipe(zip(`jinyu-theme-${version}.zip`))
        // 输出到仓库内的 release/（.gitignore 已排除），与 gulp wporg 的产物同目录。
        // 曾用 gulp.dest('..')：在 Windows 下 '..' 会解析到盘符根（D:\），
        // gulp 试图 mkdir 盘符根 → EPERM 直接失败（实测已踩）。
        .pipe(gulp.dest('release'));
}

/**
 * w.org 专用发行变体：从同一份源码构建出「无独立设置页 + 无任意代码注入」的合规主题。
 * 单真源策略——主仓库始终保留豪华设置页（自托管/GitHub/Gitee 版），
 * 此任务仅生成剔除后的发行包，源码不重复维护。
 *
 * slug 必须与自托管版区分（jinyu vs jinyu-lite）：
 * WordPress 主题自动更新按 slug 匹配，两版同名会让装了自托管版的站点
 * 被 w.org 版覆盖（独立设置页/代码注入随之消失）。
 * 产物：release/jinyu-lite-<version>.zip（顶层单 jinyu-lite/ 目录）。
 *
 * 命名硬约束（上传器 + Theme Check 双重校验，均实测踩过）：
 *  1. 主题名 / slug 不得含 "WordPress" 或 "Theme"——w.org 上传器直接拒收
 *     （曾用 "Jinyu Theme Lite" 被拒：「您不能在主题名称中使用 WordPress 或 Theme」）。
 *  2. 显示名必须能 sanitize 成同一个 slug（sanitize_title('Jinyu Lite') === 'jinyu-lite'），
 *     否则 Theme Check 报 "wrong directory"（它用 Theme Name 反推 slug 与目录名比对）。
 */
const WPORG_SLUG = 'jinyu-lite';
// 显示名见上「命名硬约束」：不可含 "WordPress"/"Theme"，且须 sanitize 成 WPORG_SLUG。
const WPORG_NAME = 'Jinyu Lite';
// 变体描述必须与实际能力一致：自托管版描述里的「支持后台可视化配置」在变体中是 Customizer
// （独立设置中心与代码注入均已剔除），照抄会误导审核方与用户。
// 描述同样避开 "WordPress" 字样，避免被读成与官方存在隶属关系。
const WPORG_DESC = 'A clean, responsive blog theme with dark mode, multiple layouts, shortcodes, likes & bookmarks, comment interactions and infinite scroll. All appearance options are configured in the Customizer (Appearance → Customize).';
const WPORG_DIR = `dist-wporg/${WPORG_SLUG}`;

/**
 * w.org 变体里「HTML 注释」的中英对照表。
 *
 * 背景：审核员要求「所有用户可见文本必须是英文」。PHP 的行注释与块注释不渲染到页面，
 * 但 HTML 注释（尖括号包裹的注释语法）虽是源码文本、同样是包内可见的中文字面量，审核团队 grep 源码时
 * 一定会看到。数量不多（11 处），故在构建期按显式映射逐条替换 —— 不做正则模糊替换，
 * 避免误伤；也便于日后人工核对每一条译文是否贴切。
 *
 * key 为原中文注释片段（需在源文件中唯一命中），value 为英文译文。
 */
const WPORG_HTML_COMMENT_EN = {
	// inc/setting/template.php —— 后台设置页
	'顶栏：玻璃吸顶操作条。\n\t\t品牌标识与页面标题已从这里下放到侧栏顶部与内容卡卡头，顶栏只留「当前位置 + 全局操作」，\n\t\t高度 60px 且滚动时常驻，避免长表单「滚到底才能保存」。所有按钮 id 保持不变，\n\t\tadmin.js 的事件绑定与快捷键（Ctrl+K）无需改动。':
		'Top bar: a glass sticky action bar. The brand identity and page title were moved down to the\n\t\tsidebar top and the content card header, so the top bar only keeps "current location + global actions".\n\t\tIt is 60px tall and stays visible while scrolling, so long forms do not force a scroll to the bottom\n\t\tjust to reach the save button. All button ids are unchanged, so the event bindings and the Ctrl+K\n\t\tshortcut in admin.js need no changes.',
	'当前分组名由 admin.js 在切换分组时写入':
		'The current group name is written by admin.js when the group is switched.',
	'「恢复默认」保留纯图标（低频次操作，图标比文字更克制）；图标提示由\n\t\t\t.jinyu-icon-btn 的 CSS 浮层渲染，不引 JS、也不与原生 title 重复。':
		'"Restore defaults" stays icon-only (a low-frequency action; an icon is more restrained than a label).\n\t\t\tThe tooltip is rendered by the CSS overlay of .jinyu-icon-btn, so no extra JavaScript is needed\n\t\t\tand it does not duplicate the native title attribute.',
	'移动端深色切换：窄屏时侧栏底部工具条隐藏，把月亮/太阳切钮放到恢复默认右侧。\n\t\t\t桌面端通过 CSS 隐藏，JS 对所有 .jinyu-theme-toggle 统一绑定。':
		'Mobile dark-mode toggle: on narrow screens the bottom sidebar toolbar is hidden and the moon/sun\n\t\t\tswitch is moved next to "Restore defaults". On desktop it is hidden via CSS, and JavaScript binds\n\t\t\tall .jinyu-theme-toggle elements uniformly.',
	'设置分组（含「维护工具」面板）由 admin.js 渲染':
		'The settings groups (including the "Maintenance tools" panel) are rendered by admin.js.',
	// templates/auth-modal.php —— 登录/注册弹窗
	'登录': 'Sign in',
	'注册': 'Register',
	'找回密码': 'Lost password',
	// pages/template-tags.php —— 标签云
	'字母导航': 'Alphabet navigation',
	'分组列表': 'Grouped list',
	// header.php —— 旧内核盾
	'古老内核盾：内联识别过旧渲染引擎（Chromium<120 / Safari<15 / Firefox<110），命中给 <html> 打 jinyu-legacy，CSS 据此拆除毛玻璃等 GPU 合成触发点，避免渲染进程崩溃。必须在 </head> 前同步执行，首帧即生效、无闪烁。':
		'Legacy engine shield: an inline script detects outdated rendering engines (Chromium < 120 / Safari < 15 /\n\t\tFirefox < 110) and flags <html> with jinyu-legacy, so the CSS can drop the backdrop-filter effects that\n\t\ttrigger GPU compositing and crash the renderer. It must run synchronously before </head> so that it\n\t\ttakes effect on the first frame, with no flash of unstyled content.',
};

function copyWporg() {
	// 先清空上一次构建产物，避免 gulp.dest 增量写入导致旧文件（如被新规则剔除的 .less/原始 .js）残留进包
	fs.rmSync(WPORG_DIR, { recursive: true, force: true });
	return gulp.src([
		'**/*', '!node_modules/**', '!package-lock.json',
		'!gulpfile.js', '!.git/**',
		'!cache/**', '!*.log',
		'!.workbuddy/**', '!.trae/**',
		'!dist-wporg/**',
		'!*.zip',
		'!release/**',
		// 本仓库与工具链专属文件不该出现在提交包内：package.json / tools / tests / README.md。
		'!package.json', '!README.md',
		// SECURITY.md 是中文运维/部署须知（非主题运行期文件，且非英文）；用户侧数据口径已由 readme.txt 的 Privacy 节覆盖。
		// 保留在仓库供自托管版参考，但 w.org 变体不随包发布，避免包内出现非英文文档（踩过 review 红线）。
		'!SECURITY.md',
		'!tools/**', '!tests/**',
		// 临时文件兜底：调试期的 tmp-* 产物若落在项目根，绝不能让它们混进提交包（已实际踩过）
		'!tmp-*', '!tmp/**',
		// —— 以下为「.gitignore 已排除 = 非主题源」的构建中间产物与死副本，一律不进提交包 ——
		// FontAwesome 子集化中间产物：.gitignore 已列，tools/fa-collect.py 可随时重新生成
		'!assets/fonts/fa/icons.all.raw.txt', '!assets/fonts/fa/icons.raw.txt', '!assets/fonts/fa/icons.map.json',
		// 英文版白名单只是构建期的中转文件（localizeWporg 用它覆盖包内中文版），本身不进包。
		'!assets/fonts/fa/icons.extra.en.txt',
		// 同理：英文版语料与词库是 localizeWporg 的中转文件，包内只保留
		// inc/data/quotes.php 与 inc/data/spam-words.php 这两个文件名。
		'!inc/data/quotes.en.php', '!inc/data/spam-words.en.php',
		// 未子集的完整 FA 压缩样式（102KB）：运行期只加载 subset.min.css，全仓库无引用。
		// 但 wp.org 要求「压缩文件必须同时提供未压缩原始文件」——
		// subset.min.css 的配对源是 subset.css（未压缩），all.min.css 的配对源是 all.css（未压缩），
		// 两者都随包发布，故 all.min.css 仅作构建期裁剪源，不进提交包。
		'!assets/fonts/fa/all.min.css',
		// 死副本：assets/dist/img 与 assets/img 内容逐字节相同，而代码只走 assets/img/
		// （post-meta.php 的类目封面用 get_theme_file_uri('assets/img/cat-cover/…')）
		'!assets/dist/img/**',
		// 死副本：编译后 CSS 里的 url(../fonts/jinyu-text/…) 相对 assets/dist/style/ 解析，
		// 落在 assets/dist/fonts/；assets/fonts/jinyu-text/ 这份无任何引用
		'!assets/fonts/jinyu-text/**',
		// 操作系统 / 编辑器垃圾兜底
		'!.DS_Store', '!Thumbs.db', '!*.tmp', '!*.swp', '!*~',
		// 独立设置页文件在 w.org 变体中整体剔除（逻辑已由 JINYU_WPORG 门控跳过，此处一并移除文件）。
		'!inc/setting/Jinyu_Setting.php',
		// —— 隐私合规：以下文件含「把访客数据发往第三方」的代码路径，发行变体整体剔除 ——
		// inc/fun/live.php：IP 归属地查询会把访客 IP 发往 whois.pconline.com.cn。
		//   代码里已加 jinyu_is_wporg() 硬门控（option 被写成 true 也不发请求），
		//   这里再把文件整体移除，确保提交包内 grep 不到任何远程 IP 查询。
		'!inc/fun/live.php',
		// 头部 5 个国内头像镜像在 inc/fun/user.php 的 jinyu_avatar_mirror_map() 里，
		//   选中非官方源会把评论者邮箱 md5 发往第三方。该函数已加 wporg 门控
		//   （发行变体只返回官方 gravatar.com），无需剔除整个 user.php。
		// —— 以下为「开发源文件」：运行期只加载编译产物 ——
		// wp.org REQUIRED：凡随包发布的压缩文件，必须同时提供未压缩原始文件（成对）。
		// 故 .less 源 / 顶层原始 .js / vendor 原始 .js 一律随包提供，便于审核与用户核对。
		// 仅剔除 w.org 发行变体不使用的后台设置页资产（运行期无对应加载，不触发“必须随附原始文件”要求）：
		'!assets/dist/style/admin.min.css',
		'!assets/dist/js/admin.min.js',
		'!assets/style/admin.less',
		'!assets/js/admin.js',
		// WP.org 只需一份许可证：保留 license.txt，删去大写的 LICENSE 副本（内容相同，纯冗余）
		'!LICENSE',
		// 仓库本地恢复说明（中文名 `_先读我-恢复说明.md`）：纯开发/运维内部文档，
		// 非主题运行期文件，且非 ASCII 文件名在部分平台不通用（WP.org 上传会拒）。不应进提交包。
		'!_先读我-恢复说明.md',
		// wp.org REQUIRED：生产主题包严禁夹带开发配置，自动扫描判 REQUIRED 直接拒包。
		// 根目录两份 PHPCS 规则集一律剔除（自托管版 buildZipGlob 刻意保留 phpcs.xml.dist，
		// 但 w.org 变体不得带任何一份——曾因漏排除整包被判不通过）。
		'!phpcs.xml.dist', '!phpcs-all.xml.dist'
	], { base: '.' })
		.pipe(gulp.dest(WPORG_DIR));
}

function flagWporg(done) {
	fs.writeFileSync(
		`${WPORG_DIR}/inc/jinyu-wporg.php`,
		"<?php\n" +
		"// Auto-generated by `gulp build:wporg`. Marks this build as the w.org-compliant variant.\n" +
		"// The main repo never ships this file; only the w.org release artifact does.\n" +
		"define( 'JINYU_WPORG', true );\n"
	);
	done();
}

/**
 * 把 FontAwesome 图标白名单替换成英文注释版，并把中文语料/词库换成英文版。
 *
 * 1) 仓库里的 assets/fonts/fa/icons.extra.txt 带中文说明（面向维护者），
 *    不能进 wp.org 提交包（包内不得出现非英文文档）。构建时用等价的英文版
 *    icons.extra.en.txt 覆盖包内同名文件 —— 图标清单内容一字不差，只是注释换成英文。
 * 2) 每日一句语料（inc/data/quotes.php）是中文古诗文，随包发布会让英文站的前台
 *    小组件输出非英文文案 —— w.org 变体改用等价的英文语料 inc/data/quotes.en.php。
 * 3) 垃圾评论词库（inc/data/spam-words.php）：仓库版是**中文**词表（面向中文站）。
 *    w.org 变体改用纯英文词表 inc/data/spam-words.en.php —— 词条是**运行时匹配数据**
 *    （用于匹配访客评论，前台不展示），换成英文词表后：
 *      a) 包内不再有任何中文字面量，审核团队 grep 源码时无话可说；
 *      b) 英文站真正会遇到的是英文垃圾评论，英文词表命中率更高；
 *      c) 需要中文词表的站长用自托管版（那里保留完整中文库）。
 */
function localizeWporg(done) {
	const faDir = path.join(WPORG_DIR, 'assets', 'fonts', 'fa');
	const zhWhitelist = path.join(faDir, 'icons.extra.txt');
	// 英文版从仓库源目录读取，不复制进包（它只是构建期的中转文件）。
	const enWhitelist = path.join(__dirname, 'assets', 'fonts', 'fa', 'icons.extra.en.txt');
	if (!fs.existsSync(zhWhitelist) || !fs.existsSync(enWhitelist)) {
		return done(new Error('localizeWporg: 缺少 icons.extra.txt 或 icons.extra.en.txt'));
	}
	fs.copyFileSync(enWhitelist, zhWhitelist);

	// 语料：英文版整体替换中文版（包内只保留 quotes.php 这一个文件名）。
	const dataDir = path.join(WPORG_DIR, 'inc', 'data');
	const quotesZh = path.join(dataDir, 'quotes.php');
	const quotesEn = path.join(__dirname, 'inc', 'data', 'quotes.en.php');
	if (!fs.existsSync(quotesZh) || !fs.existsSync(quotesEn)) {
		return done(new Error('localizeWporg: 缺少 inc/data/quotes.php 或 quotes.en.php'));
	}
	fs.copyFileSync(quotesEn, quotesZh);
	// 中转文件已被打包排除规则挡在包外，正常不会出现在包里；存在才删（容错）。
	if (fs.existsSync(path.join(dataDir, 'quotes.en.php'))) {
		fs.unlinkSync(path.join(dataDir, 'quotes.en.php'));
	}

	// 垃圾词库：w.org 变体整体换成纯英文词表（见 localizeWporg 顶部说明）。
	const spamZh = path.join(dataDir, 'spam-words.php');
	const spamEn = path.join(__dirname, 'inc', 'data', 'spam-words.en.php');
	if (!fs.existsSync(spamZh) || !fs.existsSync(spamEn)) {
		return done(new Error('localizeWporg: 缺少 inc/data/spam-words.php 或 spam-words.en.php'));
	}
	fs.copyFileSync(spamEn, spamZh);
	if (fs.existsSync(path.join(dataDir, 'spam-words.en.php'))) {
		fs.unlinkSync(path.join(dataDir, 'spam-words.en.php'));
	}

	// 4) 移除「旧版中文垃圾词库」迁移逻辑（inc/fun/opt.php 的 jinyu_migrate_spam_words）：
	//    该函数靠匹配旧版中文内置词库的两个字符串来判断「用户没自定义过」。
	//    w.org 发行变体面向英文站点，不存在「停留在中文旧库」这个状态，
	//    函数整体移除即可顺带消掉这两串中文常量。
	const optPhp = path.join(WPORG_DIR, 'inc', 'fun', 'opt.php');
	if (fs.existsSync(optPhp)) {
		let s = fs.readFileSync(optPhp, 'utf8');
		const MIG = /function jinyu_migrate_spam_words\(\): void \{[\s\S]*?\n\}\nadd_action\( 'init', 'jinyu_migrate_spam_words', 6 \);\n/;
		if (!MIG.test(s)) {
			return done(new Error('localizeWporg: 未能定位 jinyu_migrate_spam_words()，旧版中文词库常量会残留在包内'));
		}
		s = s.replace(MIG, '');
		fs.writeFileSync(optPhp, s);
	}

	// 5) 社交平台配置里的中文键名（inc/fun/template-tags.php 的 jinyu_parse_social 映射表）：
	//    这些键是「站长填写社交平台」的输入匹配键（填「微博」也能识别），输出的是图标，
	//    不是用户可见文案。但审核团队会 grep 源码里的中文字面量，为免被追问，
	//    w.org 变体只保留英文键名 —— 英文站点本来也只会填英文平台名。
	const tagsPhp = path.join(WPORG_DIR, 'inc', 'fun', 'template-tags.php');
	if (fs.existsSync(tagsPhp)) {
		let s = fs.readFileSync(tagsPhp, 'utf8');
		// 中文键 → 去掉该行（英文键同名项已在同一张表里保留）
		s = s.replace(/^\t*'(微博|微信|邮箱|知乎|b站|抖音|豆瓣|今日头条)'\s*=>.*\n/gm, '');
		fs.writeFileSync(tagsPhp, s);
	}

	// 6) error_log 的中文日志文本（inc/fun/user.php）：仅写入服务器日志，不输出到页面，
	//    但同样是包内可见的中文字面量 —— 换成英文。
	const userPhp = path.join(WPORG_DIR, 'inc', 'fun', 'user.php');
	if (fs.existsSync(userPhp)) {
		let s = fs.readFileSync(userPhp, 'utf8');
		s = s.replace(
			"'[jinyu] pending-email token 迁移完成，回填 %d 条。'",
			"'[jinyu] pending-email token migration completed, %d rows backfilled.'"
		);
		fs.writeFileSync(userPhp, s);
	}

	// 7) HTML 注释（<!-- -->）里的中文：虽不渲染到访客可见文本，但仍是包内可见的中文字面量，
	//    审核团队 grep 源码一定会看到。按显式对照表逐条替换为英文（见 WPORG_HTML_COMMENT_EN）。
	const missing = [];
	walkDir(WPORG_DIR, (file) => {
		if (path.extname(file).toLowerCase() !== '.php') return;
		let s = fs.readFileSync(file, 'utf8');
		const before = s;
		for (const [zh, en] of Object.entries(WPORG_HTML_COMMENT_EN)) {
			if (s.includes(zh)) {
				s = s.split(zh).join(en);
			}
		}
		if (s !== before) fs.writeFileSync(file, s);
	});
	// 替换后若包内仍有含中文的 HTML 注释，说明对照表漏了条目 —— 硬失败，
	// 不能带着漏网的注释出包（否则审核时又要解释一轮）。
	walkDir(WPORG_DIR, (file) => {
		if (path.extname(file).toLowerCase() !== '.php') return;
		const s = fs.readFileSync(file, 'utf8');
		const rest = s.match(/<!--[\s\S]*?-->/g) || [];
		for (const c of rest) {
			if (/[\u4e00-\u9fff]/.test(c)) {
				missing.push(`${path.relative(WPORG_DIR, file)}: ${c.slice(0, 80)}`);
			}
		}
	});
	if (missing.length) {
		return done(new Error('localizeWporg: 仍有含中文的 HTML 注释未英文化：\n' + missing.join('\n')));
	}

	done();
}

function walkDir(dir, cb) {
	for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
		const p = path.join(dir, e.name);
		if (e.isDirectory()) walkDir(p, cb);
		else cb(p);
	}
}

/**
 * 把变体的 slug / text domain / 主题名从 jinyu 改为 jinyu-lite。
 * 只替换「引号包裹的 'jinyu'」（text domain 字面量）：
 * 不会误伤 jinyu_ 函数前缀、'jinyu_options' 选项名、'JINYU_WPORG' 常量（后缀/大小写不匹配）。
 */
function renameWporg(done) {
	walkDir(WPORG_DIR, (file) => {
		const ext = path.extname(file).toLowerCase();
		if (!['.php', '.js', '.css'].includes(ext)) return;
		let s = fs.readFileSync(file, 'utf8');
		const before = s;
		s = s.replace(/(['"])jinyu\1/g, `$1${WPORG_SLUG}$1`);
		s = s.replace(/^Text Domain: jinyu$/m, `Text Domain: ${WPORG_SLUG}`);
		s = s.replace(/^Theme Name: .*$/m, `Theme Name: ${WPORG_NAME}`);
		// 描述只改主题头，且只改 style.css：其它文件里的 "Description:" 是设置字段标签，不可动。
		if (path.basename(file) === 'style.css') {
			// Theme URI 在 w.org 变体整体移除：wp.org 页面尚未上线，保留外站 URI 会被判不合格。
			// （Theme URI 可选；若保留则必须指向 wp.org 托管的本主题页，故直接删去最稳妥。）
			s = s.replace(/^Theme URI: .*$/m, '');
			s = s.replace(/^Description: .*$/m, `Description: ${WPORG_DESC}`);
			// 作者名是中文「金玉」：w.org 要求主题头可读性为英文，中文作者名会被审核挑出。
			// 自托管版保留原样（站长自己的站），只在发行变体英文化。
			s = s.replace(/^Author: .*$/m, 'Author: Jinyu');
		}
		if (s !== before) fs.writeFileSync(file, s);
	});

	// 隐私合规（物理移除）：发行变体运行时虽已被 jinyu_is_wporg() 门控成只用官方
	// gravatar.com，但 5 个国内镜像的域名**字符串仍留在源码里**——审核团队会直接 grep
	// 远程域名，看到就会追问「这些外发是干什么的、Privacy 里有没有交代」。
	// 故在构建期把整个镜像表字面量替换掉，做到 grep 不到。
	// 自托管版（主仓库）不受影响，镜像功能完整保留。
	const userPhp = path.join(WPORG_DIR, 'inc', 'fun', 'user.php');
	if (fs.existsSync(userPhp)) {
		let s = fs.readFileSync(userPhp, 'utf8');
		// 锚点：从 wporg 门控的 return 到镜像表结尾的 ];（非贪婪 + 明确闭合）
		const MIRROR_BLOCK = /if \( jinyu_is_wporg\(\) \) \{[\s\S]*?\n\t\}\n\n\treturn \[\n(?:\t\t'[a-z0-9]+'\s*=>\s*'https:\/\/[^']*\/',\n)+\t\];/;
		const STUB =
			"if ( jinyu_is_wporg() ) {\n\t\treturn [ 'gravatar' => 'https://gravatar.com/avatar/' ];\n\t}\n\n" +
			"\t// w.org 发行构建：国内镜像域名已在构建期物理移除（见 gulpfile.js renameWporg）。\n" +
			"\treturn [ 'gravatar' => 'https://gravatar.com/avatar/' ];";
		const replaced0 = s.replace(MIRROR_BLOCK, STUB);
		if (replaced0 === s) {
			return done(new Error('renameWporg: 未能替换 jinyu_avatar_mirror_map() 镜像表，镜像域名会残留在提交包内'));
		}
		// 上方 docblock 里提到具体镜像的失效与探测细节，同样含域名/冗余，一并精简。
		const replaced = replaced0
			.replace(/ \* 国内头像镜像表：[\s\S]*?\*\/\nfunction jinyu_avatar_mirror_map/, ' * 可用的头像源表。w.org 发行构建只保留官方 gravatar.com。\n */\nfunction jinyu_avatar_mirror_map')
			.replace(/ \* 后台「头像来源」选的源未必活着[\s\S]*?\* 而非「先发一次 404 再回退」。/, ' * 探测由 shutdown 钩子补齐，最多 6 小时重跑一次，避免首屏先发一次失败请求。');
		fs.writeFileSync(userPhp, replaced);
	}
	// 翻译文件的 domain 由文件名决定，同步重命名（.mo 为二进制，只改名不读内容）
	const langDir = path.join(WPORG_DIR, 'languages');
	if (fs.existsSync(langDir)) {
		for (const f of fs.readdirSync(langDir)) {
			if (f.startsWith('jinyu') && !f.startsWith(WPORG_SLUG)) {
				fs.renameSync(path.join(langDir, f), path.join(langDir, WPORG_SLUG + f.slice(5)));
			}
		}
	}
	// 变体专属 readme：主 readme 描述的是自托管完整版（独立设置中心、代码注入等），
	// 直接沿用会对审核方与用户双重失实。tools/ 不进包，但可从仓库路径读取。
	const wporgReadme = path.join(__dirname, 'tools', 'readme-wporg.txt');
	if (fs.existsSync(wporgReadme)) {
		fs.copyFileSync(wporgReadme, path.join(WPORG_DIR, 'readme.txt'));
	} else {
		done(new Error('缺少 tools/readme-wporg.txt，变体 readme 无法生成'));
		return;
	}
	done();
}

async function zipWporg() {
	const version = require('./package.json').version;
	// 产物目录自检（严格模式）：顶层必须已是单一 <slug>/ 目录，这里下钻检查
	await precheckGate(path.join(__dirname, 'dist-wporg'), WPORG_SLUG);
	return gulp.src(`${WPORG_DIR}/**/*`, { base: 'dist-wporg' })
		.pipe(zip(`${WPORG_SLUG}-${version}.zip`))
		.pipe(gulp.dest('release'));
}

const buildWporg = gulp.series(versionCheck, copyWporg, flagWporg, renameWporg, localizeWporg, zipWporg);

// 两个后台样式都必须进这条链，否则 gulp build 不会产出它们（watch 重建后与线上不一致）
const buildTasks = gulp.parallel(
    buildStyle,
    buildAdminStyle,
    buildAlertStyle,
    buildJs,
    buildAdminJs,
    buildCritical
);
const dev = gulp.series(buildTasks, watch);
const build = gulp.series(versionCheck, buildTasks);
const buildZipSafe = gulp.series(versionCheck, buildZip);

exports.default = build;
exports.dev = dev;
exports.build = build;
exports.zip = buildZipSafe;
// 单任务出口：只改了后台 JS 一类局部改动时不必走 build 全量（也不会被 versionCheck 拦下）
exports.js = buildJs;
exports.adminJs = buildAdminJs;
exports.style = gulp.parallel(buildStyle, buildAdminStyle, buildAlertStyle);
exports.critical = buildCritical;
exports.wporg = buildWporg;



