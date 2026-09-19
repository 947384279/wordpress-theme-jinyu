<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 金玉主题 - 主入口
 */
define('JINYU_ABS_DIR', get_template_directory());
define('JINYU_ABS_URI', get_template_directory_uri());
define('JINYU_CUR_VER', wp_get_theme()->get('Version'));
// 主题更新服务器（独立更新主机 update.qicaiyun.top 的 web 根；可在 wp-config.php 中提前 define 覆盖）。
if (!defined('JINYU_UPDATE_SERVER')) {
    define('JINYU_UPDATE_SERVER', 'https://update.qicaiyun.top/jinyu-update.json');
}
const JINYU     = 'jinyu';
const JINYU_OPT = 'jinyu_options';

if (version_compare(PHP_VERSION, '8.0', '<')) {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p>' . sprintf(__('金玉主题要求 PHP 8.0+，当前版本 %s', JINYU), PHP_VERSION) . '</p></div>';
    });
    return;
}

require_once JINYU_ABS_DIR . '/inc/fun/crypto.php';
require_once JINYU_ABS_DIR . '/inc/fun/core.php';
require_once JINYU_ABS_DIR . '/inc/fun/series.php';
require_once JINYU_ABS_DIR . '/inc/fun/maintenance.php';
require_once JINYU_ABS_DIR . '/inc/fun/tracking.php';     // 销售与变现追踪后端（事件/订阅/来源表）
require_once JINYU_ABS_DIR . '/inc/fun/update.php';       // 主题更新通道（接入 WP 原生主题更新）
require_once JINYU_ABS_DIR . '/inc/fun/reporting.php';   // 上报/遥测客户端（更新主机：反馈/激活/盗版监测）
require_once JINYU_ABS_DIR . '/inc/fun/sales-front.php';  // 销售与变现（前端触点/订阅/广告角标）
if (is_admin()) require_once JINYU_ABS_DIR . '/inc/setting/index.php';

add_action('admin_enqueue_scripts', function ($hook) {
    if (strpos($hook, 'jinyu-options') === false) return;
    wp_enqueue_media(); // 设置页「上传/选择」字段依赖 wp.media，缺则点击无反应
    wp_enqueue_style('jinyu-admin', JINYU_ABS_URI . '/assets/dist/style/admin.min.css', [], filemtime(JINYU_ABS_DIR . '/assets/dist/style/admin.min.css'));
    wp_enqueue_script('jinyu-admin', JINYU_ABS_URI . '/assets/dist/js/admin.min.js', ['jquery'], filemtime(JINYU_ABS_DIR . '/assets/dist/js/admin.min.js'), true);
});

add_action('after_setup_theme', function () {
    // 加载主题翻译文件（/languages），让 __()/_e() 等可被翻译
    load_theme_textdomain(JINYU, get_template_directory() . '/languages');

    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    // WP 7.0 已废弃并移除 HTML5 的 'style'/'script' 主题支持(Trac #64442)，声明会触发 _doing_it_wrong。
    // 现代 WP 默认即 HTML5 输出，故此处只保留内容相关特性。
    add_theme_support('html5', ['search-form','comment-form','comment-list','gallery','caption']);
    add_theme_support('automatic-feed-links');
    add_theme_support('custom-logo', ['height'=>60,'width'=>200,'flex-height'=>true,'flex-width'=>true]);
    add_theme_support('custom-background');
    add_theme_support('custom-header', ['height'=>120,'width'=>1920,'flex-height'=>true,'flex-width'=>true]);
    register_nav_menus([
        'primary' => __('主导航菜单', JINYU),
        'footer'  => __('页脚菜单', JINYU),
    ]);

    // 古登堡区块支持
    add_theme_support('wp-block-styles');
    add_theme_support('align-wide');
    add_theme_support('editor-styles');
    add_editor_style('assets/dist/style/style.min.css');

    // 响应式嵌入：让视频/iframe 在移动端按比例自适应（包裹 .wp-has-aspect-ratio）
    add_theme_support('responsive-embeds');

    // 默认禁用 WordPress 5.8+ 的区块小工具编辑器，恢复经典小工具，
    // 让主题注册的 WP_Widget（金玉·作者卡/热门文章/标签云等）在后台可见可拖拽。
    // 后台「全局设置 › 使用区块小工具」开启后改为保留区块小工具编辑器。
    if (!jinyu_is_checked('use_widgets_block')) {
        remove_theme_support('widgets-block-editor');
    }
});

add_filter('body_class', function (array $classes): array {
    $pos = jinyu_get_option('sidebar_pos', 'right');
    if ($pos === 'left') $classes[] = 'jinyu-sidebar-left';
    if ($pos === 'none') $classes[] = 'jinyu-no-sidebar';

    // 文章列表风格（card/list/big/cms）
    $mode = jinyu_get_option('post_style', 'card');
    if (!in_array($mode, ['card', 'list', 'big', 'cms'], true)) $mode = 'card';
    $classes[] = 'jinyu-layout-' . $mode;

    // 中文排版优化
    if (jinyu_is_checked('cn_typography')) $classes[] = 'jinyu-cn';

    // 导航栏毛玻璃：默认开启，后台「全局设置 › 导航栏毛玻璃效果」关闭时摘掉 backdrop-filter
    if (!jinyu_is_checked('nav_blur')) $classes[] = 'jinyu-no-nav-blur';

    return $classes;
});

// 小工具区域统一在 inc/fun/sidebar.php 注册。此处禁止再 register_sidebar 同一 id：
// 后注册会整体覆盖先注册项（description / before_widget 的 %1$s、%2$s 全丢），且结果取决于文件加载顺序。

// 维护模式（非管理员访问前台返回 503 维护页）
add_action('template_redirect', 'jinyu_maintenance_mode', 1);

// 首页主查询调整（仅前台主查询）
add_action('pre_get_posts', function ($q) {
    if (is_admin() || !$q->is_main_query()) return;
    if (!(is_home() || is_front_page())) return;

    // CMS「最新文章」排除指定分类
    $ex = array_filter(array_map('intval', explode(',', (string) jinyu_get_option('cms_new_exclude_cats', ''))));
    if ($ex) $q->set('category__not_in', $ex);

    // 首屏 Banner 已单独展示置顶首篇，主循环里再排一次就是同一篇文章出现两遍。
    // 仅首页第一页排除：banner 只在第一页渲染，后续页不该少一篇。
    // ⚠️ 判定必须复用 jinyu_home_banner_post()，与 index.php 保持同一份逻辑。
    if (!$q->is_paged) {
        $banner = jinyu_home_banner_post();
        if ($banner) {
            // 读出现有值再合并，避免覆盖其它钩子已设的 post__not_in
            $not_in = array_filter(array_map('intval', (array) $q->get('post__not_in')));
            $not_in[] = (int) $banner->ID;
            $q->set('post__not_in', array_values(array_unique($not_in)));
        }
    }
});

add_action('wp_enqueue_scripts', function () {
    // 用文件修改时间做版本号：文件内容一变，版本号就变，浏览器必定重新拉取，杜绝缓存到旧/失败的 CSS/JS
    $css_path = JINYU_ABS_DIR . '/assets/dist/style/style.min.css';
    $js_path  = JINYU_ABS_DIR . '/assets/dist/js/jinyu.min.js';
    $css_ver  = file_exists($css_path) ? filemtime($css_path) : JINYU_CUR_VER;
    $js_ver   = file_exists($js_path)  ? filemtime($js_path)  : JINYU_CUR_VER;

    wp_enqueue_style('jinyu-font-awesome', JINYU_ABS_URI . '/assets/fonts/fa/all.min.css', [], '6.5.1');
    wp_enqueue_style('jinyu-style', JINYU_ABS_URI . '/assets/dist/style/style.min.css', ['jinyu-font-awesome'], $css_ver);

    $deps = [];
    if (is_singular()) {
        if (jinyu_is_checked('qr_enable')) {
            wp_enqueue_script('jinyu-qrcode', JINYU_ABS_URI . '/assets/js/vendor/qrcode.min.js', [], $css_ver, true);
            $deps[] = 'jinyu-qrcode';
        }

        // 代码高亮 highlight.js（明暗双主题，跟随 html[data-theme] 自动切换）
        if (jinyu_is_checked('code_highlight_enable')) {
            wp_enqueue_style('jinyu-highlight-css', JINYU_ABS_URI . '/assets/css/vendor/highlight.min.css', [], $css_ver);
            wp_enqueue_script('jinyu-highlight', JINYU_ABS_URI . '/assets/js/vendor/highlight.min.js', [], $css_ver, true);
            $deps[] = 'jinyu-highlight';
        }

        // 文章图片灯箱 Viewer.js
        wp_enqueue_style('jinyu-viewer-css', JINYU_ABS_URI . '/assets/css/vendor/viewer.min.css', [], $css_ver);
        wp_enqueue_script('jinyu-viewer', JINYU_ABS_URI . '/assets/js/vendor/viewer.min.js', [], $css_ver, true);
        $deps[] = 'jinyu-viewer';
    }

    // 首页轮播 Swiper
    if (is_home() && jinyu_is_checked('home_carousel')) {
        wp_enqueue_style('jinyu-swiper-css', JINYU_ABS_URI . '/assets/css/vendor/swiper-bundle.min.css', [], $css_ver);
        wp_enqueue_script('jinyu-swiper', JINYU_ABS_URI . '/assets/js/vendor/swiper-bundle.min.js', [], $css_ver, true);
        $deps[] = 'jinyu-swiper';
    }

    // AI 对话模板：单独加载交互脚本，依赖 jinyu-main 注入的 JINYU_CONFIG
    if (is_page_template('pages/template-ai.php')) {
        $ai_js = JINYU_ABS_DIR . '/assets/dist/js/ai-chat.min.js';
        wp_enqueue_script(
            'jinyu-ai-chat',
            JINYU_ABS_URI . '/assets/dist/js/ai-chat.min.js',
            ['jinyu-main'],
            file_exists($ai_js) ? filemtime($ai_js) : JINYU_CUR_VER,
            true
        );
    }

    wp_enqueue_script('jinyu-main', JINYU_ABS_URI . '/assets/dist/js/jinyu.min.js', $deps, $js_ver, true);
    wp_localize_script('jinyu-main', 'JINYU_CONFIG', [
        'ajax_url'            => admin_url('admin-ajax.php'),
        'site_url'            => home_url(),
        'site_name'           => get_bloginfo('name'),
        // nonce 改为占位：页面加载后由前端 JS 从 jinyu_nonce 接口懒拉取（见 inc/fun/nonce.php）。
        // 这样无论 HTML 被主题缓存 / 第三方缓存 / CDN 固化多久，前端 nonce 永远新鲜，
        // 避免点赞 / 评论 / AI 对话 / 登录等 AJAX 因过期 nonce 被 check_ajax_referer 打回 -1。
        'nonce'               => '',
        'nonce_endpoint'      => esc_url_raw(admin_url('admin-ajax.php?action=jinyu_nonce')),
        'logged_in'           => is_user_logged_in(),
        'post_id'             => is_singular() ? (int) get_the_ID() : 0,
        'liked_posts'         => is_user_logged_in()
            ? jinyu_meta_ids(get_current_user_id(), 'jinyu_liked_posts')
            : [],
        'fav_posts'           => jinyu_get_user_favs(),
        'enable_ajax_comment' => jinyu_is_checked('enable_ajax_comment'),
        'load_more_infinite'  => jinyu_is_checked('blog_show_load_more') && jinyu_is_checked('load_more_infinite'),
        'pjax'                => jinyu_is_checked('enable_pjax'),
        'home_carousel'       => is_home() && jinyu_is_checked('home_carousel'),
        'grey'                => jinyu_is_checked('grey'),
        // 内容增强开关（对应「内容增强」设置分组）
        'toc_enable'          => jinyu_is_checked('toc_enable'),
        'toc_depth'           => (int) jinyu_get_option('toc_depth', 3),
        'breadcrumb_enable'   => jinyu_is_checked('breadcrumb_enable'),
        'back_top_enable'     => jinyu_is_checked('back_top_enable'),
        'code_highlight_enable' => jinyu_is_checked('code_highlight_enable'),
        'read_progress_enable' => jinyu_is_checked('read_progress_enable'),
        'show_cover'          => jinyu_is_checked('show_cover'),
        'related_enable'      => jinyu_is_checked('related_enable'),
        'post_nav_enable'     => jinyu_is_checked('post_nav_enable'),
        'like_enable'         => jinyu_is_checked('like_enable'),
        'fav_enable'          => jinyu_is_checked('fav_enable'),
        'share_enable'        => jinyu_is_checked('share_enable'),
        'poster_enable'       => jinyu_is_checked('poster_enable'),
        'qr_enable'           => jinyu_is_checked('qr_enable'),
        // 主题模式：前端 JS 仅识别 light/dark/auto。'switch' 是升级前残留的旧值，映射为 auto（仍可切换）。
        'theme_mode'          => jinyu_get_option('theme_mode', 'auto') === 'switch' ? 'auto' : jinyu_get_option('theme_mode', 'auto'),
        // 分享渠道（逗号分隔，空=全部）
        'share_channels'      => jinyu_get_option('share_channels', ''),
        'favicon_badge'      => current_user_can('manage_options'),
        'reward_title'        => jinyu_get_option('reward_title', __('赞赏作者', JINYU)),
        'reward_text'         => jinyu_get_option('reward_text', ''),
    ]);
    if (is_singular() && comments_open() && get_option('thread_comments')) {
        wp_enqueue_script('comment-reply');
    }
});

/* ======================================================================
   样式加载策略
   ----------------------------------------------------------------------
   原方案把主样式(jinyu-style)与 Font Awesome 改为 media='print' 异步加载
   以降低 FCP，但首屏关键 CSS 内联(critical.min.css)仅覆盖布局骨架，未能
   覆盖全部首屏样式，导致强制刷新时先渲染约 1 秒无样式页面(FOUC)。现恢复
   WP 默认阻塞加载(rel=stylesheet media='all')，样式在首屏绘制前就绪，
   彻底消除闪烁；主 CSS 经 CDN(header.php 已 preconnect)加载，速度充足。
   首屏关键 CSS 内联保留为渐进增强，不影响正确性。
   ====================================================================== */

/* ======================================================================
   性能优化：精简 HTTP 头与无用脚本（减请求数 / 减 DOM 开销 / 降 FCP）
   ====================================================================== */

// 1) 禁用 WordPress 表情符号转换：中文站用不到，且会向 <head> 注入
//    wp-emoji-release.min.js + 内联检测脚本 + emoji 样式，属纯额外开销。
if (!is_admin()) {
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_filter('the_content_feed', 'wp_staticize_emoji');
    remove_filter('comment_text_rss', 'wp_staticize_emoji');
    remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
}

// 2) 移除前端无用的 <link> 发现标签：减小 HTML 体积、减少无谓请求。
//    wlwmanifest/rsd 为 XML-RPC / Windows Live Writer 发现，现代站点不需要；
//    wp_generator 暴露 WP 版本；wp_shortlink_wp_head 的短链接多数场景无意义。
add_action('init', function () {
    remove_action('wp_head', 'wlwmanifest_link');
    remove_action('wp_head', 'rsd_link');
    remove_action('wp_head', 'wp_generator');
    remove_action('wp_head', 'wp_shortlink_wp_head', 10);
}, 99);

add_action('admin_notices', function () {
    $missing = [];
    foreach (['gd', 'mbstring'] as $ext) {
        if (!extension_loaded($ext)) $missing[] = "<code>{$ext}</code>";
    }
    if (!empty($missing)) {
        printf(
            '<div class="notice notice-warning"><p>%s</p></div>',
            /* translators: %s: 缺失的 PHP 扩展名列表 */
            sprintf(esc_html__('金玉主题缺少 PHP 扩展：%s', JINYU), implode(', ', $missing))
        );
    }
});

add_action('after_switch_theme', function () {
    update_option('jinyu_theme_activated', date('Y-m-d H:i:s'));
    if (function_exists('jinyu_stats_install')) jinyu_stats_install();
});

/* ======================================================================
   站点级设置：统计代码 / 摘要字数 / 正文字号 / 评论分页
   ====================================================================== */

// 统计代码（百度/GA 等，输出到 </head> 前）
add_action('wp_head', function () {
    $code = jinyu_get_option('analytics_code', '');
    if ($code) echo "\n" . $code . "\n";
}, 99);

/* ======================================================================
   自定义代码注入（「扩展与开发 › 自定义代码」面板）
   仅前台输出；code 字段为管理员自填的 CSS/JS，原样输出不转义（与 analytics_code 一致）。
   ====================================================================== */
add_action('wp_head', function () {
    $css = trim((string) jinyu_get_option('css_code_head', ''));
    $js  = trim((string) jinyu_get_option('js_code_head', ''));
    if ($css) echo "\n<style id=\"jinyu-custom-head-css\">\n" . $css . "\n</style>\n";
    if ($js)  echo "\n" . $js . "\n";
}, 100);

add_action('wp_footer', function () {
    $css = trim((string) jinyu_get_option('css_code_foot', ''));
    $js  = trim((string) jinyu_get_option('js_code_foot', ''));
    if ($css) echo "\n<style id=\"jinyu-custom-foot-css\">\n" . $css . "\n</style>\n";
    if ($js)  echo "\n" . $js . "\n";
}, 99);

// 摘要字数
add_filter('excerpt_length', function ($len) {
    return (int) jinyu_get_option('excerpt_length', 120);
});

// 正文字号 / 字体：以 CSS 变量注入，markdown.less 用 var(--content-font-size, @fs-base) 引用
add_action('wp_head', function () {
    $css = '';
    $fs = (int) jinyu_get_option('content_font_size', 16);
    if ($fs >= 13 && $fs <= 20 && $fs !== 16) {
        $css .= '--content-font-size:' . $fs . 'px;';
    }
    $font = jinyu_get_option('content_font', 'system');
    $stacks = [
        'system' => "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'PingFang SC','Microsoft YaHei',sans-serif",
        'serif'  => "Georgia,'Times New Roman','Songti SC','SimSun',serif",
        'mono'   => "'SFMono-Regular',Consolas,'Courier New',monospace",
        'round'  => "'YouYuan','Yuanti SC','Microsoft YaHei',sans-serif",
    ];
    if (isset($stacks[$font]) && $font !== 'system') {
        $css .= '--content-font-family:' . $stacks[$font] . ';';
    }
    if ($css) {
        echo '<style id="jinyu-content-font">:root{' . $css . '}</style>';
    }
}, 2);

// 首屏关键 CSS 内联（主题内置，无需后台开关）
add_action('wp_head', function () {
    $file = JINYU_ABS_DIR . '/assets/dist/style/critical.min.css';
    if (is_file($file)) {
        echo '<style id="jinyu-critical-css">' . file_get_contents($file) . '</style>';
    }
}, 1);

// Cookie 合规提示条
add_action('wp_footer', function () {
    if (!jinyu_is_checked('cookie_consent')) return;
    if (!empty($_COOKIE['jinyu_cookie_ok'])) return;
    $text = trim((string) jinyu_get_option('cookie_consent_text', ''));
    if (!$text) {
        $text = __('本站点使用 Cookie 以提升浏览体验，继续浏览即表示您同意我们的隐私政策。', JINYU);
    }
    echo '<div class="jinyu-cookie-bar" id="jinyu-cookie-bar" role="dialog" aria-label="' . esc_attr__('Cookie 提示', JINYU) . '">' .
        '<span class="jinyu-cookie-text">' . esc_html($text) . '</span>' .
        '<button type="button" class="jinyu-cookie-ok" id="jinyu-cookie-ok">' . esc_html__('同意', JINYU) . '</button>' .
        '</div>';
    // 点击「同意」：写入 cookie 并移除提示条。脚本紧贴提示条 HTML 之后输出，
    // 保证元素已存在于 DOM 时再绑定（避免依赖 jinyu-main 内联脚本的时序问题）。
    ?>
    <script>
    (function () {
        var btn = document.getElementById('jinyu-cookie-ok');
        if (!btn) return;
        btn.addEventListener('click', function () {
            try { document.cookie = 'jinyu_cookie_ok=1;path=/;max-age=' + (30 * 86400) + ';samesite=lax'; } catch (e) {}
            var bar = document.getElementById('jinyu-cookie-bar');
            if (bar) bar.remove();
        });
    })();
    </script>
    <?php
}, 99);

// 登录页品牌化（自定义 Logo / 背景）
add_action('login_head', function () {
    $logo = jinyu_get_option('login_logo', '');
    $bg   = jinyu_get_option('login_bg', '');
    if ($logo || $bg) {
        echo '<style id="jinyu-login-brand">';
        if ($logo) {
            echo '#login h1 a{background-image:url(' . esc_url($logo) . ');background-size:contain;background-position:center;width:100%;height:80px;}';
        }
        if ($bg) {
            echo 'body.login{background-image:url(' . esc_url($bg) . ');background-size:cover;background-position:center;}';
        }
        echo '</style>';
    }
});

// 评论分页
if (jinyu_is_checked('comment_pages_enable')) {
    add_filter('option_page_comments', '__return_true');
    add_filter('option_comments_per_page', function ($v) {
        return (int) jinyu_get_option('comments_per_page', 10);
    });
}
