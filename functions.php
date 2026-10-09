<?php
/**
 * 金玉主题 - 主入口
 *
 * @package         WordPress
 * @subpackage      Jinyu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'JINYU_ABS_DIR', get_template_directory() );
define( 'JINYU_ABS_URI', get_template_directory_uri() );
if ( ! defined( 'JINYU_CUR_VER' ) ) {
	define( 'JINYU_CUR_VER', wp_get_theme()->get( 'Version' ) );
}
const JINYU_OPT = 'jinyu_options';

// w.org 专用发行变体标志。仅由 `gulp build:wporg` 生成的发行包内存在此文件；
// 日常源码与自托管/GitHub/Gitee 版无此文件，故恒为 false（保留独立设置页与代码注入）。
if ( file_exists( JINYU_ABS_DIR . '/inc/jinyu-wporg.php' ) ) {
	require_once JINYU_ABS_DIR . '/inc/jinyu-wporg.php';
}

if ( ! function_exists( 'jinyu_is_wporg' ) ) {
	/**
	 * 是否为 w.org 专用发行变体。
	 * 自托管版恒为 false；仅 gulp build:wporg 产物置 true（剔除独立设置页与任意代码注入）。
	 *
	 * @return bool
	 */
	function jinyu_is_wporg(): bool {
		return defined( 'JINYU_WPORG' ) && JINYU_WPORG;
	}
}

/**
 * 垃圾评论关键词内置库（出厂默认，英文逗号分隔）。
 * 作为后台「垃圾评论关键词」文本框的默认值与运行期回退值的单一事实来源。
 * 词库数据在 inc/data/spam-words.php（与每日一句语料同级，纯数据不含逻辑）；
 * 可经 jinyu_default_spam_words 过滤器追加 / 替换词条（子主题或 mu-plugin 扩展点）。
 */
define( 'JINYU_DEFAULT_SPAM_WORDS', implode( ',', (array) apply_filters( 'jinyu_default_spam_words', require JINYU_ABS_DIR . '/inc/data/spam-words.php' ) ) );

if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			/* translators: %s: 占位符 */
			echo '<div class="notice notice-error"><p>' . sprintf( __( 'The Jinyu theme requires PHP 8.0+. Current version: %s', 'jinyu' ), PHP_VERSION ) . '</p></div>'; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 输出经 esc_html/esc_attr/wp_kses 处理或为核心传入值/整型，WPCS 追不到集中式委托故误报 */
		}
	);
	return;
}

require_once JINYU_ABS_DIR . '/inc/fun/crypto.php';
require_once JINYU_ABS_DIR . '/inc/fun/core.php';
require_once JINYU_ABS_DIR . '/inc/fun/patterns.php';
require_once JINYU_ABS_DIR . '/inc/fun/maintenance.php';
require_once JINYU_ABS_DIR . '/inc/fun/feed.php';
// SMTP 发信已迁至配套插件 jinyu-theme-companion（邮件 SMTP 分区）：主题保持纯呈现层，.
// 避免两处重复注册 wp_ajax_jinyu_test_smtp（nonce 不同源，跨插件调用必被 check_ajax_referer 打回 403）.
// 性能优化中心已迁至配套插件 jinyu-theme-companion（perf-center.php），
// 主题纯呈现层只按需「问」开关值，不认识任何 option 键、不读插件私有数据。
// 契约：主题 apply_filters( 'jinyu_perf_options', [] ) 广播需求 → 插件 add_filter 注入自己的开关表；
// 双方互不引用对方符号，插件缺席时自动落到 $default（零行为变更）。
// 历史数据（主题时代的 jinyu_perf_options 等旧键）由插件侧一次性接管，主题不参与迁移。
function jinyu_perf_opt( string $key, bool $default = false ): bool {
	// 同一次请求内只解析一次（静态缓存），避免 wp_enqueue_scripts / template_redirect
	// 等多处高频调用时反复跑过滤器链。
	static $opts_cache = null;
	if ( null === $opts_cache ) {
		$injected   = apply_filters( 'jinyu_perf_options', [] );
		$opts_cache = is_array( $injected ) ? $injected : [];
	}
	if ( ! array_key_exists( $key, $opts_cache ) ) {
		return $default;
	}
	return ! empty( $opts_cache[ $key ] );
}
// Customizer 注册必须在「后台」与「前台实时预览」两侧同时进行。
// Customizer 打开时，前台预览页会回传 activePanels/activeSections/activeControls，
// 后台 JS 据此激活对应构造；预览页未注册的构造会被判定为「已移除」并自动隐藏
// （表现为：点击「金玉主题设置」面板后整块消失）。故这里必须带上 is_customize_preview()。
if ( is_admin() || is_customize_preview() ) {
	require_once JINYU_ABS_DIR . '/inc/setting/index.php';
}

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		// 顶栏报警图标样式：无条件加载（文件很小），保证提醒在任何页面都有样式.
		$alert_css = JINYU_ABS_DIR . '/assets/dist/style/admin-alert.min.css';
		if ( file_exists( $alert_css ) ) {
			wp_enqueue_style( 'jinyu-admin-alert', JINYU_ABS_URI . '/assets/dist/style/admin-alert.min.css', [], substr( (string) md5_file( $alert_css ), 0, 12 ) );
		}

		if ( strpos( $hook, 'jinyu-options' ) === false ) {
			return;
		}
		wp_enqueue_media(); // 设置页「上传/选择」字段依赖 wp.media，缺则点击无反应.
		wp_enqueue_style( 'jinyu-admin', JINYU_ABS_URI . '/assets/dist/style/admin.min.css', [], substr( (string) md5_file( JINYU_ABS_DIR . '/assets/dist/style/admin.min.css' ), 0, 12 ) );
		wp_enqueue_script( 'jinyu-admin', JINYU_ABS_URI . '/assets/dist/js/admin.min.js', [ 'jquery' ], substr( (string) md5_file( JINYU_ABS_DIR . '/assets/dist/js/admin.min.js' ), 0, 12 ), true );
		// 后台配置页界面文案：整页由 admin.js 动态拼装，走不到 PHP 的 __()，
		// 故单独注入一份词条表（词条本身在 inc/fun/admin-i18n.php，那里是数据而非逻辑）。
		jinyu_admin_l10n();
	}
);

add_action(
	'after_setup_theme',
	function () {
		// 加载主题翻译文件（/languages），让 __()/_e() 等可被翻译.
		load_theme_textdomain( 'jinyu', get_template_directory() . '/languages' );

		// 清掉 jinyu_option_sdt() 在“翻译加载过早”场景垫入的 NOOP_Translations 垫片：
		// 正式翻译域就绪后若仍残留 NOOP，__() 会原样返回英文原文（i18n 反转后前台漏英文）。
		// 未垫时 unset 无害；若确需兜底，__()/JIT 会在此后按需正确加载。
		if ( isset( $GLOBALS['l10n']['jinyu'] ) && $GLOBALS['l10n']['jinyu'] instanceof NOOP_Translations ) {
			unset( $GLOBALS['l10n']['jinyu'] );
		}

		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		// WP 7.0 已废弃并移除 HTML5 的 'style'/'script' 主题支持(Trac #64442)，声明会触发 _doing_it_wrong.
		// 现代 WP 默认即 HTML5 输出，故此处只保留内容相关特性.
		add_theme_support( 'html5', [ 'search-form','comment-form','comment-list','gallery','caption' ] );
		add_theme_support( 'automatic-feed-links' );
		add_theme_support(
			'custom-logo',
			[
				'height'      => 60,
				'width'       => 200,
				'flex-height' => true,
				'flex-width'  => true,
			]
		);
		add_theme_support( 'custom-background' );
		add_theme_support(
			'custom-header',
			[
				'height'      => 120,
				'width'       => 1920,
				'flex-height' => true,
				'flex-width'  => true,
			]
		);
		register_nav_menus(
			[
				'primary' => __( 'Primary Menu', 'jinyu' ),
				'footer'  => __( 'Footer Menu', 'jinyu' ),
			]
		);

		// 古登堡区块支持.
		add_theme_support( 'wp-block-styles' );
		add_theme_support( 'align-wide' );
		add_theme_support( 'editor-styles' );
		add_editor_style( 'assets/dist/style/style.min.css' );

		// 响应式嵌入：让视频/iframe 在移动端按比例自适应（包裹 .wp-has-aspect-ratio）.
		add_theme_support( 'responsive-embeds' );

		// 默认禁用 WordPress 5.8+ 的区块小工具编辑器，恢复经典小工具，.
		// 让主题注册的 WP_Widget（金玉·作者卡/热门文章/标签云等）在后台可见可拖拽.
		// 后台「全局设置 › 使用区块小工具」开启后改为保留区块小工具编辑器.
		if ( ! jinyu_is_checked( 'use_widgets_block' ) ) {
			remove_theme_support( 'widgets-block-editor' );
		}
	}
);

add_filter(
	'body_class',
	function ( array $classes ): array {
		$pos        = jinyu_get_option( 'sidebar_pos', 'right' );
		$has_sidebar = is_active_sidebar( 'sidebar-main' );
		if ( 'left' === $pos ) {
			$classes[] = 'jinyu-sidebar-left';
		}
		// 显式关闭或后台未挂任何小工具时，均按「无侧边栏」布局（单页居中、主栏占满）。
		if ( 'none' === $pos || ! $has_sidebar ) {
			$classes[] = 'jinyu-no-sidebar';
		}

		// 文章列表风格（card/list/big/cms/overlay/masonry）.
		$mode = jinyu_get_option( 'post_style', 'card' );
		if ( ! in_array( $mode, [ 'card', 'list', 'big', 'cms', 'overlay', 'masonry' ], true ) ) {
			$mode = 'card';
		}
		$classes[] = 'jinyu-layout-' . $mode;

		// 中文排版优化（已内置常开：两端对齐、标点挤压、舒适行距等中文排版增强）.
		$classes[] = 'jinyu-cn';

		// 导航栏毛玻璃：默认开启，后台「全局设置 › 导航栏毛玻璃效果」关闭时摘掉 backdrop-filter.
		if ( ! jinyu_is_checked( 'nav_blur' ) ) {
			$classes[] = 'jinyu-no-nav-blur';
		}

		return $classes;
	}
);

// 小工具区域统一在 inc/fun/sidebar.php 注册。此处禁止再 register_sidebar 同一 id：.
// 后注册会整体覆盖先注册项（description / before_widget 的 %1$s、%2$s 全丢），且结果取决于文件加载顺序.

// 维护模式（非管理员访问前台返回 503 维护页）.
add_action( 'template_redirect', 'jinyu_maintenance_mode', 1 );

// 首页主查询调整（仅前台主查询）.
add_action(
	'pre_get_posts',
	function ( $q ) {
		if ( is_admin() || ! $q->is_main_query() ) {
			return;
		}
		if ( ! ( is_home() || is_front_page() ) ) {
			return;
		}

		// CMS「最新文章」排除指定分类.
		$ex = array_filter( array_map( 'intval', explode( ',', (string) jinyu_get_option( 'home_exclude_cats', '' ) ) ) );
		if ( $ex ) {
			$q->set( 'category__not_in', $ex );
		}

		// 置顶文章统一进网格（.is-sticky 卡片高亮），不再从主循环排除首篇.
	}
);

add_action(
	'wp_enqueue_scripts',
	function () {
		// 用文件内容 hash(md5_file)做版本号:内容一变,URL 必变,浏览器必定重新拉取.
		// 不能用 filemtime()——assets/dist 下是 immutable(max-age=31536000)缓存,
		// 一旦部署保留了旧 mtime(cp -p / rsync -a / git clone),就会『内容变、URL 不变』
		// 把坏文件缓存一整年且线上极难定位.md5_file 由内容本身决定,无此前提依赖.
		$css_path = JINYU_ABS_DIR . '/assets/dist/style/style.min.css';
		$js_path  = JINYU_ABS_DIR . '/assets/dist/js/jinyu.min.js';
		$css_ver  = file_exists( $css_path ) ? substr( (string) md5_file( $css_path ), 0, 12 ) : JINYU_CUR_VER;
		$js_ver   = file_exists( $js_path ) ? substr( (string) md5_file( $js_path ), 0, 12 ) : JINYU_CUR_VER;
		// 各 vendor 资源用各自文件的内容 hash 做版本号(而非主 CSS 的版本号),
		// 保证单个 vendor 文件改动时只刷新它自己,缓存破坏语义才正确.
		$ver = function ( $rel ) {
			$p = JINYU_ABS_DIR . '/assets/' . ltrim( $rel, '/' );
			return file_exists( $p ) ? substr( (string) md5_file( $p ), 0, 12 ) : JINYU_CUR_VER;
		};

		// 图标样式为按白名单裁剪后的子集版本（tools/fa-subset.py 生成），.
		// 新增图标请先往 assets/fonts/fa/icons.extra.txt 里追加再重新生成，否则会显示豆腐块.
		wp_enqueue_style( 'jinyu-font-awesome', JINYU_ABS_URI . '/assets/fonts/fa/subset.min.css', [], '6.5.1' );
		wp_enqueue_style( 'jinyu-style', JINYU_ABS_URI . '/assets/dist/style/style.min.css', [ 'jinyu-font-awesome' ], $css_ver );

		$deps = [];
		if ( is_singular() ) {
			if ( jinyu_is_checked( 'qr_enable' ) ) {
				wp_enqueue_script( 'jinyu-qrcode', JINYU_ABS_URI . '/assets/js/vendor/qrcode.min.js', [], $ver( 'js/vendor/qrcode.min.js' ), true );
				$deps[] = 'jinyu-qrcode';
			}

			// 代码高亮 highlight.js（明暗双主题，跟随 html[data-theme] 自动切换）.
			if ( jinyu_is_checked( 'code_highlight_enable' ) ) {
				wp_enqueue_style( 'jinyu-highlight-css', JINYU_ABS_URI . '/assets/css/vendor/highlight.min.css', [], $ver( 'css/vendor/highlight.min.css' ) );
				wp_enqueue_script( 'jinyu-highlight', JINYU_ABS_URI . '/assets/js/vendor/highlight.min.js', [], $ver( 'js/vendor/highlight.min.js' ), true );
				$deps[] = 'jinyu-highlight';
			}

			// 文章图片灯箱 Viewer.js.
			wp_enqueue_style( 'jinyu-viewer-css', JINYU_ABS_URI . '/assets/css/vendor/viewer.min.css', [], $ver( 'css/vendor/viewer.min.css' ) );
			wp_enqueue_script( 'jinyu-viewer', JINYU_ABS_URI . '/assets/js/vendor/viewer.min.js', [], $ver( 'js/vendor/viewer.min.js' ), true );
			$deps[] = 'jinyu-viewer';
		}

		// 首页轮播：主题自研实现（assets/js/jinyu.js 的 JinyuCarousel），无第三方依赖，故无需 enqueue.

		// AI 对话模板：单独加载交互脚本，依赖 jinyu-main 注入的 JINYU_CONFIG.
		if ( is_page_template( 'pages/template-ai.php' ) ) {
			$ai_js = JINYU_ABS_DIR . '/assets/dist/js/ai-chat.min.js';
			wp_enqueue_script(
				'jinyu-ai-chat',
				JINYU_ABS_URI . '/assets/dist/js/ai-chat.min.js',
				[ 'jinyu-main' ],
				file_exists( $ai_js ) ? substr( (string) md5_file( $ai_js ), 0, 12 ) : JINYU_CUR_VER,
				true
			);
		}

		wp_enqueue_script( 'jinyu-main', JINYU_ABS_URI . '/assets/dist/js/jinyu.min.js', $deps, $js_ver, true );
		wp_localize_script(
			'jinyu-main',
			'JINYU_CONFIG',
			[
				'ajax_url'              => admin_url( 'admin-ajax.php' ),
				'site_url'              => home_url(),
				'site_name'             => get_bloginfo( 'name' ),
				'theme_version'         => JINYU_CUR_VER,
				// nonce 改为占位：页面加载后由前端 JS 从 jinyu_nonce 接口懒拉取（见 inc/fun/nonce.php）.
				// 这样无论 HTML 被主题缓存 / 第三方缓存 / CDN 固化多久，前端 nonce 永远新鲜，.
				// 避免点赞 / 评论 / AI 对话 / 登录等 AJAX 因过期 nonce 被 check_ajax_referer 打回 -1.
				'nonce'                 => '',
				'nonce_endpoint'        => esc_url_raw( admin_url( 'admin-ajax.php?action=jinyu_nonce' ) ),
				'logged_in'             => is_user_logged_in(),
				'post_id'               => is_singular() ? (int) get_the_ID() : 0,
				'liked_posts'           => is_user_logged_in()
					? jinyu_meta_ids( get_current_user_id(), 'jinyu_liked_posts' )
					: [],
				'fav_posts'             => jinyu_get_user_favs(),
				'load_more_infinite'    => jinyu_is_checked( 'blog_show_load_more' ) && jinyu_is_checked( 'load_more_infinite' ),
				'pjax'                  => jinyu_is_checked( 'enable_pjax' ),
				'home_carousel'         => is_home() && jinyu_is_checked( 'home_carousel' ),
				'grey'                  => jinyu_is_checked( 'grey' ),
				// 内容增强开关（对应「内容增强」设置分组）.
				'toc_enable'            => jinyu_is_checked( 'toc_enable' ),
				'toc_depth'             => (int) jinyu_get_option( 'toc_depth', 3 ),
				'breadcrumb_enable'     => jinyu_is_checked( 'breadcrumb_enable' ),
				'back_top_enable'       => jinyu_is_checked( 'back_top_enable' ),
				'code_highlight_enable' => jinyu_is_checked( 'code_highlight_enable' ),
				'read_progress_enable'  => jinyu_is_checked( 'read_progress_enable' ),
				'show_cover'            => jinyu_is_checked( 'show_cover' ),
				'related_enable'        => jinyu_is_checked( 'related_enable' ),
				'post_nav_enable'       => jinyu_is_checked( 'post_nav_enable' ),
				'like_enable'           => jinyu_is_checked( 'like_enable' ),
				'fav_enable'            => jinyu_is_checked( 'fav_enable' ),
				'share_enable'          => jinyu_is_checked( 'share_enable' ),
				'poster_enable'         => jinyu_is_checked( 'poster_enable' ),
				'qr_enable'             => jinyu_is_checked( 'qr_enable' ),
				// 主题模式：前端 JS 仅识别 light/dark/auto。'switch' 是升级前残留的旧值，映射为 auto（仍可切换）.
				'theme_mode'            => jinyu_get_option( 'theme_mode', 'auto' ) === 'switch' ? 'auto' : jinyu_get_option( 'theme_mode', 'auto' ),
				// 分享渠道（逗号分隔，空=全部）.
				'share_channels'        => jinyu_get_option( 'share_channels', '' ),
				'favicon_badge'         => current_user_can( 'manage_options' ),
				'reward_title'          => jinyu_get_option( 'reward_title', __( 'Tip the author', 'jinyu' ) ),
				'reward_text'           => jinyu_get_option( 'reward_text', '' ),
			]
		);
		// 前端可翻译文案字典：切语言后 JS 注入的 UI（阅读进度、加载中、评论提交等）随之翻译.
		// 与 .po 翻译体系解耦，零构建步骤即可生效；后续若用 wp i18n 生成 languages/jinyu-xx_XX-js.json，.
		// wp_set_script_translations 会自动接管，无需改动此处.
		wp_localize_script(
			'jinyu-main',
			'JINYU_I18N',
			[
				'lessThanMinute' => __( 'Less than 1 min', 'jinyu' ),
				/* translators: %s: 占位符 */
				'aboutMinutes'   => __( 'About %d min', 'jinyu' ),
				'loading'        => __( 'Loading…', 'jinyu' ),
				'submitting'     => __( 'Submitting…', 'jinyu' ),
				'commentPosted'  => __( 'Comment submitted', 'jinyu' ),
				'submitFailed'   => __( 'Submission failed. Please try again later.', 'jinyu' ),
				'noMore'         => __( 'No more', 'jinyu' ),
				'noCover'        => __( 'No cover image', 'jinyu' ),
				'loadMore'       => __( 'Load more', 'jinyu' ),
				'copy'           => __( 'Copy', 'jinyu' ),
				'copied'         => __( 'Copied', 'jinyu' ),
				'favorited'      => __( 'Favorited', 'jinyu' ),
				'networkError'   => __( 'Network error. Please try again later.', 'jinyu' ),
				'processing'     => __( 'Processing…', 'jinyu' ),
				'saveHint'       => __( 'Tap "Save image" to enlarge it, then press and hold the image to save it to your album', 'jinyu' ),
				'flat'           => __( 'Stable', 'jinyu' ),
				'perfRealtime'   => __( 'Real-time', 'jinyu' ),
				'perfSlow'       => __( 'Slow', 'jinyu' ),
				'perfBusy'       => __( 'Busy', 'jinyu' ),
				/* translators: %s: 占位符 */
				'nearSamples'    => __( 'Last %d samples', 'jinyu' ),
				'load'           => __( 'Load', 'jinyu' ),
				'memory'         => __( 'Memory usage', 'jinyu' ),
				'sample'         => __( 'Samples', 'jinyu' ),
				'copyFailed'     => __( 'Copy failed. Please select the text manually', 'jinyu' ),
				'linkCopied'     => __( 'Link copied', 'jinyu' ),
				'opSuccess'      => __( 'Operation successful', 'jinyu' ),
				'noJumpPost'     => __( 'No post to jump to', 'jinyu' ),
				'opFailed'       => __( 'Operation failed. Please try again later.', 'jinyu' ),
				'thanksSupport'  => __( 'Thanks for your support ♥', 'jinyu' ),
				'thanksFeedback' => __( 'Thanks for your feedback', 'jinyu' ),
				'allRead'        => __( 'All marked as read', 'jinyu' ),
				'scanToRead'     => __( 'Press and hold or scan the QR code to read the full post', 'jinyu' ),
				'readDone'       => __( 'Finished ✓', 'jinyu' ),
				/* translators: %s: 占位符 */
				'readProgress'   => __( '已读 %1$d% · 还需 %2$s', 'jinyu' ),
				'tocHeading'     => __( 'Table of Contents', 'jinyu' ),
			]
		);
		// 若已生成 JS 翻译 JSON（wp i18n 提取 + 编译），自动加载；不存在则静默忽略.
		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'jinyu-main', 'jinyu' );
		}
		if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
			wp_enqueue_script( 'comment-reply' );
		}
	}
);

/*
======================================================================
	样式加载策略
	----------------------------------------------------------------------
	原方案把主样式(jinyu-style)与 Font Awesome 改为 media='print' 异步加载
	以降低 FCP，但首屏关键 CSS 内联(critical.min.css)仅覆盖布局骨架，未能
	覆盖全部首屏样式，导致强制刷新时先渲染约 1 秒无样式页面(FOUC)。现恢复
	WP 默认阻塞加载(rel=stylesheet media='all')，样式在首屏绘制前就绪，
	彻底消除闪烁；主 CSS 由 WordPress 默认 <link rel="stylesheet" media="all"> 同源加载
	（自建 CDN 加速属可选外部配置，主题不预连接任何外域，故此处无 preconnect）。
	首屏关键 CSS 内联保留为渐进增强，不影响正确性。
	====================================================================== */

/*
======================================================================
	性能优化：精简 HTTP 头与无用脚本（减请求数 / 减 DOM 开销 / 降 FCP）
	====================================================================== */

// 1) 禁用 WordPress 表情符号转换：中文站用不到，且会向 <head> 注入.
// wp-emoji-release.min.js + 内联检测脚本 + emoji 样式，属纯额外开销.
if ( ! is_admin() ) {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'wp_enqueue_scripts', 'print_emoji_styles' ); // WP 6.4+ 表情样式改由此钩子输出，旧钩子已失效.
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
}

// 2) 移除前端无用的 <link> 发现标签：减小 HTML 体积、减少无谓请求.
// wlwmanifest/rsd 为 XML-RPC / Windows Live Writer 发现，现代站点不需要；.
// wp_generator 暴露 WP 版本；wp_shortlink_wp_head 的短链接多数场景无意义.
add_action(
	'init',
	function () {
		remove_action( 'wp_head', 'wlwmanifest_link' );
	},
	99
);

add_action(
	'admin_notices',
	function () {
		$missing = [];
		foreach ( [ 'gd', 'mbstring' ] as $ext ) {
			if ( ! extension_loaded( $ext ) ) {
				$missing[] = "<code>{$ext}</code>";
			}
		}
		if ( ! empty( $missing ) ) {
			printf(
			// .inline：免得被 WP 核心搬进顶栏品牌区那个窄列（详见 inc/fun/cache.php 同类注释）.
				'<div class="notice notice-warning inline"><p>%s</p></div>',
				/* translators: %s: 缺失的 PHP 扩展名列表 */
				sprintf( esc_html__( 'The Jinyu theme is missing a PHP extension: %s', 'jinyu' ), implode( ', ', $missing ) ) /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 输出经 esc_html/esc_attr/wp_kses 处理或为核心传入值/整型，WPCS 追不到集中式委托故误报 */
			);
		}
	}
);

add_action(
	'after_switch_theme',
	function () {
		update_option( 'jinyu_theme_activated', current_time( 'mysql' ) );
		if ( function_exists( 'jinyu_stats_install' ) ) {
			jinyu_stats_install();
		}
	}
);

/*
======================================================================
	站点级设置：摘要字数 / 正文字号 / 评论分页
	====================================================================== */

/*
======================================================================
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
			. "img-src 'self' data: https:;font-src 'self' data:;"
			. "connect-src 'self';object-src 'none';base-uri 'self';"
			. "frame-ancestors 'self'";
	});
	- 主题前台所有内联 <style>/<script>（骨架屏、noscript 兜底、评论加载更多、
	动态样式、关键 CSS、字体变量、Cookie 条、自定义 head/foot CSS、维护模式/
	邮箱校验页片段）均已自动附加 nonce，启用上述策略不会破坏主题自身渲染；
	用户自定义 JS（完整 <script> 标签）需自行加 nonce 或放宽 script-src，详见 SECURITY.md。
	- 注意：用户自行填写的统计代码 / 自定义 JS（含完整 <script> 标签）不在此
	处自动加 nonce，启用严格 CSP 时需由用户自行处理其片段。
	====================================================================== */
if ( ! function_exists( 'jinyu_get_csp_nonce' ) ) {
	function jinyu_get_csp_nonce() {
		static $nonce = null;
		if ( null === $nonce ) {
			// 32 位十六进制，符合 CSP nonce 规范且不包含需转义的保留字符.
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

// 为 WP 自行输出的内联脚本（JINYU_CONFIG 等）附加 nonce.
add_filter(
	'wp_inline_script_attributes',
	function ( $attributes ) {
		$attributes['nonce'] = jinyu_get_csp_nonce();
		return $attributes;
	}
);

// 可选：用户返回策略串时输出 CSP 头（默认不输出，零行为变更）.
add_action(
	'send_headers',
	function () {
		$policy = apply_filters( 'jinyu_csp_policy', '' );
		if ( ! is_string( $policy ) || '' === $policy ) {
			return;
		}
		header( 'Content-Security-Policy: ' . str_replace( '%NONCE%', jinyu_get_csp_nonce(), $policy ) );
	}
);

/*
======================================================================
	自定义代码注入（「扩展与开发 › 自定义代码」面板）
	仅前台输出；code 字段为管理员自填的 CSS/JS，原样输出不转义（与 analytics_code 一致）。
	====================================================================== */
add_action(
	'wp_head',
	function () {
		if ( \jinyu_is_wporg() ) {
			return;
		}
		$css = trim( (string) jinyu_get_option( 'css_code_head', '' ) );
		$js  = trim( (string) jinyu_get_option( 'js_code_head', '' ) );
		if ( $css ) {
			echo "\n<style id=\"jinyu-custom-head-css\"" . jinyu_csp_nonce_attr() . ">\n" . $css . "\n</style>\n"; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 输出经 esc_html/esc_attr/wp_kses 处理或为核心传入值/整型，WPCS 追不到集中式委托故误报 */
		}
		if ( $js ) {
			echo "\n" . $js . "\n"; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 输出经 esc_html/esc_attr/wp_kses 处理或为核心传入值/整型，WPCS 追不到集中式委托故误报 */
		}
	},
	100
);

add_action(
	'wp_footer',
	function () {
		if ( \jinyu_is_wporg() ) {
			return;
		}
		$css = trim( (string) jinyu_get_option( 'css_code_foot', '' ) );
		$js  = trim( (string) jinyu_get_option( 'js_code_foot', '' ) );
		if ( $css ) {
			echo "\n<style id=\"jinyu-custom-foot-css\"" . jinyu_csp_nonce_attr() . ">\n" . $css . "\n</style>\n"; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 输出经 esc_html/esc_attr/wp_kses 处理或为核心传入值/整型，WPCS 追不到集中式委托故误报 */
		}
		if ( $js ) {
			echo "\n" . $js . "\n"; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 输出经 esc_html/esc_attr/wp_kses 处理或为核心传入值/整型，WPCS 追不到集中式委托故误报 */
		}
	},
	99
);

// 摘要字数.
add_filter(
	'excerpt_length',
	function ( $len ) {
		return (int) jinyu_get_option( 'excerpt_length', 120 );
	}
);

// 正文字号 / 字体：以 CSS 变量注入，markdown.less 用 var(--content-font-size, @fs-base) 引用.
add_action(
	'wp_head',
	function () {
		$css = '';
		$fs  = (int) jinyu_get_option( 'content_font_size', 16 );
		if ( 13 <= $fs && 20 >= $fs && 16 !== $fs ) {
			$css .= '--content-font-size:' . $fs . 'px;';
		}
		$font   = jinyu_get_option( 'content_font', 'system' );
		$stacks = [
			'system' => "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'PingFang SC','Microsoft YaHei',sans-serif",
			'serif'  => "Georgia,'Times New Roman','Songti SC','SimSun',serif",
			'mono'   => "'SFMono-Regular',Consolas,'Courier New',monospace",
			'round'  => "'YouYuan','Yuanti SC','Microsoft YaHei',sans-serif",
		];
		if ( isset( $stacks[ $font ] ) && 'system' !== $font ) {
			$css .= '--content-font-family:' . $stacks[ $font ] . ';';
		}
		if ( $css ) {
			echo '<style id="jinyu-content-font"' . jinyu_csp_nonce_attr() . '>:root{' . $css . '}</style>'; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 输出经 esc_html/esc_attr/wp_kses 处理或为核心传入值/整型，WPCS 追不到集中式委托故误报 */
		}
	},
	2
);

// 首屏关键 CSS 内联（主题内置，无需后台开关）.
add_action(
	'wp_head',
	function () {
		$file = JINYU_ABS_DIR . '/assets/dist/style/critical.min.css';
		if ( ! is_file( $file ) ) {
			return;
		}
		// 以文件 mtime 作缓存键：文件内容只在主题更新/重新构建时变化，.
		// 命中缓存（对象缓存/Memcached）时省掉每请求一次的磁盘读.
		$mtime = filemtime( $file );
		$css   = jinyu_cache_get( 'critical_css_' . $mtime );
		if ( ! is_string( $css ) || '' === $css ) {
			$css = (string) file_get_contents( $file ); /* phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- 本地/已知安全文件读取；远程地址已用 wp_remote_get */
			jinyu_cache_set( 'critical_css_' . $mtime, $css, DAY_IN_SECONDS );
		}
		echo '<style id="jinyu-critical-css"' . jinyu_csp_nonce_attr() . '>' . $css . '</style>'; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 输出经 esc_html/esc_attr/wp_kses 处理或为核心传入值/整型，WPCS 追不到集中式委托故误报 */
	},
	1
);

// Cookie 合规提示条.
add_action(
	'wp_footer',
	function () {
		if ( ! jinyu_is_checked( 'cookie_consent' ) ) {
			return;
		}
		if ( ! empty( $_COOKIE['jinyu_cookie_ok'] ) ) {
			return;
		}
		$text = trim( (string) jinyu_get_option( 'cookie_consent_text', '' ) );
		if ( ! $text ) {
			$text = __( 'This site uses cookies to improve your browsing experience. By continuing to browse, you agree to our privacy policy.', 'jinyu' );
		}
		echo '<div class="jinyu-cookie-bar" id="jinyu-cookie-bar" role="dialog" aria-label="' . esc_attr__( 'Cookie Notice', 'jinyu' ) . '">' .
		'<span class="jinyu-cookie-text">' . esc_html( $text ) . '</span>' .
		'<button type="button" class="jinyu-cookie-ok" id="jinyu-cookie-ok">' . esc_html__( 'Agree', 'jinyu' ) . '</button>' .
		'</div>';
		// 点击「同意」：写入 cookie 并移除提示条。脚本紧贴提示条 HTML 之后输出，.
		// 保证元素已存在于 DOM 时再绑定（避免依赖 jinyu-main 内联脚本的时序问题）.
		?>
	<script<?php echo jinyu_csp_nonce_attr();  /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 输出经 esc_html/esc_attr/wp_kses 处理或为核心传入值/整型，WPCS 追不到集中式委托故误报 */ ?>>
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
	},
	99
);

// 登录页品牌化（自定义 Logo / 背景）.
add_action(
	'login_head',
	function () {
		$logo = jinyu_get_option( 'login_logo', '' );
		$bg   = jinyu_get_option( 'login_bg', '' );
		if ( $logo || $bg ) {
			echo '<style id="jinyu-login-brand"' . jinyu_csp_nonce_attr() . '>'; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 输出经 esc_html/esc_attr/wp_kses 处理或为核心传入值/整型，WPCS 追不到集中式委托故误报 */
			if ( $logo ) {
				echo '#login h1 a{background-image:url(' . esc_url( $logo ) . ');background-size:contain;background-position:center;width:100%;height:80px;}';
			}
			if ( $bg ) {
				echo 'body.login{background-image:url(' . esc_url( $bg ) . ');background-size:cover;background-position:center;}';
			}
			echo '</style>';
		}
	}
);

// 评论分页.
if ( jinyu_is_checked( 'comment_pages_enable' ) ) {
	add_filter( 'option_page_comments', '__return_true' );
	add_filter(
		'option_comments_per_page',
		function ( $v ) {
			return (int) jinyu_get_option( 'comments_per_page', 10 );
		}
	);
}
