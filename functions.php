<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 金玉主题 - 主入口
 */
define('JINYU_ABS_DIR', get_template_directory());
define('JINYU_ABS_URI', get_template_directory_uri());
if (!defined('JINYU_CUR_VER')) {
	define('JINYU_CUR_VER', wp_get_theme()->get('Version'));
}
const JINYU_OPT = 'jinyu_options';

/**
 * 垃圾评论关键词内置库（出厂默认，英文逗号分隔）。
 * 作为后台「垃圾评论关键词」文本框的默认值与运行期回退值的单一事实来源。
 * 匹配为大小写不敏感子串命中（stripos），故词条尽量用“有区分度的短语”，
 * 避免单字/极短词误伤正常评论（例如用「加微信」而非「微信」）。
 */
$jinyu_builtin_spam = [
	// 博彩 / 赌博
	'彩票', '博彩', '赌博', '赌球', '赌场', '外围赌博', '私彩', '时时彩', '六合彩', '百家乐', '老虎机', '押注', '盘口', '投注', '网投', '葡京', '威尼斯人', '永利', '开元棋牌', '棋牌游戏', '德州扑克', '抢庄牛牛', '龙虎斗', '彩票预测', '博彩公司', '賭博', '賭場', '賭球',
	// 色情 / 成人
	'色情', '性爱', '成人', '裸聊', '约炮', '一夜情', '小姐', '按摩会所', '上门服务', '做爱', '黄色网站', '春药', '伟哥', '壮阳', '同城交友', '外围女', '福利姬', '援交', '成人电影', '成人小说', '激情聊天', '性感少妇', '包养',
	// 诈骗 / 杀猪盘 / 网赚
	'诈骗', '杀猪盘', '刷单', '刷信誉', '刷销量', '返利', '兼职刷单', '日赚', '月入过万', '稳赚不赔', '高收益', '投资理财', '荐股', '内幕消息', '割韭菜', '资金盘', '庞氏骗局', '互助盘', '跑路', '黑平台', '解冻民族资产', '精准扶贫骗局', '充值返现', '博彩骗局', '冒充客服', '公检法诈骗', '刷单返佣',
	// 贷款 / 金融
	'贷款', '放贷', '小额贷款', '网贷', '黑户贷款', '白户贷款', '套现', '信用卡套现', '花呗套现', '借呗', '提额', '征信修复', '黑征信', '秒到账', '下款', '口子', '714高炮', '套路贷', '空放', '无视黑白户', '貸款',
	// 发票 / 证件
	'发票', '代开发票', '办证', '刻章', '假证', '毕业证', '学位证', '资格证代办', '办银行卡', '对公账户', '手机卡实名', '实名卡', '發票', '辦證',
	// 代写 / 灰产
	'代写', '代写论文', '代孕', '代考', '枪手', '论文代写', '代做', '刷量', '刷粉', '刷赞', '刷屏', '地推', '接码', '养号', '引流工作室', '刷播放量', '刷阅读量', '代寫',
	// 微商 / 广告
	'加微信', '微信号', '微信同号', '加我微信', '加V', '扫一扫加', '二维码名片', '招代理', '代理加盟', '一件代发', '微商', '代购', '厂家直销', '免费送', '扫码关注', '关注公众号', '转发朋友圈', '集赞', '砍价', '砍一刀', '拼团', '助力', '点赞有礼', '免费领取', '复制打开',
	// 兼职 / 网赚
	'兼职', '网赚', '在家赚钱', '手机赚钱', '宝妈副业', '副业', '日结', '佣金', '淘客', '返佣', '拉人头', '多级分销', '传销', '直销', '创业项目',
	// 虚拟币 / 区块链
	'比特币', '虚拟币', '数字货币', '区块链', '挖矿', '矿机', 'ico', '币圈', '代币', '空气币', '钱包', 'usdt', '泰达币',
	// 灰产医疗 / 药品
	'减肥产品', '丰胸', '美白祛斑', '治百病', '老中医', '偏方', '根治', '包治', '抗癌神药', '进口药代购', '违禁药', '迷药', '听话水', '特效药',
	// 仿牌 / 私服 / 外挂
	'高仿', '精仿', 'A货', '原单', '私服', '游戏外挂', '辅助脚本', '破解版', '激活码', '注册机', '刷钻', '刷会员', '代充',
	// 引流 / 加群
	'加QQ群', '加群', '进群', '扣扣', '企鹅号', '快手引流', '抖音引流', '小红书引流', '涨粉', '代运营', '刷播放', '刷阅读', '加好友',
	// 通用垃圾话术
	'限时特惠', '名额有限', '内部名额', '速联系', '点击链接', '私聊', '一对一指导', '稳赚', '日结兼职',
	// 英文垃圾词（大小写不敏感子串命中）
	'casino', 'viagra', 'cialis', 'levitra', 'porn', 'xxx', 'sex', 'nude', 'free money', 'make money', 'earn money', 'work from home', 'click here', 'buy now', 'cheap', 'discount', 'order now', 'bitcoin', 'forex', 'trading signals', 'weight loss', 'hot girls', 'follow me', 'payday loan', 'online pharmacy', 'replica', 'rolex', 'wholesale', 'factory price', 'free shipping', 'limited offer', 'act now', 'click below', 'visit my', 'congratulations you', 'best price', 'pharmacy',
];
define('JINYU_DEFAULT_SPAM_WORDS', implode(',', $jinyu_builtin_spam));
unset($jinyu_builtin_spam);

if (version_compare(PHP_VERSION, '8.0', '<')) {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p>' . sprintf(__('金玉主题要求 PHP 8.0+，当前版本 %s', 'jinyu'), PHP_VERSION) . '</p></div>';
    });
    return;
}

require_once JINYU_ABS_DIR . '/inc/fun/crypto.php';
require_once JINYU_ABS_DIR . '/inc/fun/core.php';
require_once JINYU_ABS_DIR . '/inc/fun/patterns.php';
require_once JINYU_ABS_DIR . '/inc/fun/maintenance.php';
// 性能优化中心已迁至配套插件 jinyu-theme-companion（perf-center.php）。
// 主题仅在呈现层按需读取开关值（HTML 压缩 / 评论懒加载），插件缺席时按 $default 降级。
function jinyu_perf_opt( string $key, bool $default = false ): bool {
	// 唯一真源：配套插件 jinyu-theme-companion 的 jyc_perf_options；
	// 兼容主题时代旧键 jinyu_perf_options（插件接管前保存过的历史配置）。
	// 同一次请求内只读取一次（静态缓存），避免被 wp_enqueue_scripts / template_redirect
	// 等多处高频调用时反复 get_option + 冗长的降级查询。
	static $opts_cache = null;
	if ( $opts_cache === null ) {
		$opts_cache = get_option( 'jyc_perf_options', get_option( 'jinyu_perf_options', [] ) );
	}
	$opts = $opts_cache;
	if ( ! is_array( $opts ) || ! array_key_exists( $key, $opts ) ) {
		return $default;
	}
	return ! empty( $opts[ $key ] );
}
if (is_admin()) require_once JINYU_ABS_DIR . '/inc/setting/index.php';

add_action('admin_enqueue_scripts', function ($hook) {
    if (strpos($hook, 'jinyu-options') === false) return;
    wp_enqueue_media(); // 设置页「上传/选择」字段依赖 wp.media，缺则点击无反应
    wp_enqueue_style('jinyu-admin', JINYU_ABS_URI . '/assets/dist/style/admin.min.css', [], filemtime(JINYU_ABS_DIR . '/assets/dist/style/admin.min.css'));
    wp_enqueue_script('jinyu-admin', JINYU_ABS_URI . '/assets/dist/js/admin.min.js', ['jquery'], filemtime(JINYU_ABS_DIR . '/assets/dist/js/admin.min.js'), true);
});

add_action('after_setup_theme', function () {
    // 加载主题翻译文件（/languages），让 __()/_e() 等可被翻译
    load_theme_textdomain('jinyu', get_template_directory() . '/languages');

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
        'primary' => __('主导航菜单', 'jinyu'),
        'footer'  => __('页脚菜单', 'jinyu'),
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

    // 中文排版优化（已内置常开：两端对齐、标点挤压、舒适行距等中文排版增强）
    $classes[] = 'jinyu-cn';

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
    $ex = array_filter(array_map('intval', explode(',', (string) jinyu_get_option('home_exclude_cats', ''))));
    if ($ex) $q->set('category__not_in', $ex);

    // 置顶文章统一进网格（.is-sticky 卡片高亮），不再从主循环排除首篇。
});

add_action('wp_enqueue_scripts', function () {
    // 用文件修改时间做版本号：文件内容一变，版本号就变，浏览器必定重新拉取，杜绝缓存到旧/失败的 CSS/JS
    $css_path = JINYU_ABS_DIR . '/assets/dist/style/style.min.css';
    $js_path  = JINYU_ABS_DIR . '/assets/dist/js/jinyu.min.js';
    $css_ver  = file_exists($css_path) ? filemtime($css_path) : JINYU_CUR_VER;
    $js_ver   = file_exists($js_path)  ? filemtime($js_path)  : JINYU_CUR_VER;
    // 各 vendor 资源用各自文件的 filemtime 做版本号（而非主 CSS 的版本号），
    // 保证单个 vendor 文件改动时只刷新它自己，缓存破坏语义才正确。
    $ver = function ( $rel ) {
        $p = JINYU_ABS_DIR . '/assets/' . ltrim( $rel, '/' );
        return file_exists( $p ) ? filemtime( $p ) : JINYU_CUR_VER;
    };

    wp_enqueue_style('jinyu-font-awesome', JINYU_ABS_URI . '/assets/fonts/fa/all.min.css', [], '6.5.1');
    wp_enqueue_style('jinyu-style', JINYU_ABS_URI . '/assets/dist/style/style.min.css', ['jinyu-font-awesome'], $css_ver);

    $deps = [];
    if (is_singular()) {
        if (jinyu_is_checked('qr_enable')) {
            wp_enqueue_script('jinyu-qrcode', JINYU_ABS_URI . '/assets/js/vendor/qrcode.min.js', [], $ver('js/vendor/qrcode.min.js'), true);
            $deps[] = 'jinyu-qrcode';
        }

        // 代码高亮 highlight.js（明暗双主题，跟随 html[data-theme] 自动切换）
        if (jinyu_is_checked('code_highlight_enable')) {
            wp_enqueue_style('jinyu-highlight-css', JINYU_ABS_URI . '/assets/css/vendor/highlight.min.css', [], $ver('css/vendor/highlight.min.css'));
            wp_enqueue_script('jinyu-highlight', JINYU_ABS_URI . '/assets/js/vendor/highlight.min.js', [], $ver('js/vendor/highlight.min.js'), true);
            $deps[] = 'jinyu-highlight';
        }

        // 文章图片灯箱 Viewer.js
        wp_enqueue_style('jinyu-viewer-css', JINYU_ABS_URI . '/assets/css/vendor/viewer.min.css', [], $ver('css/vendor/viewer.min.css'));
        wp_enqueue_script('jinyu-viewer', JINYU_ABS_URI . '/assets/js/vendor/viewer.min.js', [], $ver('js/vendor/viewer.min.js'), true);
        $deps[] = 'jinyu-viewer';
    }

    // 首页轮播 Swiper
    if (is_home() && jinyu_is_checked('home_carousel')) {
        wp_enqueue_style('jinyu-swiper-css', JINYU_ABS_URI . '/assets/css/vendor/swiper-bundle.min.css', [], $ver('css/vendor/swiper-bundle.min.css'));
        wp_enqueue_script('jinyu-swiper', JINYU_ABS_URI . '/assets/js/vendor/swiper-bundle.min.js', [], $ver('js/vendor/swiper-bundle.min.js'), true);
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
        'theme_version'       => JINYU_CUR_VER,
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
        'reward_title'        => jinyu_get_option('reward_title', __('赞赏作者', 'jinyu')),
        'reward_text'         => jinyu_get_option('reward_text', ''),
    ]);
    // 前端可翻译文案字典：切语言后 JS 注入的 UI（阅读进度、加载中、评论提交等）随之翻译。
    // 与 .po 翻译体系解耦，零构建步骤即可生效；后续若用 wp i18n 生成 languages/jinyu-xx_XX-js.json，
    // wp_set_script_translations 会自动接管，无需改动此处。
    wp_localize_script('jinyu-main', 'JINYU_I18N', [
        'lessThanMinute' => __('不到 1 分钟', 'jinyu'),
        'aboutMinutes'   => __('约 %d 分钟', 'jinyu'),
        'loading'        => __('加载中…', 'jinyu'),
        'submitting'     => __('提交中…', 'jinyu'),
        'commentPosted'  => __('评论已提交', 'jinyu'),
        'submitFailed'   => __('提交失败，请稍后重试', 'jinyu'),
        'noMore'         => __('没有更多了', 'jinyu'),
        'noCover'        => __('暂无封面', 'jinyu'),
        'loadMore'       => __('加载更多', 'jinyu'),
        'copy'           => __('复制', 'jinyu'),
        'copied'         => __('已复制', 'jinyu'),
        'favorited'      => __('已收藏', 'jinyu'),
        'networkError'   => __('网络异常，请稍后重试', 'jinyu'),
        'processing'     => __('处理中…', 'jinyu'),
        'saveHint'       => __('点「保存图片」放大后长按图片，即可保存到相册', 'jinyu'),
        'flat'           => __('持平', 'jinyu'),
        'nearSamples'    => __('近 %d 次采样', 'jinyu'),
        'load'           => __('负载', 'jinyu'),
        'memory'         => __('内存占用', 'jinyu'),
        'sample'         => __('采样', 'jinyu'),
        'copyFailed'     => __('复制失败，请手动选择', 'jinyu'),
        'linkCopied'     => __('链接已复制', 'jinyu'),
        'opSuccess'      => __('操作成功', 'jinyu'),
        'noJumpPost'     => __('没有可跳转的文章', 'jinyu'),
        'opFailed'       => __('操作失败，请稍后再试', 'jinyu'),
        'thanksSupport'  => __('感谢支持 ♥', 'jinyu'),
        'thanksFeedback' => __('感谢你的反馈', 'jinyu'),
        'allRead'        => __('已全部标记已读', 'jinyu'),
        'scanToRead'     => __('长按 / 扫码阅读全文', 'jinyu'),
        'readDone'       => __('已读完 ✓', 'jinyu'),
        'readProgress'   => __('已读 %d% · 还需 %s', 'jinyu'),
    ]);
    // 若已生成 JS 翻译 JSON（wp i18n 提取 + 编译），自动加载；不存在则静默忽略。
    if ( function_exists( 'wp_set_script_translations' ) ) {
        wp_set_script_translations( 'jinyu-main', 'jinyu');
    }
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
    remove_action('wp_enqueue_scripts', 'print_emoji_styles'); // WP 6.4+ 表情样式改由此钩子输出，旧钩子已失效
    remove_filter('the_content_feed', 'wp_staticize_emoji');
    remove_filter('comment_text_rss', 'wp_staticize_emoji');
    remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
}

// 2) 移除前端无用的 <link> 发现标签：减小 HTML 体积、减少无谓请求。
//    wlwmanifest/rsd 为 XML-RPC / Windows Live Writer 发现，现代站点不需要；
//    wp_generator 暴露 WP 版本；wp_shortlink_wp_head 的短链接多数场景无意义。
add_action('init', function () {
    remove_action('wp_head', 'wlwmanifest_link');
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
            sprintf(esc_html__('金玉主题缺少 PHP 扩展：%s', 'jinyu'), implode(', ', $missing))
        );
    }
});

add_action('after_switch_theme', function () {
    update_option('jinyu_theme_activated', current_time('mysql'));
    if (function_exists('jinyu_stats_install')) jinyu_stats_install();
});

/* ======================================================================
   站点级设置：摘要字数 / 正文字号 / 评论分页
   ====================================================================== */

/* ======================================================================
   CSP（内容安全策略）nonce 支持
   - 为主题自身输出的内联 <style>/<script> 附加一次性 nonce，使站点可在
     不放开 'unsafe-inline' 的前提下启用严格 CSP。
   - WP 通过 wp_localize_script / wp_add_inline_script 输出的内联脚本，由
     wp_inline_script_attributes 过滤器统一附加 nonce（覆盖 JINYU_CONFIG）。
   - 默认不输出 CSP 头；需要严格 CSP 的用户用 jinyu_csp_policy 过滤器返回
     策略串（用 %NONCE% 占位，会自动替换为本请求的真实 nonce），例如：
       add_filter('jinyu_csp_policy', function () {
           return "default-src 'self';"
                . "script-src 'self' 'nonce-%NONCE%';"
                . "style-src 'self' 'nonce-%NONCE%';"
                . "img-src 'self' data: https:;font-src 'self';connect-src 'self'";
       });
   - 注意：用户自行填写的统计代码 / 自定义 JS（含完整 <script> 标签）不在此
     处自动加 nonce，启用严格 CSP 时需由用户自行处理其片段。
   ====================================================================== */
if ( ! function_exists( 'jinyu_get_csp_nonce' ) ) {
    function jinyu_get_csp_nonce() {
        static $nonce = null;
        if ( null === $nonce ) {
            // 32 位十六进制，符合 CSP nonce 规范且不包含需转义的保留字符
            $nonce = bin2hex( random_bytes( 16 ) );
        }
        return $nonce;
    }
}

if ( ! function_exists( 'jinyu_csp_nonce_attr' ) ) {
    function jinyu_csp_nonce_attr() {
        return ' nonce="' . esc_attr( jinyu_get_csp_nonce() ) . '"';
    }
}

// 为 WP 自行输出的内联脚本（JINYU_CONFIG 等）附加 nonce
add_filter( 'wp_inline_script_attributes', function ( $attributes ) {
    $attributes['nonce'] = jinyu_get_csp_nonce();
    return $attributes;
} );

// 可选：用户返回策略串时输出 CSP 头（默认不输出，零行为变更）
add_action( 'send_headers', function () {
    $policy = apply_filters( 'jinyu_csp_policy', '' );
    if ( ! is_string( $policy ) || '' === $policy ) {
        return;
    }
    header( 'Content-Security-Policy: ' . str_replace( '%NONCE%', jinyu_get_csp_nonce(), $policy ) );
} );

/* ======================================================================
   自定义代码注入（「扩展与开发 › 自定义代码」面板）
   仅前台输出；code 字段为管理员自填的 CSS/JS，原样输出不转义（与 analytics_code 一致）。
   ====================================================================== */
add_action('wp_head', function () {
    $css = trim((string) jinyu_get_option('css_code_head', ''));
    $js  = trim((string) jinyu_get_option('js_code_head', ''));
    if ($css) echo "\n<style id=\"jinyu-custom-head-css\"" . jinyu_csp_nonce_attr() . ">\n" . $css . "\n</style>\n";
    if ($js)  echo "\n" . $js . "\n";
}, 100);

add_action('wp_footer', function () {
    $css = trim((string) jinyu_get_option('css_code_foot', ''));
    $js  = trim((string) jinyu_get_option('js_code_foot', ''));
    if ($css) echo "\n<style id=\"jinyu-custom-foot-css\"" . jinyu_csp_nonce_attr() . ">\n" . $css . "\n</style>\n";
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
        echo '<style id="jinyu-content-font"' . jinyu_csp_nonce_attr() . '>:root{' . $css . '}</style>';
    }
}, 2);

// 首屏关键 CSS 内联（主题内置，无需后台开关）
add_action('wp_head', function () {
    $file = JINYU_ABS_DIR . '/assets/dist/style/critical.min.css';
    if (!is_file($file)) {
        return;
    }
    // 以文件 mtime 作缓存键：文件内容只在主题更新/重新构建时变化，
    // 命中缓存（对象缓存/Memcached）时省掉每请求一次的磁盘读。
    $mtime = filemtime($file);
    $css   = jinyu_cache_get('critical_css_' . $mtime);
    if (!is_string($css) || $css === '') {
        $css = (string) file_get_contents($file);
        jinyu_cache_set('critical_css_' . $mtime, $css, DAY_IN_SECONDS);
    }
    echo '<style id="jinyu-critical-css"' . jinyu_csp_nonce_attr() . '>' . $css . '</style>';
}, 1);

// Cookie 合规提示条
add_action('wp_footer', function () {
    if (!jinyu_is_checked('cookie_consent')) return;
    if (!empty($_COOKIE['jinyu_cookie_ok'])) return;
    $text = trim((string) jinyu_get_option('cookie_consent_text', ''));
    if (!$text) {
        $text = __('本站点使用 Cookie 以提升浏览体验，继续浏览即表示您同意我们的隐私政策。', 'jinyu');
    }
    echo '<div class="jinyu-cookie-bar" id="jinyu-cookie-bar" role="dialog" aria-label="' . esc_attr__('Cookie 提示', 'jinyu') . '">' .
        '<span class="jinyu-cookie-text">' . esc_html($text) . '</span>' .
        '<button type="button" class="jinyu-cookie-ok" id="jinyu-cookie-ok">' . esc_html__('同意', 'jinyu') . '</button>' .
        '</div>';
    // 点击「同意」：写入 cookie 并移除提示条。脚本紧贴提示条 HTML 之后输出，
    // 保证元素已存在于 DOM 时再绑定（避免依赖 jinyu-main 内联脚本的时序问题）。
    ?>
    <script<?php echo jinyu_csp_nonce_attr(); ?>>
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
        echo '<style id="jinyu-login-brand"' . jinyu_csp_nonce_attr() . '>';
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
