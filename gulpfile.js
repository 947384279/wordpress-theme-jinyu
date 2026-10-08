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
        // —— 以下三项 copyWporg 已排除，自托管版曾漏掉，白带 1.5MB 死副本 ——
        // 未子集的完整 FA 样式（102KB）：运行期只加载 subset.min.css，全仓库无引用
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
		// 本仓库与工具链专属文件不该出现在提交包内：package.json / tools / tests / README.md。
		'!package.json', '!README.md',
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

async function zipWporg() {
	const version = require('./package.json').version;
	// 产物目录自检（严格模式）：顶层必须已是单一 <slug>/ 目录，这里下钻检查
	await precheckGate(path.join(__dirname, 'dist-wporg'), WPORG_SLUG);
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



