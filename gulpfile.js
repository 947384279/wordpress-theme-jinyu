const gulp = require('gulp');
const less = require('gulp-less');
const cleanCSS = require('gulp-clean-css');
const babel = require('gulp-babel');
const uglify = require('gulp-uglify');
const rename = require('gulp-rename');
const zip = require('gulp-zip');
const { spawnSync } = require('child_process');
const path = require('path');

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
        '!gulpfile.js', '!.git/**',
        '!cache/**', '!*.log',
        '!.workbuddy/**', '!.trae/**'
    ], { base: '.' })
        .pipe(rename((p) => { p.dirname = path.posix.join('jinyu', p.dirname || ''); }))
        .pipe(zip(`jinyu-theme-${version}.zip`))
        .pipe(gulp.dest('..'));
}

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



