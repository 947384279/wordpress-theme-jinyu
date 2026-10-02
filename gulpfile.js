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
        .pipe(cleanCSS({ compatibility: 'ie8' }))
        .pipe(rename({ basename: 'admin-alert.min' }))
        .pipe(gulp.dest('assets/dist/style'));
}

function buildJs() {
    return gulp.src(['assets/js/*.js', '!assets/js/admin.js'])
        .pipe(babel({ presets: ['@babel/preset-env'] }))
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
function buildZip() {
    const version = require('./package.json').version;
    return gulp.src([
        // package.json 必须随包发布：tools/ 里的 release.js / sync-version.js 依赖它读取 version。
        '**/*', '!node_modules/**', '!package-lock.json',
        '!gulpfile.js', '!.git/**', '!.git*',
        '!cache/**', '!*.log', '!*.zip', '!*.tmp',
        '!.workbuddy/**', '!.trae/**',
        '!.DS_Store', '!Thumbs.db',
        // w.org 变体构建产物、本仓库打包产物、临时脚本：都不是主题运行期文件（曾因漏排除让包体凭空翻倍）
        '!dist-wporg/**', '!release/**', '!tmp-*', '!tmp/**',
        // 自托管更新服务的部署配置：.gitignore 已排除（非主题源），主题自身也不读它
        // （主题更新由配套插件请求远端 update.qicaiyun.top/jinyu-update.json 完成，与本文件无关）
        '!jinyu-update.json',
        // FontAwesome 子集化中间产物（.gitignore 已列，tools/fa-collect.py 可重新生成）
        '!assets/fonts/fa/icons.all.raw.txt', '!assets/fonts/fa/icons.raw.txt', '!assets/fonts/fa/icons.map.json',
        // —— 以下三项 copyWporg 已排除，自托管版曾漏掉，白带 1.5MB 死副本 ——
        // 未子集的完整 FA 样式（102KB）：运行期只加载 subset.min.css，全仓库无引用
        '!assets/fonts/fa/all.min.css',
        // 死副本：assets/dist/img 与 assets/img 内容逐字节相同，而代码只走 assets/img/
        // （post-meta.php 的类目封面用 get_theme_file_uri('assets/img/cat-cover/…')）
        '!assets/dist/img/**',
        // 死副本：编译后 CSS 里的 url(../fonts/jinyu-text/…) 相对 assets/dist/style/ 解析，
        // 落在 assets/dist/fonts/；assets/fonts/jinyu-text/ 这份无任何引用
        '!assets/fonts/jinyu-text/**'
    ], { base: '.' })
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
const WPORG_DESC = '金玉（Jinyu）纯呈现层发行版：高颜值自适应博客主题，支持暗色模式、多种布局、短代码、点赞收藏、评论互动与无限加载；外观选项统一在「外观 → 自定义」中配置。';
const WPORG_DIR = `dist-wporg/${WPORG_SLUG}`;

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
		// w.org 发行包只保留运行期必需文件：jinyu-update.json 指向自托管更新服务
		// （审核方会读成「主题自带外部更新通道」），package.json / tools / tests / README.md
		// 只服务于本仓库与工具链，都不该出现在提交包内。
		'!jinyu-update.json', '!package.json', '!README.md',
		'!tools/**', '!tests/**',
		// 临时文件兜底：调试期的 tmp-* 产物若落在项目根，绝不能让它们混进提交包（已实际踩过）
		'!tmp-*', '!tmp/**',
		// —— 以下为「.gitignore 已排除 = 非主题源」的构建中间产物与死副本，一律不进提交包 ——
		// FontAwesome 子集化中间产物：.gitignore 已列，tools/fa-collect.py 可随时重新生成
		'!assets/fonts/fa/icons.all.raw.txt', '!assets/fonts/fa/icons.raw.txt', '!assets/fonts/fa/icons.map.json',
		// 未子集的完整 FA 样式（102KB）：运行期只加载 subset.min.css，全量版全仓库无引用
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
		// —— 以下为「开发源文件」：运行期只加载编译产物，绝不该进提交包（用户要求：不要打包不相干的）——
		// .less 源（assets/dist/style/*.min.css 才是运行期样式，WP.org 不读 less）
		'!assets/style/**/*.less',
		// 顶层原始 JS（buildJs/buildAdminJs 已编译为 assets/dist/js/*.min.js，运行期只认 .min 版）
		'!assets/js/*.js',
		// 原始 vendor（保留对应 *.min.js：functions.php enqueue 的是 .min 版）
		'!assets/js/vendor/highlight.js', '!assets/js/vendor/qrcode.js', '!assets/js/vendor/viewer.js',
		// WP.org 只需一份许可证：保留 license.txt，删去大写的 LICENSE 副本（内容相同，纯冗余）
		'!LICENSE',
		// 仓库本地恢复说明（中文名 `_先读我-恢复说明.md`）：纯开发/运维内部文档，
		// 非主题运行期文件，且非 ASCII 文件名在部分平台不通用（WP.org 上传会拒）。不应进提交包。
		'!_先读我-恢复说明.md'
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
			s = s.replace(/^Description: .*$/m, `Description: ${WPORG_DESC}`);
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

function zipWporg() {
	const version = require('./package.json').version;
	return gulp.src(`${WPORG_DIR}/**/*`, { base: 'dist-wporg' })
		.pipe(zip(`${WPORG_SLUG}-${version}.zip`))
		.pipe(gulp.dest('release'));
}

const buildWporg = gulp.series(versionCheck, copyWporg, flagWporg, renameWporg, zipWporg);

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



