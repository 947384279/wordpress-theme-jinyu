const gulp = require('gulp');
const less = require('gulp-less');
const cleanCSS = require('gulp-clean-css');
const babel = require('gulp-babel');
const uglify = require('gulp-uglify');
const rename = require('gulp-rename');
const zip = require('gulp-zip');

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
    gulp.watch('assets/style/*.less', gulp.series(buildStyle, buildAdminStyle, buildCritical));
    gulp.watch('assets/js/*.js', gulp.series(buildJs, buildAdminJs));
}

function buildZip() {
    return gulp.src([
        '**/*', '!node_modules/**', '!package-lock.json',
        '!gulpfile.js', '!package.json', '!.git/**',
        '!cache/**', '!*.log',
        '!.workbuddy/**', '!.trae/**'
    ]).pipe(zip('jinyu.zip')).pipe(gulp.dest('..'));
}

const dev = gulp.series(gulp.parallel(buildStyle, buildAdminStyle, buildJs, buildAdminJs, buildCritical), watch);
const build = gulp.parallel(buildStyle, buildAdminStyle, buildJs, buildAdminJs, buildCritical);

exports.default = build;
exports.dev = dev;
exports.build = build;
exports.zip = buildZip;



