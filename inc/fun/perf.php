<?php
/**
 * 金玉主题 · 后台性能优化中心
 * --------------------------------------------------------------------------
 * 一个自包含的后台工具，提供：
 *   1) 状态看板：OPcache 字节码缓存（命中率/内存/脚本数/重启次数）与
 *      Memcached 对象缓存（命中率/内存占用/条目数）的实时指标，
 *      以及 autoload 体积、过期 transient、表碎片等总览。
 *   2) 可逆开关：关闭心跳、禁用仪表盘新闻、禁用 emoji / wp-embed、限制文章修订、
 *      短接实时小工具外部地理查询、禁用 XML-RPC、清理 wp_head 冗余、禁用 pingback、
 *      前台隐藏 admin bar。每项带用途与副作用说明（注释式 UI）。
 *   3) 缓存管理：单独或一键重置 OPcache / 清空 Memcached / 清整页缓存。
 *   4) 一键优化：清理过期 transient + 优化碎片表 + 刷新全部缓存 + 套用开关，
 *      返回优化前后对比，并自动刷新状态看板。
 *
 * 设计原则：
 *   - 所有操作均可逆（开关存于 jinyu_perf_options，清理不删有效数据）。
 *   - 仅管理员可用（manage_options），全部 AJAX 走 nonce 校验。
 *   - 页面输出不使用任何 emoji / 图片：一律用 CSS / 内联 SVG 绘制，
 *     规避 WP emoji 脚本把字符替换为 s.w.org 远程图片、国内加载失败出现裂图的问题。
 *   - 不引入构建产物：JS / CSS 内联，不进 gulp 流程。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ───────────────────────── 菜单注册 ───────────────────────── */

add_action( 'admin_menu', function () {
	add_submenu_page(
		'jinyu-options',
		__( '性能优化', 'jinyu'),
		__( '性能优化', 'jinyu'),
		'manage_options',
		'jinyu-perf',
		'jinyu_perf_render_page'
	);
}, 20 );

/* ───────────────────────── 选项与开关定义 ───────────────────────── */

/**
 * 读取性能开关（单例缓存，避免一次请求内重复查询 options 表）。
 *
 * @return array key => 0|1
 */
function jinyu_perf_get_options(): array {
	static $cache = null;
	if ( is_array( $cache ) ) {
		return $cache;
	}
	$defaults = [
		'disable_heartbeat'      => 1, // 关闭后台心跳轮询（单管理员站点推荐）
		'disable_dashboard_news' => 1, // 移除仪表盘「动态与新闻」
		'disable_emoji'          => 1, // 移除 WP emoji 检测/替换脚本（默认开：国内 s.w.org 不可达）
		'disable_embed'          => 1, // 移除 wp-embed 前端脚本
		'limit_revisions'        => 1, // 每篇文章最多保留 5 个修订
		'disable_live_geo'       => 1, // 短接 whois.pconline.com.cn 地理查询
		'disable_xmlrpc'         => 1, // 禁用 XML-RPC（减小攻击面）
		'clean_wp_head'          => 1, // 清理 wp_head 冗余输出（RSD/wlwmanifest/版本号等）
		'disable_pingback'       => 1, // 禁用 pingback 自引用
		'disable_wp_org_api'     => 1, // 屏蔽 WordPress.org 外部 API（更新/翻译/主题检查，国内极慢）
		'hide_admin_bar_front'   => 0, // 前台对非管理员隐藏 admin bar（按需开启）
		'html_minify'            => 1, // 压缩前台 HTML 输出（去除空白/注释）
		'dns_preconnect'        => 1, // 关键域名 DNS 预连接（CDN 域名，加速首屏建连）
		'comment_lazyload'      => 0, // 评论懒加载（按需加载更多，减少长文首屏 DOM）
		'iframe_lazy'           => 1, // iframe 懒加载（视频/嵌入延后到视口）
		'restrict_guest_rest'   => 0, // 限制游客 REST API（拦截用户枚举等敏感路由）
	];
	$saved    = get_option( 'jinyu_perf_options', [] );
	$cache    = wp_parse_args( is_array( $saved ) ? $saved : [], $defaults );
	return $cache;
}

/**
 * 开关清单：key => [标签, 说明（用途 + 副作用）]。说明展示在每个开关下方，属「注释」UI。
 *
 * @return array
 */
function jinyu_perf_toggle_meta(): array {
	return [
		'disable_heartbeat'      => [
			'label' => __( '关闭心跳（Heartbeat）轮询', 'jinyu' ),

			'group' => 'admin',
			'desc'  => __( '后台默认每 15~60 秒向 admin-ajax.php 发一次心跳请求（协同编辑提示、自动保存增量、锁状态都靠它）。关闭后后台明显安静，但代价：编辑文章时不显示「他人正在编辑」锁定提示，自动保存间隔变得不可靠。单管理员站点建议开。', 'jinyu' ),

		],
		'disable_dashboard_news' => [
			'label' => __( '禁用仪表盘「WordPress 动态与新闻」', 'jinyu' ),

			'group' => 'admin',
			'desc'  => __( '该小工具每次打开仪表盘都会同步请求 api.wordpress.org 拉取资讯与活动，海外网络波动时可拖慢仪表盘 1~3 秒。移除后仅影响这一个官方小工具，其余小工具不受影响。', 'jinyu' ),

		],
		'disable_emoji'          => [
			'label' => __( '移除 WP Emoji 脚本（推荐）', 'jinyu' ),

			'group' => 'front',
			'desc'  => __( 'WP 会在前台/后台注入 emoji 检测脚本，并在浏览器不支持时把部分 emoji 字符替换为 s.w.org 的远程图片——国内 s.w.org 不可达时就会看到「裂开的图」。移除后 emoji 恢复为系统原生渲染（显示效果不变），只是不再转图片。本页面已全程改用 CSS 绘制图标，不再依赖 emoji。', 'jinyu' ),

		],
		'disable_embed'          => [
			'label' => __( '移除 wp-embed 脚本', 'jinyu' ),

			'group' => 'front',
			'desc'  => __( 'wp-embed.js 用于把其他 WordPress 文章以卡片形式嵌入到内容里（oEmbed）。绝大多数站点从不使用。移除后正常文章显示完全不变；仅在「嵌入别人的 WP 文章卡片」这一场景失效。', 'jinyu' ),

		],
		'limit_revisions'        => [
			'label' => __( '限制文章修订（每篇最多 5 个）', 'jinyu' ),

			'group' => 'admin',
			'desc'  => __( 'WP 默认无限保存修订版本，编辑频繁时 wp_posts 表会持续膨胀、拖慢文章查询。开启后每篇文章最多保留 5 个修订。注意：只限制新增，不删除历史修订，可用性不受影响。', 'jinyu' ),

		],
		'disable_live_geo'       => [
			'label' => __( '短接实时小工具的外部地理查询', 'jinyu' ),

			'group' => 'admin',
			'desc'  => __( '实时小工具会同步请求 whois.pconline.com.cn 查询访客 IP 归属地（超时上限 3 秒）。该外链一旦变慢，会拖慢每一个带实时小工具的页面。开启后请求被本地直接拒绝，归属地显示为空，其余实时数据不受影响。', 'jinyu' ),

		],
		'disable_xmlrpc'         => [
			'label' => __( '禁用 XML-RPC 接口', 'jinyu' ),

			'group' => 'security',
			'desc'  => __( 'XML-RPC 用于旧版编辑器与部分第三方客户端的远程调用，也是暴力破解与 pingback 攻击的常见入口。关闭后手机原生 App、Jetpack 的部分远程功能可能失效；普通站点无影响。', 'jinyu' ),

		],
		'clean_wp_head'          => [
			'label' => __( '清理 wp_head 冗余输出', 'jinyu' ),

			'group' => 'front',
			'desc'  => __( '移除 wp_head 注入的冗余标签：RSD 链接、wlwmanifest、WordPress 版本号（generator）、短链接、REST API 发现、相邻文章 rel。仅减少页面 <head> 噪音与一处外部发现链接，不影响任何功能。', 'jinyu' ),

		],
		'disable_pingback'       => [
			'label' => __( '禁用 pingback 自引用', 'jinyu' ),

			'group' => 'security',
			'desc'  => __( '摘掉 XML-RPC 的 pingback.ping 方法并关闭新文章默认 pingback，减少外链回推请求与攻击面。已发布文章的既有 pingback 不受影响。', 'jinyu' ),

		],
		'disable_wp_org_api'     => [
			'label' => __( '屏蔽 WordPress.org 外部 API 请求（推荐）', 'jinyu' ),

			'group' => 'security',
			'desc'  => __( '拦截所有发往 *.wordpress.org 的 HTTP 请求（含 api / downloads / translate）。后台每次更新检查、翻译包拉取、主题/插件版本探测都会同步请求 wp.org，国内网络波动时可拖慢后台数秒甚至超时。拦截后这些检查立即失败并跳过，后台明显变快。仅影响 WP 官方源的查询，站点自身接口（CDN / ajax 等）与国内头像源（Cravatar 等）完全不受影响；代价：后台不再提示「WordPress 有新版本」，需自行关注升级。', 'jinyu' ),

		],
		'hide_admin_bar_front'   => [
			'label' => __( '前台对非管理员隐藏 admin bar', 'jinyu' ),

			'group' => 'front',
			'desc'  => __( '未登录 / 非管理员访问前台时不再显示 WordPress 管理条，减少一处前台 CSS/JS 注入。管理员在后台与前台均不受影响。', 'jinyu' ),

		],
		'html_minify'            => [
			'label' => __( '压缩前台 HTML 输出', 'jinyu' ),

			'group' => 'front',
			'desc'  => __( '去除整页 HTML 的标签间空白与注释（仅前台完整文档，跳过后台/接口，且 <script>/<style>/<pre>/<textarea> 等原始块不被折叠）。首屏体积更小；出现极端排版问题时可关闭。', 'jinyu' ),

		],
		'dns_preconnect'        => [
			'label' => __( '关键域名 DNS 预连接（Preconnect）', 'jinyu' ),

			'group' => 'front',
			'desc'  => __( '在 <head> 最前面为静态资源 CDN 域名提前建好 DNS+TCP+TLS 连接（并附 dns-prefetch 兼容老浏览器）。首屏图片/脚本命中该域名时省去建连往返，TTFB 与 LCP 略降。仅作用于已配置的「静态资源 CDN 域名」（主题设置→资源），未配置 CDN 时不发任何多余请求；不影响其它域名。还可通过 jinyu_perf_preconnect_hosts 过滤器追加字体/统计等源。', 'jinyu' ),

		],
		'comment_lazyload'      => [
			'label' => __( '评论懒加载（加载更多）', 'jinyu' ),

			'group' => 'cache',
			'desc'  => __( '评论数多的文章，首屏只渲染第一页评论，底部出现「加载更多评论」按钮，点击后通过 admin-ajax 增量拉取后续评论页并追加（完美复用主题评论回调与嵌套结构，锚点/SEO 不受影响）。长文评论区 DOM 量大幅下降。依赖 WP 原生评论分页（page_comments 需开启），关闭后恢复一次性输出全部评论。默认关，按需开启。', 'jinyu' ),

		],
		'iframe_lazy'           => [
			'label' => __( 'iframe 懒加载', 'jinyu' ),

			'group' => 'front',
			'desc'  => __( '自动给正文/小工具里的 <iframe>（视频嵌入、第三方组件等）补上 loading="lazy"，延迟到进入视口才加载，减少首屏请求与带宽。主题 [jinyu_video] 的 B站 嵌入已内置该属性，此处对其它来源的 iframe 兜底。已带 loading 的不会被重复添加。现代浏览器对 iframe 本就默认懒加载，此开关为显式保险，默认开。', 'jinyu' ),

		],
		'restrict_guest_rest'   => [
			'label' => __( '限制游客 REST API 访问（加固）', 'jinyu' ),

			'group' => 'security',
			'desc'  => __( '未登录访客仅能访问公开内容类 REST 路由（文章/页面/评论/分类/标签/媒体/搜索等），其余内部/管理类路由（用户 /wp/v2/users、设置、插件、主题、区块、菜单、小工具、模板、全局样式、类型/分类法/状态枚举等）一律返回 403。主要阻断「通过 /wp/v2/users 枚举作者用户名」这类常见探测与 API 滥用。登录用户不受影响（后台区块编辑器等照常）。注意：若站内插件在前台依赖被拦截的路由，相关功能会受影响——默认关，确认无依赖后再开。', 'jinyu' ),

		],
	];
}

/* ───────────────────────── 开关落地（前台 + 后台全站生效） ───────────────────────── */

/**
 * 套用全部开关。挂在 init:5 —— 开关保存后于「下一个请求」生效
 * （本请求内改的选项，钩子在本请求早已执行完，不回捞，逻辑简单可靠）。
 */
add_action( 'init', 'jinyu_perf_apply', 5 );

function jinyu_perf_apply(): void {
	$o = jinyu_perf_get_options();

	// 1) 关闭心跳：在脚本加载阶段（enqueue）注销 heartbeat。
	//    注意 admin-ajax.php 上下文不走 admin_enqueue_scripts，但心跳本来
	//    只在普通页面由 JS 发起，页面不发请求即达到目的。
	if ( ! empty( $o['disable_heartbeat'] ) ) {
		$kill_heartbeat = static function () {
			if ( wp_script_is( 'heartbeat', 'registered' ) ) {
				wp_deregister_script( 'heartbeat' );
			}
		};
		add_action( 'admin_enqueue_scripts', $kill_heartbeat, 999 );
		add_action( 'wp_enqueue_scripts', $kill_heartbeat, 999 );
	}

	// 2) 移除仪表盘「动态与新闻」：在自己的 wp_dashboard_setup 回调里
	//    （优先级 1，先于小工具注册）摘掉官方注册函数。
	if ( ! empty( $o['disable_dashboard_news'] ) ) {
		add_action( 'wp_dashboard_setup', static function () {
			remove_action( 'wp_dashboard_setup', 'wp_dashboard_events_news' );
		}, 1 );
	}

	// 3) 移除 emoji 检测/替换脚本：不注入 JS，也就不会出现 s.w.org 裂图。
	//    完整接管原 optimize.php 的 emoji 移除（含 feed / 邮件过滤器），避免重复实现与开关失效。
	if ( ! empty( $o['disable_emoji'] ) ) {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_head', 'print_emoji_detection_script' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
		add_filter( 'emoji_svg_url', '__return_false' );
		add_filter( 'tiny_mce_plugins', static function ( $plugins ) {
			return is_array( $plugins ) ? array_diff( $plugins, [ 'wpemoji' ] ) : $plugins;
		} );
	}

	// 4) 移除 wp-embed 前端脚本（oEmbed 嵌入卡片）。
	if ( ! empty( $o['disable_embed'] ) ) {
		add_action( 'wp_enqueue_scripts', static function () {
			if ( wp_script_is( 'wp-embed', 'registered' ) ) {
				wp_deregister_script( 'wp-embed' );
			}
		}, 999 );
	}

	// 5) 限制修订数量：WP 核心过滤器，返回 5 即每篇最多 5 个修订。
	if ( ! empty( $o['limit_revisions'] ) ) {
		add_filter( 'wp_revisions_to_keep', static function () {
			return 5;
		}, 999 );
	}

	// 6) 短接实时小工具的外部地理查询。
	//    pre_http_request 签名是 ($preempt, $args, $url) —— 必须接满 3 参，
	//    否则 $url 拿到的是 $args 数组，PHP 8 下 strpos() 直接 TypeError。
	if ( ! empty( $o['disable_live_geo'] ) ) {
		add_filter( 'pre_http_request', static function ( $preempt, $args, $url ) {
			if ( is_string( $url ) && strpos( $url, 'whois.pconline.com.cn' ) !== false ) {
				return new WP_Error(
					'jinyu_perf_geo_disabled',
					__( '地理查询已被性能优化开关关闭', 'jinyu' )
				);
			}
			return $preempt;
		}, 10, 3 );
	}

	// 7) 禁用 XML-RPC：直接关闭远程调用接口（含 pingback 攻击面）。
	if ( ! empty( $o['disable_xmlrpc'] ) ) {
		add_filter( 'xmlrpc_enabled', '__return_false' );
	}

	// 8) 清理 wp_head 冗余输出：移除 RSD / wlwmanifest / 版本号 / 短链接 /
	//    REST API 发现 / 相邻文章 rel。仅减 <head> 噪音，不影响功能。
	if ( ! empty( $o['clean_wp_head'] ) ) {
		remove_action( 'wp_head', 'wlwmanifest_link' );
	}

	// 9) 禁用 pingback 自引用：摘掉 XML-RPC 的 pingback 方法，并关闭新文章默认 pingback。
	if ( ! empty( $o['disable_pingback'] ) ) {
		add_filter( 'xmlrpc_methods', static function ( $methods ) {
			if ( is_array( $methods ) ) {
				unset( $methods['pingback.ping'], $methods['pingback.ext_pingbacks'] );
			}
			return $methods;
		} );
		add_filter( 'pre_option_default_ping_status', '__return_zero' );
		add_filter( 'pre_option_default_pingback_status', '__return_zero' );
	}

	// 10) 前台对非管理员隐藏 admin bar：减少一处前台 CSS/JS 注入。
	if ( ! empty( $o['hide_admin_bar_front'] ) ) {
		add_action( 'init', static function () {
			if ( ! current_user_can( 'manage_options' ) ) {
				show_admin_bar( false );
			}
		} );
	}

	// 11) 屏蔽 WordPress.org 外部 API 请求：国内访问 *.wordpress.org 极慢/超时，
	//     每次后台更新检查 / 翻译拉取 / 版本探测都同步卡数秒。pre_http_request
	//     直接返回 WP_Error，WP 更新检查立即失败跳过、不再阻塞后台。
	//     仅拦截 wordpress.org 主机，站点自身接口与国内头像源不受影响。
	if ( ! empty( $o['disable_wp_org_api'] ) ) {
		add_filter( 'pre_http_request', static function ( $preempt, $args, $url ) {
			if ( ! is_string( $url ) ) {
				return $preempt;
			}
			$host = wp_parse_url( $url, PHP_URL_HOST );
			if ( $host && preg_match( '/(\.|^)WordPress\.org$/i', $host ) ) {
				return new WP_Error(
					'jinyu_perf_wp_org_blocked',
					__( 'WordPress.org 外部 API 已被性能优化开关屏蔽', 'jinyu' )
				);
			}
			return $preempt;
		}, 10, 3 );
	}

	// 12) 关键域名 DNS 预连接：在 <head> 最前为 CDN 域名提前建连。
	if ( ! empty( $o['dns_preconnect'] ) ) {
		add_action( 'wp_head', static function () {
		$hosts = [];
		$cdn   = trim( (string) jinyu_get_option( 'cdn_url', '' ) );
		$scheme = 'https';
		if ( $cdn && preg_match( '#^[a-z]+://#i', $cdn, $mm ) ) {
			$scheme = rtrim( $mm[0], ':' );
		}
		if ( $cdn ) {
			$h = wp_parse_url( $cdn, PHP_URL_HOST );
			if ( $h && ! in_array( $h, $hosts, true ) ) {
				$hosts[] = $h;
			}
		}
		// 图片常托管在存储加速域名（storage_domain，独立于 cdn_url）；只预连接 cdn_url 会导致
		// 图床域名零预连接。JY-10：一并纳入，使浏览器提前建连、省首屏 RTT。
		$sd = trim( (string) jinyu_get_option( 'storage_domain', '' ) );
		if ( $sd ) {
			$h = wp_parse_url( $sd, PHP_URL_HOST );
			if ( $h && ! in_array( $h, $hosts, true ) ) {
				$hosts[] = $h;
			}
		}
			// 允许外部追加更多需预连接的域名（如字体/统计源）
			foreach ( (array) apply_filters( 'jinyu_perf_preconnect_hosts', [] ) as $extra ) {
				if ( is_string( $extra ) && ! in_array( $extra, $hosts, true ) ) {
					$hosts[] = $extra;
				}
			}
			foreach ( $hosts as $h ) {
				echo "\n<link rel=\"preconnect\" href=\"" . esc_attr( $scheme ) . '://' . esc_attr( $h ) . "\">";
				echo "\n<link rel=\"dns-prefetch\" href=\"" . esc_attr( $scheme ) . '://' . esc_attr( $h ) . "\">";
			}
		}, 1 );
	}

	// 13) 评论懒加载：隐藏原生评论分页，改由「加载更多」按钮 + admin-ajax 增量拉取。
	if ( ! empty( $o['comment_lazyload'] ) ) {
		// 隐藏原生分页（由前端按钮替代）
		add_filter( 'the_comments_pagination', static function ( $html ) {
			return is_singular() ? '' : $html;
		} );
		// 仅在单篇且开放评论时输出增量加载脚本
		add_action( 'wp_footer', static function () {
			if ( ! is_singular() || ! comments_open() ) {
				return;
			}
			$opt = jinyu_perf_get_options();
			if ( empty( $opt['comment_lazyload'] ) ) {
				return;
			}
			?>
			<script>
			(function(){
				var btn = document.querySelector('.jinyu-comments-more');
				if(!btn) return;
				var list = document.querySelector('.jinyu-comment-list');
				if(!list) return;
				var busy = false;
				btn.addEventListener('click', function(){
					if(busy) return; busy=true;
					var page = parseInt(btn.getAttribute('data-page')||'2',10);
					var max  = parseInt(btn.getAttribute('data-max')||'1',10);
					var txt  = btn.querySelector('.jinyu-comments-more-txt');
					var old  = txt ? txt.textContent : '<?php echo esc_js( __( '加载更多评论', 'jinyu' ) ); ?>';
					if(txt) txt.textContent='<?php echo esc_js( __( '加载中…', 'jinyu' ) ); ?>';
					var url = (btn.getAttribute('data-ajax')||'') + '?action=jinyu_load_comments&post_id=' + encodeURIComponent(btn.getAttribute('data-post-id')||'') + '&page=' + page;
					fetch(url).then(function(r){return r.json();}).then(function(j){
						if(j && j.success && j.data && j.data.html){
							list.insertAdjacentHTML('beforeend', j.data.html);
							page++;
							btn.setAttribute('data-page', page);
							if(page>max && btn.parentNode){ btn.parentNode.removeChild(btn); }
							else if(txt){ txt.textContent=old; }
						} else if(txt){ txt.textContent=old; }
						busy=false;
					}).catch(function(){ if(txt) txt.textContent=old; busy=false; });
				});
			})();
			</script>
			<?php
		} );
	}

	// 14) iframe 懒加载：正文/小工具里缺 loading 属性的 iframe 自动补上 lazy。
	if ( ! empty( $o['iframe_lazy'] ) ) {
		$add_lazy = static function ( $content ) {
			if ( ! is_string( $content ) || false === strpos( $content, '<iframe' ) ) {
				return $content;
			}
			return preg_replace_callback(
				'#<iframe\b([^>]*)>#i',
				static function ( $m ) {
					if ( preg_match( '#\bloading\s*=#i', $m[1] ) ) {
						return $m[0];
					}
					return '<iframe loading="lazy"' . $m[1] . '>';
				},
				$content
			);
		};
		add_filter( 'the_content', $add_lazy, 99 );
		add_filter( 'widget_text_content', $add_lazy, 99 );
		add_filter( 'widget_block_content', $add_lazy, 99 );
	}

	// 15) 限制游客 REST API：未登录仅放行公开内容类路由，拦截内部/管理类路由。
	if ( ! empty( $o['restrict_guest_rest'] ) ) {
		add_filter( 'rest_pre_dispatch', static function ( $result, $server, $request ) {
			if ( is_user_logged_in() ) {
				return $result;
			}
			$route = ltrim( (string) $request->get_route(), '/' );
			// 默认放行：公开内容类（文章/页面/媒体/评论/分类/标签/搜索/状态）
			// 拦截：用户枚举、设置、插件/主题、区块、菜单、小工具、模板、全局样式、类型/分类法枚举
			$deny  = (array) apply_filters( 'jinyu_perf_rest_guest_deny', [
				'#^wp/v2/users#m',
				'#^wp/v2/settings#m',
				'#^wp/v2/plugins#m',
				'#^wp/v2/themes#m',
				'#^wp/v2/blocks#m',
				'#^wp/v2/block-patterns#m',
				'#^wp/v2/block-types#m',
				'#^wp/v2/block-renderer#m',
				'#^wp/v2/menu-items#m',
				'#^wp/v2/menus#m',
				'#^wp/v2/widget-types#m',
				'#^wp/v2/widgets#m',
				'#^wp/v2/template-parts#m',
				'#^wp/v2/templates#m',
				'#^wp/v2/global-styles#m',
				'#^wp/v2/types#m',
				'#^wp/v2/taxonomies#m',
				'#^wp/v2/statuses#m',
			] );
			foreach ( $deny as $re ) {
				if ( is_string( $re ) && preg_match( $re, $route ) ) {
					return new WP_Error(
						'jinyu_rest_forbidden',
						__( '游客无权访问该 REST 接口', 'jinyu' ),

						[ 'status' => 403 ]
					);
				}
			}
			return $result;
		}, 10, 3 );
	}
}

/* ───────────────────────── 状态采集：总览 ───────────────────────── */

/**
 * 总览指标（轻量，两次 SQL，可每页加载）。
 *
 * @return array
 */
function jinyu_perf_status(): array {
	global $wpdb;

	$autoload = (int) $wpdb->get_var(
		"SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload = 'yes'"
	);

	$transient_total = (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_%' OR option_name LIKE '_site_transient_%'"
	);
	$transient_expired = (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$wpdb->options}
		 WHERE (option_name LIKE '_transient_timeout_%' OR option_name LIKE '_site_transient_timeout_%')
		 AND option_value < UNIX_TIMESTAMP()"
	);

	$overhead = (float) $wpdb->get_var(
		"SELECT COALESCE(SUM(Data_free),0) FROM information_schema.TABLES
		 WHERE TABLE_SCHEMA = DATABASE() AND Data_free > 0"
	);

	$active_plugins = (array) get_option( 'active_plugins', [] );

	// 心跳状态直接由开关推导：admin-ajax 上下文里 wp_script_is 不可靠，
	// 用「用户意志」而非「运行时碰巧的状态」来展示，逻辑才自洽。
	$o         = jinyu_perf_get_options();
	$opcache   = jinyu_perf_opcache_stats();
	$memcached = jinyu_perf_memcached_stats();

	return [
		'opcache'           => $opcache ? (bool) $opcache['enabled'] : false,
		'object_cache'      => (bool) ( $memcached['reachable'] ?? wp_using_ext_object_cache() ),
		'autoload_bytes'    => $autoload,
		'transient_total'   => $transient_total,
		'transient_expired' => $transient_expired,
		'table_overhead'    => $overhead,
		'active_plugins'    => count( $active_plugins ),
		'heartbeat'         => empty( $o['disable_heartbeat'] ),
	];
}

/* ───────────────────────── 状态采集：OPcache 看板 ───────────────────────── */

/**
 * 读取 OPcache 运行指标。
 * 注意：CLI 下 opcache.enable_cli 默认关，返回 enabled=false 属正常，
 * 看板以 web（php-fpm）上下文为准——本函数由后台页面/AJAX 触发，天然是 web 上下文。
 *
 * @return array|null null=扩展未安装
 */
function jinyu_perf_opcache_stats(): ?array {
	if ( ! function_exists( 'opcache_get_status' ) ) {
		return null;
	}
	$st = @opcache_get_status( false );
	if ( ! is_array( $st ) ) {
		// 扩展装了但当前 SAPI 未启用（如 CLI）
		return [ 'enabled' => false ];
	}
	$stat = $st['opcache_statistics'] ?? [];

	// 内存键名兼容：PHP <=8.0 平铺在顶层（memory_used/free/wasted），
	// PHP 8.1+ 收进 memory_usage 子数组（used_memory/free_memory/wasted_memory）。
	$mu   = $st['memory_usage'] ?? [];
	$used = (float) ( $mu['used_memory'] ?? $st['memory_used'] ?? 0 );
	$free = (float) ( $mu['free_memory'] ?? $st['memory_free'] ?? 0 );
	$wasted = (float) ( $mu['wasted_memory'] ?? $st['memory_wasted'] ?? 0 );
	$total  = $used + $free;

	return [
		'enabled'        => ! empty( $st['opcache_enabled'] ),
		'cached_scripts' => (int) ( $stat['num_cached_scripts'] ?? 0 ),
		'hit_rate'       => round( (float) ( $stat['opcache_hit_rate'] ?? 0 ), 1 ),
		'misses'         => (int) ( $stat['num_misses'] ?? 0 ),
		'memory_used'    => $used,
		'memory_total'   => $total,
		'mem_pct'        => $total > 0 ? round( $used / $total * 100, 1 ) : 0,
		'wasted'         => $wasted,
		'oom_restarts'   => (int) ( $stat['oom_restarts'] ?? 0 ),
		'last_restart'   => (int) ( $stat['last_restart_time'] ?? 0 ),
	];
}

/* ───────────────────────── 状态采集：Memcached 看板 ───────────────────────── */

/**
 * 读取 Memcached 服务器实时指标（getStats，等价于 stats 命令）。
 * 使用持久连接池，避免每次看板刷新都重建 TCP 连接。
 *
 * @return array|null null=PECL memcached 扩展不可用
 */
function jinyu_perf_memcached_stats(): ?array {
	if ( ! class_exists( 'Memcached' ) ) {
		return null;
	}

	// 服务器列表可通过过滤器覆盖（默认本机 11211，与 object-cache.php drop-in 一致）
	$servers = apply_filters( 'jinyu_perf_memcached_servers', [ [ '127.0.0.1', 11211 ] ] );

	// 持久池：同池复用连接；仅首次给池加服务器
	$m = new Memcached( 'jinyu-perf-board' );
	if ( ! $m->getServerList() ) {
		$m->addServers( $servers );
	}
	$all = $m->getStats();
	if ( empty( $all ) ) {
		return [ 'reachable' => false ];
	}
	$s = reset( $all );

	$hits   = (int) ( $s['get_hits'] ?? 0 );
	$misses = (int) ( $s['get_misses'] ?? 0 );
	$total  = $hits + $misses;
	$limit  = (int) ( $s['limit_maxbytes'] ?? 0 );
	$bytes  = (int) ( $s['bytes'] ?? 0 );

	return [
		'reachable'   => true,
		'version'     => (string) ( $s['version'] ?? '' ),
		'hit_rate'    => $total > 0 ? round( $hits / $total * 100, 1 ) : null,
		'hits'        => $hits,
		'misses'      => $misses,
		'curr_items'  => (int) ( $s['curr_items'] ?? 0 ),
		'bytes'       => $bytes,
		'limit'       => $limit,
		'mem_pct'     => $limit > 0 ? round( $bytes / $limit * 100, 1 ) : 0,
		'uptime'      => (int) ( $s['uptime'] ?? 0 ),
		'connections' => (int) ( $s['curr_connections'] ?? 0 ),
	];
}

/* ───────────────────────── 状态采集：真实用户 Web Vitals ───────────────────────── */

/**
 * 读取本地聚合的真实用户指标（由前端 web-vitals 采集后写入）。
 *
 * @return array|null null=近 7 天无样本
 */
function jinyu_perf_web_vitals_stats(): ?array {
	$agg = get_option( 'jinyu_web_vitals_stats' );
	if ( ! is_array( $agg ) || empty( $agg['n'] ) ) {
		return null;
	}
	$n   = (int) $agg['n'];
	$out = [
		'n'     => $n,
		'avg'   => [],
		'max'   => [],
		'paths' => [],
		'fresh' => ! empty( $agg['ts'] ) && ( time() - (int) $agg['ts'] ) < WEEK_IN_SECONDS,
	];
	foreach ( [ 'lcp', 'inp', 'cls', 'fcp', 'ttfb' ] as $k ) {
		$sum         = (float) ( $agg['sum'][ $k ] ?? 0 );
		$out['avg'][ $k ] = $n > 0 ? round( $sum / $n, $k === 'cls' ? 3 : 0 ) : 0;
		$out['max'][ $k ] = round( (float) ( $agg['max'][ $k ] ?? 0 ), $k === 'cls' ? 3 : 0 );
	}
	if ( ! empty( $agg['path_metrics'] ) && is_array( $agg['path_metrics'] ) ) {
		$rows = [];
		foreach ( $agg['path_metrics'] as $p => $pm ) {
			if ( ! is_array( $pm ) || empty( $pm['n'] ) ) {
				continue;
			}
			$rows[] = [
				'path'    => $p,
				'n'       => (int) $pm['n'],
				'lcp_avg' => $pm['n'] > 0 ? (int) round( (float) $pm['lcp_sum'] / $pm['n'] ) : 0,
				'lcp_max' => (int) round( (float) ( $pm['lcp_max'] ?? 0 ) ),
			];
		}
		// 按最差 LCP 降序，取前 5 条路径 = 最慢路径
		usort( $rows, static function ( $a, $b ) {
			return $b['lcp_max'] <=> $a['lcp_max'];
		} );
		$out['slowest'] = array_slice( $rows, 0, 5 );
	}
	return $out;
}

/**
 * Web Vitals 指标元数据：标签 / 中文名 / 单位 / 良好与较差阈值（Core Web Vitals 标准）。
 *
 * @return array
 */
function jinyu_perf_wv_meta(): array {
	return [
		'lcp'  => [ 'label' => 'LCP', 'name' => __( '最大内容绘制', 'jinyu' ), 'ms' => true, 'good' => 2500, 'poor' => 4000,
			'tip' => __( '视口内最大元素（图片/标题/区块）渲染完成的时间。≤2.5s 良好，≥4s 较差。', 'jinyu' ),

			'optimize' => __( '压缩首屏大图并转 WebP，预加载关键资源，非首屏图片懒加载。', 'jinyu' ) ],
		'inp'  => [ 'label' => 'INP', 'name' => __( '交互延迟', 'jinyu' ), 'ms' => true, 'good' => 200, 'poor' => 500,
			'tip' => __( '用户点击/输入到页面响应的延迟，反映整体交互流畅度。≤200ms 良好，≥500ms 较差。', 'jinyu' ),

			'optimize' => __( '拆分长任务、精简第三方脚本，交互回调避免强制同步布局。', 'jinyu' ) ],
		'cls'  => [ 'label' => 'CLS', 'name' => __( '累计布局位移', 'jinyu' ), 'ms' => false, 'good' => 0.1, 'poor' => 0.25,
			'tip' => __( '页面加载中元素意外位移的幅度，衡量视觉稳定性。≤0.1 良好，≥0.25 较差。', 'jinyu' ),

			'optimize' => __( '为图片/视频/广告预留宽高比，禁止插入内容引发位移。', 'jinyu' ) ],
		'fcp'  => [ 'label' => 'FCP', 'name' => __( '首次内容绘制', 'jinyu' ), 'ms' => true, 'good' => 1800, 'poor' => 3000,
			'tip' => __( '浏览器首次画出任意文本/图片的时间，首屏出图的快慢。≤1.8s 良好。', 'jinyu' ),

			'optimize' => __( '内联关键 CSS，移除阻塞渲染的脚本，启用对象缓存。', 'jinyu' ) ],
		'ttfb' => [ 'label' => 'TTFB', 'name' => __( '首字节时间', 'jinyu' ), 'ms' => true, 'good' => 800, 'poor' => 1800,
			'tip' => __( '从请求到收到服务器第一个字节的耗时，反映后端与网络。≤0.8s 良好。', 'jinyu' ),

			'optimize' => __( '开启页面缓存与对象缓存，优化数据库查询，启用 CDN。', 'jinyu' ) ],
	];
}

/**
 * 按 Core Web Vitals 阈值给均值评级：good / mid / poor。
 *
 * @param float $v   指标均值
 * @param array $meta jinyu_perf_wv_meta() 的单项
 * @return string
 */
function jinyu_perf_wv_rate( float $v, array $meta ): string {
	if ( $v <= (float) $meta['good'] ) {
		return 'good';
	}
	if ( $v >= (float) $meta['poor'] ) {
		return 'poor';
	}
	return 'mid';
}

/**
 * 由 5 项指标聚合出 0-100 综合体验评分（Field Performance Score）。
 * 单项分：≤good 记 100，≥poor 记 0，区间内线性插值；加权平均，并按木桶取评级。
 *
 * @param array $wv  jinyu_perf_web_vitals_stats() 返回值
 * @param array $wvm jinyu_perf_wv_meta() 返回值
 * @return array [ 'score' => int, 'rate' => 'good'|'mid'|'poor' ]
 */
function jinyu_perf_wv_score( array $wv, array $wvm ): array {
	$weights = [ 'lcp' => 0.25, 'inp' => 0.25, 'cls' => 0.15, 'fcp' => 0.15, 'ttfb' => 0.20 ];
	$sum = 0; $w = 0; $worst = 'good';
	foreach ( $weights as $k => $wt ) {
		if ( ! isset( $wv['avg'][ $k ] ) ) {
			continue;
		}
		$v    = (float) $wv['avg'][ $k ];
		$meta = $wvm[ $k ];
		$good = (float) $meta['good'];
		$poor = (float) $meta['poor'];
		if ( $v <= $good ) {
			$s = 100;
		} elseif ( $v >= $poor ) {
			$s = 0;
		} else {
			$s = round( 100 * ( $poor - $v ) / ( $poor - $good ) );
		}
		$sum += $s * $wt;
		$w   += $wt;
		$r    = jinyu_perf_wv_rate( $v, $meta );
		if ( 'poor' === $r ) {
			$worst = 'poor';
		} elseif ( 'mid' === $r && 'poor' !== $worst ) {
			$worst = 'mid';
		}
	}
	$score = $w > 0 ? (int) round( $sum / $w ) : 0;
	// 木桶：任一核心项较差则整体不超过「需优化」上限。
	if ( 'poor' === $worst && $score > 49 ) {
		$score = 49;
	}
	return [
		'score' => $score,
		'rate'  => $score >= 90 ? 'good' : ( $score >= 50 ? 'mid' : 'poor' ),
	];
}

/* ───────────────────────── 优化动作 ───────────────────────── */

/**
 * 清理过期 transient。
 * 只删「已过期」的 timeout 计时行（option_value < 当前时间戳），
 * 对应值行由 WP 惰性回收（读到过期 timeout 会自动当作不存在），
 * 有效期内的 transient 一条不碰。
 *
 * @return int 删除行数
 */
function jinyu_perf_clean_transients(): int {
	global $wpdb;
	$sql = "DELETE FROM {$wpdb->options}
			WHERE (option_name LIKE '_transient_timeout_%' OR option_name LIKE '_site_transient_timeout_%')
			AND option_value < UNIX_TIMESTAMP()";
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- 维护类批量操作，无法用 API 替代
	$wpdb->query( $sql );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	return (int) $wpdb->rows_affected;
}

/**
 * 优化有碎片的表（information_schema 里 Data_free > 0 的表）。
 * 仅手动触发；InnoDB 下 OPTIMIZE 等价于在线重建，安全但会短暂锁表，
 * 所以绝不挂在任何自动钩子上，只在用户点击按钮时执行。
 *
 * @return array{optimized:int, list:string[]}
 */
function jinyu_perf_optimize_tables(): array {
	global $wpdb;
	$tables = $wpdb->get_results(
		"SELECT TABLE_NAME AS t, Data_free AS f FROM information_schema.TABLES
		 WHERE TABLE_SCHEMA = DATABASE() AND Data_free > 0 ORDER BY Data_free DESC"
	);
	$optimized = 0;
	$list      = [];
	foreach ( (array) $tables as $row ) {
		// 表名来自 information_schema（系统目录），非用户输入，无注入面
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL
		$res = $wpdb->query( $wpdb->prepare( 'OPTIMIZE TABLE %i', $row->t ) );
		if ( false !== $res ) {
			++$optimized;
			$list[] = $row->t . '(' . jinyu_perf_human( (float) $row->f ) . ')';
		}
	}
	return [ 'optimized' => $optimized, 'list' => $list ];
}

/* ───────────────────────── 缓存刷新 ───────────────────────── */

/**
 * 重置 OPcache（整池字节码缓存，所有 fpm worker 共享）。
 * 必须在 web 上下文调用才生效；CLI 下返回 false 属预期，结果里如实提示。
 *
 * @return string 人类可读结果
 */
function jinyu_perf_reset_opcache(): string {
	if ( ! function_exists( 'opcache_reset' ) ) {
		return __( 'OPcache 未安装，无需重置', 'jinyu' );
	}
	$ok = @opcache_reset();
	return $ok
		? __( 'OPcache 已重置', 'jinyu' )
		: __( 'OPcache 重置未生效（当前可能是 CLI 上下文，请在后台页面点击）', 'jinyu' );
}

/**
 * 清空 Memcached 对象缓存。
 * 优先走 WP drop-in 的 wp_cache_flush()（本站底层即 Memcached::flush()，
 * 等价于 flush_all）；drop-in 不在时降级为直连 stats 探测的清空。
 *
 * @return string 人类可读结果
 */
function jinyu_perf_flush_memcached(): string {
	if ( function_exists( 'wp_cache_flush' ) && wp_using_ext_object_cache() ) {
		$ok = wp_cache_flush();
		return $ok ? __( 'Memcached 对象缓存已清空', 'jinyu' ) : __( 'Memcached 清空失败', 'jinyu' );
	}
	// 降级路径：没有 drop-in 但扩展可用时直连清空
	if ( class_exists( 'Memcached' ) ) {
		$m = new Memcached( 'jinyu-perf-flush' );
		if ( ! $m->getServerList() ) {
			$m->addServers( apply_filters( 'jinyu_perf_memcached_servers', [ [ '127.0.0.1', 11211 ] ] ) );
		}
		return $m->flush() ? __( 'Memcached 已清空（直连）', 'jinyu' ) : __( 'Memcached 清空失败', 'jinyu' );
	}
	return __( '对象缓存未启用（无 drop-in 也无 Memcached 扩展）', 'jinyu' );
}

/**
 * 清第三方整页缓存（WP Super Cache / W3 Total Cache 等，若有）。
 *
 * @return string 空串表示本站没有可清的整页缓存
 */
function jinyu_perf_flush_page_cache(): string {
	$msgs = [];

	// 主题内容缓存（transient / 对象缓存组）：先清它，否则点「清除整页缓存」
	// 只清了第三方插件、主题缓存原样留存，造成「点了没反应」的假象。
	if ( function_exists( 'jinyu_cache_flush' ) ) {
		$n      = jinyu_cache_flush();
		$msgs[] = sprintf( __( '主题内容缓存已清空（%d 项）', 'jinyu' ), $n );
	}

	// 第三方整页缓存插件
	if ( function_exists( 'wp_cache_clean_cache' ) ) {       // WP Super Cache
		wp_cache_clean_cache( true );
		$msgs[] = __( '整页缓存已清空', 'jinyu' );
	} elseif ( function_exists( 'wp_cache_clear_cache' ) ) { // W3 Total Cache
		wp_cache_clear_cache();
		$msgs[] = __( '整页缓存已清空', 'jinyu' );
	}

	return implode( __( '；', 'jinyu' ), $msgs );
}

/**
 * 组合刷新：OPcache + Memcached + 整页缓存（供一键优化 / 清除全部使用）。
 *
 * @return string[] 每条结果的人类可读消息（整页缓存不存在时返回空串，调用方过滤）
 */
function jinyu_perf_flush_caches(): array {
	return [
		jinyu_perf_reset_opcache(),
		jinyu_perf_flush_memcached(),
		jinyu_perf_flush_page_cache(),
	];
}

/* ───────────────────────── 工具 ───────────────────────── */

/** 字节数转人类可读（KB/MB/GB），全站看板统一入口。 */
function jinyu_perf_human( float $bytes ): string {
	if ( $bytes >= 1073741824 ) {
		return round( $bytes / 1073741824, 2 ) . ' GB';
	}
	if ( $bytes >= 1048576 ) {
		return round( $bytes / 1048576, 2 ) . ' MB';
	}
	if ( $bytes >= 1024 ) {
		return round( $bytes / 1024, 1 ) . ' KB';
	}
	return (int) $bytes . ' B';
}

/** 秒数转「x 天 x 小时」人类可读。 */
function jinyu_perf_human_uptime( int $s ): string {
	$d = intdiv( $s, 86400 );
	$h = intdiv( $s % 86400, 3600 );
	return $d > 0 ? $d . __( ' 天 ', 'jinyu' ) . $h . __( ' 小时', 'jinyu' ) : ( $h > 0 ? $h . __( ' 小时', 'jinyu' ) : $s . __( ' 秒', 'jinyu' ) );
}

/* ───────────────────────── 渲染：状态看板（指标条 + 双看板） ───────────────────────── */

/**
 * 渲染整个状态区（指标条 + OPcache 看板 + Memcached 看板）。
 * 页面初次加载与 AJAX 刷新共用这一个函数，保证两处 HTML 结构永远一致
 * （单一数据源，不会出现「初始渲染和刷新后长得不一样」的漂移）。
 *
 * @return string HTML 片段
 */
function jinyu_perf_render_status_html(): string {
	$s = jinyu_perf_status();
	$o = jinyu_perf_opcache_stats();
	$m = jinyu_perf_memcached_stats();

	// 指标条单元格；传入 $count 时数值包入 [data-count] 供前端 count-up 缓动。
	$stat = static function ( string $k, string $v, string $d, $count = null, string $suffix = '' ): string {
		$num = ( null !== $count )
			? '<span class="jperf-num" data-count="' . $count . '">0</span>' . $suffix
			: $v;
		return '<div class="jperf-stat"><div class="jperf-k">' . $k . '</div>'
			. '<div class="jperf-v">' . $num . '</div>'
			. '<div class="jperf-d">' . $d . '</div></div>';
	};

	// 细描边环形图：data-pct 交给前端 JS 做绘制动画；data-null 时显示「—」且不绘制。
	$ring = static function ( $pct, string $stroke, string $sub ): string {
		$is_null = ( null === $pct );
		$val     = $is_null ? 0 : (float) $pct;
		$ctr     = $is_null ? '—' : '0<u>%</u>';
		$attr    = $is_null ? ' data-null="1"' : '';
		return '<div class="jperf-ring"><svg width="108" height="108" viewBox="0 0 108 108">'
			. '<circle class="jperf-track" cx="54" cy="54" r="46"/>'
			. '<circle class="jperf-bar" cx="54" cy="54" r="46" data-pct="' . $val . '"' . $attr
			. ' style="stroke:' . $stroke . '"/></svg>'
			. '<div class="jperf-ctr"><div class="jperf-pct" data-pct="' . $val . '"' . $attr . '>' . $ctr . '</div>'
			. '<div class="jperf-rlbl">' . $sub . '</div></div></div>';
	};

	// 键值行；传入 $meter（百分比字符串）时追加内存占用细进度条。
	$kv = static function ( string $name, string $val, string $meter = '' ): string {
		$row = '<div class="jperf-kvrow"><span class="jperf-name">' . $name . '</span>'
			. '<span class="jperf-val">' . $val . '</span></div>';
		if ( '' !== $meter ) {
			$row .= '<div class="jperf-meter"><i style="width:' . $meter . '"></i></div>';
		}
		return $row;
	};

	$op_hit = $o ? $o['hit_rate'] : null;
	$mc_ok  = ( $m && ! empty( $m['reachable'] ) );
	$mc_hit = $mc_ok ? $m['hit_rate'] : null;
	$mver   = $mc_ok ? (string) ( $m['version'] ?? '' ) : '';
	$mtag   = '' !== $mver ? 'v' . $mver : ( null === $m ? '未安装' : '不可达' );

	$html  = '<div class="jperf-status">';

	// —— 指标条（分隔线，非卡片堆叠）——
	$html .= '<div class="jperf-stats">';
	$html .= $stat( 'OPcache 命中率', '—', '字节码缓存有效',
		null === $op_hit ? null : $op_hit, null === $op_hit ? '' : '<small>%</small>' );
	$html .= $stat( 'Memcached 命中率', '—', '对象缓存有效',
		null === $mc_hit ? null : $mc_hit, null === $mc_hit ? '' : '<small>%</small>' );
	$html .= $stat( '待清理 Transient', (string) (int) $s['transient_expired'], '已过期待回收',
		(int) $s['transient_expired'], '' );
	$html .= $stat( '活跃插件', (string) (int) $s['active_plugins'], '含主题内置模块',
		(int) $s['active_plugins'], '' );
	$html .= '</div>';

	// —— 监控双栏 ——
	$html .= '<p class="jperf-eyebrow jperf-eyebrow-mt">运行时监控</p>';
	$html .= '<div class="jperf-panels">';

	// OPcache 面板
	$html .= '<div class="jperf-panel"><div class="jperf-panel-head">'
		. '<svg viewBox="0 0 24 24"><path d="M13 2L3 14h7l-1 8 10-12h-7z"/></svg>'
		. '<h3>OPcache 字节码缓存</h3><span class="jperf-tag">PHP ' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '</span></div>';
	$html .= '<div class="jperf-gauge">';
	$html .= $ring( $op_hit, 'var(--j-accent)', '命中率' );
	$html .= '<div class="jperf-kv">';
	if ( null === $o ) {
		$html .= '<div class="jperf-kvrow"><span class="jperf-name">状态</span><span class="jperf-val">未安装扩展</span></div>';
	} elseif ( empty( $o['enabled'] ) ) {
		$html .= '<div class="jperf-kvrow"><span class="jperf-name">状态</span><span class="jperf-val">当前上下文未启用</span></div>';
	} else {
		$html .= $kv( '缓存脚本', number_format_i18n( $o['cached_scripts'] ) );
		$html .= $kv( '内存占用', jinyu_perf_human( $o['memory_used'] ) . ' / ' . jinyu_perf_human( $o['memory_total'] ) );
		$html .= $kv( '内存使用率', $o['mem_pct'] . '%', $o['mem_pct'] . '%' );
		$html .= $kv( '浪费内存', jinyu_perf_human( $o['wasted'] ) );
	}
	$html .= '</div></div></div>';

	// Memcached 面板
	$html .= '<div class="jperf-panel"><div class="jperf-panel-head">'
		. '<svg viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h10"/></svg>'
		. '<h3>Memcached 对象缓存</h3><span class="jperf-tag">' . esc_html( $mtag ) . '</span></div>';
	$html .= '<div class="jperf-gauge">';
	$html .= $ring( $mc_hit, 'var(--j-ink2)', '命中率' );
	$html .= '<div class="jperf-kv">';
	if ( null === $m ) {
		$html .= '<div class="jperf-kvrow"><span class="jperf-name">状态</span><span class="jperf-val">未安装扩展</span></div>';
		} elseif ( empty( $m['reachable'] ) ) {
			$ext = (bool) wp_using_ext_object_cache();
			$msg = $ext ? '未连接 Memcached（当前使用其他对象缓存后端）' : '无法连接 127.0.0.1:11211';
			$html .= '<div class="jperf-kvrow"><span class="jperf-name">状态</span><span class="jperf-val">' . $msg . '</span></div>';
	} else {
		$html .= $kv( '缓存条目', number_format_i18n( $m['curr_items'] ) );
		$html .= $kv( '内存占用', jinyu_perf_human( (float) $m['bytes'] ) . ' / ' . jinyu_perf_human( (float) $m['limit'] ) );
		$html .= $kv( '内存使用率', $m['mem_pct'] . '%', $m['mem_pct'] . '%' );
		$html .= $kv( '已运行', jinyu_perf_human_uptime( $m['uptime'] ) );
	}
	$html .= '</div></div></div>';
	$html .= '</div>'; // 闭合 .jperf-panels：Web Vitals 区是全宽板块，不进监控双栏

	// —— 真实用户 Web Vitals（近 7 天聚合）——
	$wv  = jinyu_perf_web_vitals_stats();
	$wvm = jinyu_perf_wv_meta();
	$wv_score = ( null !== $wv ) ? jinyu_perf_wv_score( $wv, $wvm ) : null;
	$html .= '<p class="jperf-eyebrow jperf-eyebrow-mt">真实用户体验（近 7 天）</p>';
	$html .= '<div class="jperf-wv">';
	if ( null === $wv ) {
		$html .= '<div class="jperf-wv-empty">暂无样本。前端已采集 LCP / INP / CLS / FCP / TTFB，访客浏览后这里会出现真实均值。</div>';
	} else {
		foreach ( $wvm as $k => $meta ) {
			$avg   = (float) $wv['avg'][ $k ];
			$max   = (float) $wv['max'][ $k ];
			$rate  = jinyu_perf_wv_rate( $avg, $meta );
			$val   = $meta['ms'] ? number_format_i18n( (int) $avg ) . ' ms' : $avg;
			$worst = $meta['ms'] ? number_format_i18n( (int) $max ) . ' ms' : $max;
			$badge = 'good' === $rate ? '良好' : ( 'poor' === $rate ? '较差' : '需优化' );
			$html .= '<div class="jperf-wv-chip rate-' . $rate . '" title="' . esc_attr( $meta['tip'] ) . '">'
				. '<div class="jperf-wv-top"><span class="jperf-wv-lab">' . $meta['label'] . '</span>'
				. '<span class="jperf-wv-badge">' . $badge . '</span></div>'
				. '<div class="jperf-wv-val">' . $val . '</div>'
				. '<div class="jperf-wv-name">' . $meta['name'] . '</div>'
				. '<div class="jperf-wv-worst">最差 ' . $worst . '</div>'
				. '<div class="jperf-wv-opt" title="' . esc_attr( $meta['optimize'] ) . '"><b>优化</b> · ' . esc_html( $meta['optimize'] ) . '</div></div>';
		}
		if ( null !== $wv_score ) {
			$sr     = $wv_score['rate'];
			$sbadge = 'good' === $sr ? '优秀' : ( 'poor' === $sr ? '待提升' : '一般' );
			$html .= '<div class="jperf-wv-chip jperf-wv-score rate-' . $sr . '">'
				. '<div class="jperf-wv-top"><span class="jperf-wv-lab">综合体验评分</span>'
				. '<span class="jperf-wv-badge">' . $sbadge . '</span></div>'
				. '<div class="jperf-wv-val jperf-score-num" data-count="' . $wv_score['score'] . '">0</div>'
				. '<div class="jperf-wv-name">Field Performance Score · 满分 100</div>'
				. '<div class="jperf-wv-scorebar"><i style="width:' . $wv_score['score'] . '%"></i></div>'
				. '<div class="jperf-wv-note">5 项加权 · 近 7 天滚动均值</div></div>';
		}
		$foot = '基于 ' . number_format_i18n( $wv['n'] ) . ' 次真实访问';
		if ( empty( $wv['fresh'] ) ) {
			$foot .= '（窗口已过期，等待新样本）';
		}
		$html .= '<div class="jperf-wv-foot">' . $foot . '</div>';
		$html .= '<div class="jperf-wv-legend"><span class="lg good">良好</span><span class="lg mid">需优化</span>'
			. '<span class="lg poor">较差</span><span class="lg-note">除综合评分外，数值越低越好</span></div>';
		if ( ! empty( $wv['slowest'] ) && is_array( $wv['slowest'] ) ) {
			$html .= '<div class="jperf-slow">'
				. '<div class="jperf-slow-head"><span class="jperf-slow-t">最慢路径（按 LCP 最差）</span>'
				. '<span class="jperf-slow-hint">各路径真实访客的首屏最慢渲染时长</span></div>'
				. '<div class="jperf-slow-list">';
			$rank = 0;
			foreach ( $wv['slowest'] as $srow ) {
				$rank++;
				$srate  = jinyu_perf_wv_rate( (float) $srow['lcp_max'], $wvm['lcp'] );
				$sbadge = 'good' === $srate ? '良好' : ( 'poor' === $srate ? '较差' : '需优化' );
				$spct   = min( 100, (int) ( (float) $srow['lcp_max'] / (float) $wvm['lcp']['poor'] * 100 ) );
				$slcp   = number_format_i18n( (int) $srow['lcp_max'] ) . ' ms';
				$html  .= '<div class="jperf-slow-row rate-' . $srate . '">'
					. '<span class="jperf-slow-rank">' . $rank . '</span>'
					. '<span class="jperf-slow-path" title="' . esc_attr( $srow['path'] ) . '">' . esc_html( $srow['path'] ) . '</span>'
					. '<span class="jperf-slow-meta">最差 ' . $slcp . ' · ' . number_format_i18n( (int) $srow['n'] ) . ' 次</span>'
					. '<span class="jperf-slow-bar"><i style="width:' . $spct . '%"></i></span>'
					. '<span class="jperf-slow-badge">' . $sbadge . '</span></div>';
			}
			$html .= '</div></div>';
		}
	}
	$html .= '</div>';

	$html .= '</div>';
	return $html;
}

/* ───────────────────────── AJAX ───────────────────────── */

/** AJAX 公共门卫：权限 + nonce。失败直接中断响应。 */
function jinyu_perf_guard(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( [ 'msg' => '权限不足' ], 403 );
	}
	check_ajax_referer( 'jinyu_perf_nonce', 'nonce' );
}

add_action( 'wp_ajax_jinyu_perf_optimize', 'jinyu_perf_ajax_optimize' );

/** 一键优化：保存开关 → 清理 → 优化表 → 刷缓存 → 返回前后对比。 */
function jinyu_perf_ajax_optimize(): void {
	jinyu_perf_guard();

	$oc_b = jinyu_perf_opcache_stats();
	$mc_b = jinyu_perf_memcached_stats();
	$before = jinyu_perf_status();

	// 前端以 JSON 字符串提交开关集合，逐 key 白名单式写回（不在清单里的键直接丢弃）
	if ( isset( $_POST['options'] ) ) {
		$posted = json_decode( (string) wp_unslash( $_POST['options'] ), true );
		if ( is_array( $posted ) ) {
			$opts    = jinyu_perf_get_options();
			$allowed = array_keys( jinyu_perf_toggle_meta() );
			foreach ( $allowed as $k ) {
				$opts[ $k ] = ! empty( $posted[ $k ] ) ? 1 : 0;
			}
			update_option( 'jinyu_perf_options', $opts, false );
		}
	}

	$cleaned = jinyu_perf_clean_transients();
	$opt     = jinyu_perf_optimize_tables();
	$flush   = array_filter( jinyu_perf_flush_caches(), 'strlen' ); // 过滤空串（无整页缓存）
	$oc_a = jinyu_perf_opcache_stats();
	$mc_a = jinyu_perf_memcached_stats();
	$after   = jinyu_perf_status();

	// 前端展示用的人类可读对比（保留原始数值，展示层单独给格式化结果）
	$disp = static function ( array $s ): array {
		return [
			'autoload_bytes'    => jinyu_perf_human( (float) $s['autoload_bytes'] ),
			'transient_expired' => (int) $s['transient_expired'],
			'table_overhead'    => jinyu_perf_human( (float) $s['table_overhead'] ),
		];
	};

	// 命中率：清缓存后会明显回落，单独给前后对比
	$hit = static function ( $oc, $mc ): array {
		return [
			'opcache'   => $oc ? (float) $oc['hit_rate'] : null,
			'memcached' => ( $mc && ! empty( $mc['reachable'] ) ) ? (float) $mc['hit_rate'] : null,
		];
	};

	wp_send_json_success( [
		'before_d'           => $disp( $before ),
		'after_d'            => $disp( $after ),
		'before_hit'         => $hit( $oc_b, $mc_b ),
		'after_hit'          => $hit( $oc_a, $mc_a ),
		'cleaned_transients' => $cleaned,
		'optimized_tables'   => $opt['optimized'],
		'table_list'         => $opt['list'],
		'cache'              => $flush,
	] );
}

add_action( 'wp_ajax_jinyu_perf_flush', 'jinyu_perf_ajax_flush' );

/** 按需单独清缓存：target = opcache | memcached | page | all。 */
function jinyu_perf_ajax_flush(): void {
	jinyu_perf_guard();

	$target = isset( $_POST['target'] ) ? sanitize_key( (string) $_POST['target'] ) : 'all';
	switch ( $target ) {
		case 'opcache':
			$msg = jinyu_perf_reset_opcache();
			break;
		case 'memcached':
			$msg = jinyu_perf_flush_memcached();
			break;
		case 'page':
			$msg = jinyu_perf_flush_page_cache() ?: __( '本站未启用整页缓存插件，无需清理', 'jinyu' );
			break;
		default:
			$msg = implode( __( '；', 'jinyu' ), array_filter( jinyu_perf_flush_caches(), 'strlen' ) );
	}

		wp_send_json_success( [ 'msg' => $msg ] );
	}

/** 仅保存开关与数值配置（不跑清理/优化）。与「一键应用推荐优化」解耦，避免改动被吞。 */
function jinyu_perf_ajax_save(): void {
	jinyu_perf_guard();

	if ( isset( $_POST['options'] ) ) {
		$posted = json_decode( (string) wp_unslash( $_POST['options'] ), true );
		if ( is_array( $posted ) ) {
			$opts    = jinyu_perf_get_options();
			$allowed = array_keys( jinyu_perf_toggle_meta() );
			foreach ( $allowed as $k ) {
				$opts[ $k ] = ! empty( $posted[ $k ] ) ? 1 : 0;
			}
			update_option( 'jinyu_perf_options', $opts, false );
		}
	}
	wp_send_json_success( [ 'msg' => __( '设置已保存，下一次请求起生效', 'jinyu' ) ] );
}

/** 清空真实用户体验聚合（调试/重测用）。 */
function jinyu_perf_ajax_reset_wv(): void {
	jinyu_perf_guard();
	delete_option( 'jinyu_web_vitals_stats' );
	wp_send_json_success( [ 'msg' => '体验数据已清空' ] );
}

add_action( 'wp_ajax_jinyu_perf_save', 'jinyu_perf_ajax_save' );
add_action( 'wp_ajax_jinyu_perf_reset_wv', 'jinyu_perf_ajax_reset_wv' );
add_action( 'wp_ajax_jinyu_perf_status', 'jinyu_perf_ajax_status' );

/** 刷新状态看板：返回整块状态区 HTML（与页面初始渲染同一函数，杜绝两处漂移）。 */
function jinyu_perf_ajax_status(): void {
	jinyu_perf_guard();
	wp_send_json_success( [ 'html' => jinyu_perf_render_status_html() ] );
}

/* ───────────────────────── 评论懒加载：增量拉取下一页 ───────────────────────── */

/**
 * 「加载更多评论」增量接口（公开，游客可用）。
 * 复用 WP_Comment_Query 的分页语义（number/offset 作用于顶层评论，回复随父评论
 * 一并返回），再经 Walker_Comment 渲染，与主题原生评论回调/嵌套结构完全一致。
 * 仅校验来源文章与开关，不做权限限制（公开评论本就可读）。
 */
function jinyu_perf_ajax_load_comments() {
	$post_id = (int) ( $_GET['post_id'] ?? 0 );
	$page    = max( 1, (int) ( $_GET['page'] ?? 1 ) );
	$post    = get_post( $post_id );
	if ( ! $post || ! comments_open( $post ) ) {
		wp_send_json_error( 'invalid' );
	}
	$opt = jinyu_perf_get_options();
	if ( empty( $opt['comment_lazyload'] ) ) {
		wp_send_json_error( 'disabled' );
	}

	$per_page = (int) get_option( 'comments_per_page' );
	if ( $per_page < 1 ) {
		$per_page = 20;
	}
	$max_depth = get_option( 'thread_comments' ) ? (int) get_option( 'thread_comments_depth' ) : 0;

	$comments = get_comments( [
		'post_id'      => $post_id,
		'status'       => 'approve',
		'type'         => 'all',
		'hierarchical' => get_option( 'thread_comments' ) ? 'threaded' : false,
		'number'       => $per_page,
		'offset'       => ( $page - 1 ) * $per_page,
		'order'        => get_option( 'comment_order' ) === 'desc' ? 'DESC' : 'ASC',
	] );

	if ( ! class_exists( 'Walker_Comment' ) ) {
		wp_send_json_error( 'walker' );
	}

	$walker = new Walker_Comment();
	$html   = $walker->paged_walk(
		$comments,
		$max_depth,
		1, // 传入的 $comments 已是该页切片，按顶层计数从第 1 页开始渲染即整页
		$per_page,
		[
			'style'       => 'ol',
			'callback'    => 'jinyu_wp_comment',
			'avatar_size' => 48,
			'max_depth'   => $max_depth,
		]
	);

	wp_send_json_success( [ 'html' => $html, 'page' => $page ] );
}
add_action( 'wp_ajax_nopriv_jinyu_load_comments', 'jinyu_perf_ajax_load_comments' );
add_action( 'wp_ajax_jinyu_load_comments', 'jinyu_perf_ajax_load_comments' );

/* ───────────────────────── 页面渲染（高级浅色） ───────────────────────── */

function jinyu_perf_render_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$opts    = jinyu_perf_get_options();
	$nonce   = wp_create_nonce( 'jinyu_perf_nonce' );
	$toggles = jinyu_perf_toggle_meta();
	$st      = jinyu_perf_status();
	$oc_on   = ! empty( $st['object_cache'] );
	?>
	<div class="wrap jperf-wrap">
		<header class="jperf-topbar">
			<div class="jperf-brand">
				<span class="jperf-mark"><svg viewBox="0 0 24 24"><path d="M12 3l3 6 6 .9-4.5 4.2 1.1 6L12 17.8 6.4 20.1l1.1-6L3 9.9 9 9z"/></svg></span>
				<div>
					<h1><?php esc_html_e( '性能优化中心', 'jinyu' ); ?></h1>
					<div class="jperf-sub2"><?php esc_html_e( '金玉主题 · 运行时健康与一键优化', 'jinyu' ); ?></div>
				</div>
			</div>
			<div class="jperf-topright">
				<div class="jperf-topstatus">
					<span class="jperf-pill"><span class="jperf-dot <?php echo $oc_on ? 'ok' : 'off'; ?>"></span><?php echo $oc_on ? esc_html__( '对象缓存运行中', 'jinyu' ) : esc_html__( '对象缓存未启用', 'jinyu' ); ?></span>
					<span class="jperf-ts" id="jperf-refreshed-at"></span>
				</div>
				<button type="button" class="jperf-btn-ghost" id="jperf-refresh">
					<svg viewBox="0 0 24 24"><path d="M21 12a9 9 0 11-3-6.7M21 4v5h-5"/></svg><?php esc_html_e( '刷新看板', 'jinyu' ); ?>
				</button>
			</div>
		</header>

		<div id="jperf-status-zone"><?php echo jinyu_perf_render_status_html(); // 内部均为服务端构造的受控 HTML ?></div>

		<section class="jperf-block">
			<div class="jperf-block-head">
				<p class="jperf-eyebrow">优化开关</p>
				<span class="jperf-hint">每项均可单独开启 / 回退，不影响有效数据</span>
			</div>
			<div id="jperf-toggles">
				<?php
				$jperf_groups = [
					'front'    => __( '前端优化', 'jinyu' ),

					'admin'    => __( '后台减负', 'jinyu' ),

					'security' => __( '安全加固', 'jinyu' ),

					'cache'    => __( '缓存与评论', 'jinyu' ),

				];
				foreach ( $jperf_groups as $g_id => $g_name ) :
					$g_items = [];
					foreach ( $toggles as $key => $meta ) {
						if ( ( $meta['group'] ?? '' ) === $g_id ) {
							$g_items[ $key ] = $meta;
						}
					}
					if ( empty( $g_items ) ) {
						continue;
					}
				?>
				<div class="jgroup">
					<div class="jgroup-head">
						<span class="jgroup-tt"><?php echo esc_html( $g_name ); ?></span>
						<span class="jgroup-n"><?php echo (int) count( $g_items ); ?> 项</span>
					</div>
					<div class="jgrid">
						<?php foreach ( $g_items as $key => $meta ) : ?>
						<div class="jcard">
							<div class="jcard-body">
								<div class="jcard-t"><?php echo esc_html( $meta['label'] ); ?><?php if ( ! empty( $opts[ $key ] ) ) : ?><span class="jbadge">默认开</span><?php endif; ?></div>
								<div class="jcard-c"><?php echo esc_html( $meta['desc'] ); ?></div>
							</div>
							<div class="jperf-sw <?php echo ! empty( $opts[ $key ] ) ? 'on' : ''; ?>"
								data-key="<?php echo esc_attr( $key ); ?>"
								role="switch" aria-checked="<?php echo ! empty( $opts[ $key ] ) ? 'true' : 'false'; ?>"
								tabindex="0"></div>
						</div>
						<?php endforeach; ?>
					</div>
				</div>
				<?php endforeach; ?>
			</div>
			<div class="jperf-btnrow">
				<button type="button" id="jperf-save" class="jperf-btn jperf-btn-primary">
					<svg viewBox="0 0 24 24"><path d="M5 3h11l3 3v15H5z"/><path d="M8 3v5h6M8 13h8M8 17h5"/></svg><span>保存设置</span>
				</button>
				<button type="button" id="jperf-run" class="jperf-btn">
					<svg viewBox="0 0 24 24"><path d="M13 2L3 14h7l-1 8 10-12h-7z"/></svg><span>一键应用推荐优化</span>
				</button>
				<span id="jperf-unsaved" class="jperf-unsaved" hidden>● 有改动未保存</span>
			</div>
			<p class="jperf-hint" style="margin-top:10px">「推荐优化」= 勾选项全部保存 + 清理过期 transient + 优化碎片表 + 重置 OPcache 与 Memcached。也可单独点「保存设置」仅保存开关。</p>
			<div id="jperf-result" class="jperf-result" aria-live="polite"></div>
		</section>

		<div class="jperf-grid2">
			<section class="jperf-card">
				<div class="jperf-card-head">
					<span class="jperf-card-ico"><svg viewBox="0 0 24 24"><path d="M4 6c0-1.7 3.6-3 8-3s8 1.3 8 3-3.6 3-8 3-8-1.3-8-3z"/><path d="M4 6v12c0 1.7 3.6 3 8 3s8-1.3 8-3V6"/><path d="M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/></svg></span>
					<div class="jperf-card-tt">
						<h3>缓存管理</h3>
						<p>部署代码后建议「清除全部缓存」；各层缓存互相独立，可按需单清。</p>
					</div>
				</div>
				<div class="jperf-actions">
					<button type="button" class="jperf-btn" data-flush="opcache">
						<svg viewBox="0 0 24 24"><path d="M12 2a10 10 0 109 6M12 6v6l4 2"/></svg><span>清除 OPcache</span>
					</button>
					<button type="button" class="jperf-btn" data-flush="memcached">
						<svg viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h10"/></svg><span>清除 Memcached</span>
					</button>
					<button type="button" class="jperf-btn" data-flush="page">
						<svg viewBox="0 0 24 24"><path d="M4 4h16v16H4zM4 9h16"/></svg><span>清除整页缓存</span>
					</button>
					<button type="button" class="jperf-btn jperf-btn-hero" data-flush="all">
						<svg viewBox="0 0 24 24"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg><span>清除全部缓存</span>
					</button>
				</div>
				<div id="jperf-cache-result" class="jperf-result" aria-live="polite"></div>
			</section>

			<section class="jperf-card">
				<div class="jperf-card-head">
					<span class="jperf-card-ico"><svg viewBox="0 0 24 24"><path d="M3 12h4l2.5-6 4 12 2.5-6H21"/></svg></span>
					<div class="jperf-card-tt">
						<h3>体验数据</h3>
						<p>真实用户体验聚合（近 7 天滚动），清空后随新访客浏览重新累积。</p>
					</div>
				</div>
				<div class="jperf-actions">
					<button type="button" class="jperf-btn jperf-btn-danger" id="jperf-reset-wv">
						<svg viewBox="0 0 24 24"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg><span>清空体验数据</span>
					</button>
				</div>
				<div id="jperf-wv-result" class="jperf-result" aria-live="polite"></div>
			</section>
		</div>
	</div>

	<style>
		.jperf-wrap{
			--j-bg:#ffffff; --j-line:#ececef; --j-line2:#f3f3f5;
			--j-ink:#18181b; --j-ink2:#52525b; --j-muted:#a1a1aa; --j-faint:#d4d4d8;
			--j-accent:#4f46e5; --j-accent-soft:#eef0fe; --j-ok:#16a34a; --j-r:14px;
			--j-dot-glow:rgba(22,163,74,.5);
			width:100%; color:var(--j-ink);
			font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","Inter",system-ui,"PingFang SC","Microsoft YaHei",sans-serif;
		}
		.jperf-wrap *{box-sizing:border-box}
		.jperf-wrap h1{font-size:16px;font-weight:600;letter-spacing:-.01em;margin:0}
		.jperf-sub2{font-size:12px;color:var(--j-muted);font-weight:400;margin-top:2px}

		/* 顶栏 */
		.jperf-topbar{display:flex;align-items:center;justify-content:space-between;
			padding:26px 0 22px;border-bottom:1px solid var(--j-line);margin-bottom:34px}
		.jperf-brand{display:flex;align-items:center;gap:11px}
		.jperf-mark{width:26px;height:26px;border-radius:7px;background:var(--j-ink);
			display:flex;align-items:center;justify-content:center;flex:none}
		.jperf-mark svg{width:14px;height:14px;stroke:#fff;stroke-width:2;fill:none}
		.jperf-topright{display:flex;align-items:center;gap:16px}
		.jperf-topstatus{display:flex;align-items:center;gap:14px}
		.jperf-pill{display:inline-flex;align-items:center;gap:6px;font-size:12px;color:var(--j-ink2);
			background:var(--j-bg);border:1px solid var(--j-line);border-radius:999px;padding:5px 11px}
		.jperf-dot{width:7px;height:7px;border-radius:50%;background:var(--j-ok);animation:jperfPulse 2.2s infinite}
		.jperf-ts{font-size:11.5px;color:var(--j-muted);font-variant-numeric:tabular-nums}
		.jperf-btn-ghost{font:inherit;font-size:13px;color:var(--j-ink);background:var(--j-bg);
			border:1px solid var(--j-line);border-radius:9px;padding:7px 13px;cursor:pointer;margin-left:auto;
			display:inline-flex;align-items:center;gap:6px;transition:.15s}
		.jperf-btn-ghost:hover{border-color:#d8d8dd;background:#fafafb}
		.jperf-btn-ghost svg{width:13px;height:13px;stroke:var(--j-ink2);stroke-width:2;fill:none}

		/* 指标条 */
		.jperf-stats{display:grid;grid-template-columns:repeat(4,1fr);margin-bottom:36px;
			background:var(--j-bg);border:1px solid var(--j-line);border-radius:var(--j-r);
			box-shadow:0 1px 2px rgba(24,24,27,.04);overflow:hidden}
		.jperf-stat{padding:20px 22px;border-right:1px solid var(--j-line2)}
		.jperf-stat:last-child{border-right:none}
		.jperf-stat .jperf-k{font-size:11px;letter-spacing:.05em;text-transform:uppercase;color:var(--j-muted);font-weight:600}
		.jperf-stat .jperf-v{font-size:27px;font-weight:600;letter-spacing:-.02em;margin-top:9px;font-variant-numeric:tabular-nums}
		.jperf-stat .jperf-v small{font-size:14px;color:var(--j-muted);font-weight:500;margin-left:2px}
		.jperf-stat .jperf-d{font-size:11.5px;color:var(--j-muted);margin-top:4px}

		/* eyebrow + 面板 */
		.jperf-eyebrow{font-size:11px;letter-spacing:.07em;text-transform:uppercase;color:var(--j-muted);
			font-weight:600;margin:0 0 14px}
		.jperf-eyebrow-mt{margin-top:0}
		.jperf-panels{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:38px}
		@media(max-width:760px){.jperf-panels{grid-template-columns:1fr}}
		.jperf-panel{background:var(--j-bg);border:1px solid var(--j-line);border-radius:var(--j-r);
			box-shadow:0 1px 2px rgba(24,24,27,.04);padding:22px}
		.jperf-panel-head{display:flex;align-items:center;gap:9px;margin-bottom:18px}
		.jperf-panel-head svg{width:15px;height:15px;stroke:var(--j-ink2);stroke-width:1.8;fill:none}
		.jperf-panel-head h3{font-size:13.5px;font-weight:600;letter-spacing:-.01em;margin:0}
		.jperf-tag{margin-left:auto;font-size:11px;color:var(--j-muted);
			border:1px solid var(--j-line);border-radius:6px;padding:2px 7px}

		/* 环形图 */
		.jperf-gauge{display:flex;align-items:center;gap:22px}
		.jperf-ring{position:relative;width:108px;height:108px;flex:none}
		.jperf-ring svg{width:100%;height:100%;transform:rotate(-90deg)}
		.jperf-track{fill:none;stroke:var(--j-faint);stroke-width:9}
		.jperf-bar{fill:none;stroke-width:9;stroke-linecap:round;stroke-dasharray:289;stroke-dashoffset:289;
			transition:stroke-dashoffset 1.1s cubic-bezier(.22,1,.36,1)}
		.jperf-ctr{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center}
		.jperf-pct{font-size:23px;font-weight:600;letter-spacing:-.02em;font-variant-numeric:tabular-nums}
		.jperf-pct u{font-size:13px;text-decoration:none;color:var(--j-muted);font-weight:500}
		.jperf-rlbl{font-size:10.5px;color:var(--j-muted);letter-spacing:.03em;margin-top:1px}

		/* 键值 */
		.jperf-kv{flex:1;display:flex;flex-direction:column;gap:9px;min-width:0}
		.jperf-kvrow{display:flex;align-items:baseline;justify-content:space-between;
			font-size:12.5px;border-bottom:1px dashed var(--j-line2);padding-bottom:8px}
		.jperf-kvrow:last-child{border-bottom:none;padding-bottom:0}
		.jperf-name{color:var(--j-ink2)}
		.jperf-val{font-weight:600;font-variant-numeric:tabular-nums;letter-spacing:-.01em;text-align:right}
		.jperf-meter{height:5px;background:var(--j-faint);border-radius:3px;overflow:hidden;margin-top:3px;margin-bottom:2px}
		.jperf-meter i{display:block;height:100%;background:var(--j-accent);border-radius:3px;
			transition:width 1.1s cubic-bezier(.22,1,.36,1)}

		/* 区块 + 开关列表 */
		.jperf-block{margin-bottom:38px}
		.jperf-block-head{display:flex;align-items:baseline;justify-content:space-between;margin-bottom:14px}
		/* 底部操作卡片区（缓存管理 / 体验数据） */
		.jperf-grid2{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:38px}
		@media(max-width:900px){.jperf-grid2{grid-template-columns:1fr}}
		.jperf-card{background:var(--j-bg);border:1px solid var(--j-line);border-radius:var(--j-r);
			box-shadow:0 1px 2px rgba(24,24,27,.04);padding:20px 22px}
		.jperf-card-head{display:flex;align-items:flex-start;gap:11px;margin-bottom:16px}
		.jperf-card-ico{width:30px;height:30px;border-radius:9px;background:var(--j-accent-soft);
			color:var(--j-accent);display:flex;align-items:center;justify-content:center;flex:none}
		.jperf-card-ico svg{width:15px;height:15px;stroke:currentColor;stroke-width:1.8;fill:none}
		.jperf-card-tt h3{font-size:13.5px;font-weight:600;letter-spacing:-.01em;margin:0}
		.jperf-card-tt p{font-size:11.5px;color:var(--j-muted);margin:3px 0 0;line-height:1.5}
		.jperf-block-head .jperf-hint{font-size:12px;color:var(--j-muted)}
		/* 开关区：分组双栏卡片 */
		.jgroup{margin-bottom:20px}
		.jgroup:last-child{margin-bottom:0}
		.jgroup-head{display:flex;align-items:baseline;gap:9px;margin:0 0 11px;padding-left:2px}
		.jgroup-tt{font-size:13.5px;font-weight:650;letter-spacing:-.01em}
		.jgroup-n{font-size:11px;color:var(--j-muted)}
		.jgrid{display:grid;grid-template-columns:1fr 1fr;gap:11px}
		.jcard{display:flex;align-items:flex-start;gap:13px;padding:14px 15px;background:var(--j-bg);
			border:1px solid var(--j-line);border-radius:12px;box-shadow:0 1px 2px rgba(24,24,27,.04);
			transition:border-color .15s,box-shadow .15s,transform .15s}
		.jcard:hover{border-color:#dcdce2;box-shadow:0 6px 16px -10px rgba(24,24,27,.3);transform:translateY(-1px)}
		.jcard-body{flex:1;min-width:0}
		.jcard-t{font-size:13.5px;font-weight:550;letter-spacing:-.01em;display:flex;align-items:center;gap:7px;flex-wrap:wrap}
		.jbadge{font-size:10px;font-weight:600;color:var(--j-accent);background:var(--j-accent-soft);
			border:1px solid #dfe2fc;border-radius:6px;padding:1px 6px}
		.jcard-c{font-size:11.5px;color:var(--j-muted);margin-top:3px;line-height:1.5}
		@media (max-width:760px){ .jgrid{grid-template-columns:1fr} }
		.jperf-sw{flex:none;position:relative;width:42px;height:24px;border-radius:999px;
			background:var(--j-faint);cursor:pointer;transition:background .2s;margin-top:2px}
		.jperf-sw::after{content:"";position:absolute;top:3px;left:3px;width:18px;height:18px;
			border-radius:50%;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.18);transition:transform .2s}
		.jperf-sw.on{background:var(--j-accent)}
		.jperf-sw.on::after{transform:translateX(18px)}
		.jperf-sw:focus-visible{outline:2px solid var(--j-accent);outline-offset:2px}

		/* 数值字段（整页缓存有效期） */
		.jperf-field{display:flex;align-items:center;gap:12px;padding:16px 20px;
			background:var(--j-bg);border:1px solid var(--j-line);border-radius:var(--j-r);
			box-shadow:0 1px 2px rgba(24,24,27,.04);margin-top:14px}
		.jperf-field-label{font-size:13.5px;font-weight:550;letter-spacing:-.01em;flex:none}
		.jperf-input{width:120px;font:inherit;font-size:13px;color:var(--j-ink);
			border:1px solid var(--j-line);border-radius:9px;padding:7px 11px;background:var(--j-bg)}
		.jperf-input:focus{outline:none;border-color:var(--j-accent)}
		.jperf-field-hint{font-size:12px;color:var(--j-muted)}

		/* 按钮 */
		.jperf-actions{display:flex;flex-wrap:wrap;gap:10px}
		.jperf-btn{border:1px solid var(--j-line);background:var(--j-bg);color:var(--j-ink);
			font:inherit;font-size:13px;font-weight:500;border-radius:10px;padding:10px 16px;
			cursor:pointer;display:inline-flex;align-items:center;gap:7px;
			transition:transform .15s,box-shadow .15s,border-color .15s,background .15s,color .15s}
		.jperf-btn:hover{border-color:#d8d8dd;background:#fafafb;transform:translateY(-1px);
			box-shadow:0 6px 14px -8px rgba(24,24,27,.35)}
		.jperf-btn:active{transform:translateY(0) scale(.97);box-shadow:none;transition-duration:.06s}
		.jperf-btn svg{width:14px;height:14px;stroke:var(--j-ink2);stroke-width:1.9;fill:none}
		.jperf-btn-primary{background:var(--j-accent);border-color:var(--j-accent);color:#fff}
		.jperf-btn-primary:hover{background:#4338ca;border-color:#4338ca;box-shadow:0 6px 16px -8px rgba(79,70,229,.6)}
		.jperf-btn-primary svg{stroke:#fff}
		.jperf-btn-hero{background:var(--j-accent-soft);border-color:#dfe2fc;color:var(--j-accent)}
		.jperf-btn-hero svg{stroke:var(--j-accent)}
		.jperf-btn-hero:hover{background:#e7e9fe;border-color:#ccd2fb;box-shadow:0 6px 14px -8px rgba(79,70,229,.45)}
		.jperf-btn-danger{color:#dc2626}
		.jperf-btn-danger svg{stroke:#dc2626}
		.jperf-btn-danger:hover{border-color:#f3b4b4;background:#fdf3f3;box-shadow:0 6px 14px -8px rgba(220,38,38,.4)}

		/* 结果 */
		.jperf-result{margin-top:14px;font-size:13px;line-height:1.8;display:none}
		.jperf-result.show{display:block;animation:rise .3s ease}
		@keyframes rise{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}

		.jperf-btnrow{display:flex;flex-wrap:wrap;gap:10px;align-items:center;
			border-top:1px solid var(--j-line);margin-top:20px;padding-top:16px}
		.jperf-unsaved{display:inline-flex;align-items:center;font-size:12px;font-weight:600;color:#b45309;
			background:#fffbeb;border:1px solid #fde68a;border-radius:999px;padding:3px 10px}
		.jperf-unsaved[hidden]{display:none}
		.jperf-unsaved.saved{color:#15803d;background:#f0fdf4;border-color:#bbf7d0}
		@keyframes jperfIn{from{opacity:0;transform:translateY(12px) scale(.985)}to{opacity:1;transform:none}}
		@keyframes jperfPulse{0%{box-shadow:0 0 0 0 var(--j-dot-glow)}70%{box-shadow:0 0 0 7px transparent}100%{box-shadow:0 0 0 0 transparent}}
		.jperf-stat,.jperf-panel,.jperf-block,.jperf-wv-chip,.jperf-card{animation:jperfIn .55s cubic-bezier(.22,1,.36,1) both}
		.jperf-stats .jperf-stat:nth-child(1){animation-delay:.03s}
		.jperf-stats .jperf-stat:nth-child(2){animation-delay:.07s}
		.jperf-stats .jperf-stat:nth-child(3){animation-delay:.11s}
		.jperf-stats .jperf-stat:nth-child(4){animation-delay:.15s}
		.jperf-wv-chip:nth-child(1){animation-delay:.05s}
		.jperf-wv-chip:nth-child(2){animation-delay:.09s}
		.jperf-wv-chip:nth-child(3){animation-delay:.13s}
		.jperf-wv-chip:nth-child(4){animation-delay:.17s}
		.jperf-wv-chip:nth-child(5){animation-delay:.21s}
		.jperf-wv-chip:nth-child(6){animation-delay:.25s}
		.jperf-panel:nth-child(1){animation-delay:.06s}
		.jperf-panel:nth-child(2){animation-delay:.13s}
		.jperf-result .item{display:flex;align-items:center;gap:8px;color:var(--j-ink2);
			padding:5px 0;border-bottom:1px solid var(--j-line2)}
		.jperf-result .item:last-child{border-bottom:none}
		.jperf-result .item svg{width:13px;height:13px;stroke:var(--j-ok);stroke-width:2.4;fill:none;flex:none}
		.jperf-result .item .b{margin-left:auto;font-variant-numeric:tabular-nums;color:var(--j-ink);font-weight:600}
		.jperf-hint{color:var(--j-muted);font-size:12px;line-height:1.6;margin:0}

		/* Web Vitals 真实用户体验 */
		.jperf-wv{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:38px}
		@media(max-width:760px){.jperf-wv{grid-template-columns:repeat(2,1fr)}}
		@media(max-width:480px){.jperf-wv{grid-template-columns:1fr}}
		.jperf-wv-empty{grid-column:1/-1;background:var(--j-bg);border:1px dashed var(--j-line);
			border-radius:var(--j-r);padding:18px 20px;font-size:12.5px;color:var(--j-muted);line-height:1.6}
		.jperf-wv-chip{position:relative;overflow:hidden;background:var(--j-bg);border:1px solid var(--j-line);
			border-radius:var(--j-r);padding:15px 15px 13px;box-shadow:0 1px 2px rgba(24,24,27,.04);
			transition:transform .25s cubic-bezier(.22,1,.36,1),box-shadow .25s,border-color .25s}
		.jperf-wv-chip:hover{transform:translateY(-4px);box-shadow:0 14px 30px -14px rgba(24,24,27,.3);border-color:transparent}
		.jperf-wv-chip:hover::before{width:5px;box-shadow:0 0 12px 0 var(--wv-c)}
		/* 触屏：hover 粘滞中和，改用点按轻反馈 */
		@media(hover:none){
			.jperf-wv-chip:hover{transform:none;box-shadow:0 1px 2px rgba(24,24,27,.04);border-color:var(--j-line)}
			.jperf-wv-chip:hover::before{width:3px;box-shadow:none}
		}
		.jperf-wv-chip:active{transform:scale(.98);transition:transform .12s}
		.jperf-wv-chip::before{content:"";position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--wv-c);transition:width .25s,box-shadow .25s}
		.jperf-wv-chip.rate-good{--wv-c:#16a34a}
		.jperf-wv-chip.rate-mid{--wv-c:#d97706}
		.jperf-wv-chip.rate-poor{--wv-c:#dc2626}
		.jperf-wv-score{background:linear-gradient(160deg,var(--j-bg),var(--j-accent-soft))}
		.jperf-wv-score .jperf-wv-val{color:var(--j-ink);font-size:36px;line-height:1;margin-top:8px;font-weight:700}
		.jperf-wv-scorebar{height:5px;background:var(--j-faint);border-radius:3px;overflow:hidden;margin-top:11px}
		.jperf-wv-scorebar i{display:block;height:100%;background:var(--wv-c);border-radius:3px;
			animation:jperfBar 1s cubic-bezier(.22,1,.36,1) .25s both}
		@keyframes jperfBar{from{width:0}}
		.jperf-wv-top{display:flex;align-items:center;justify-content:space-between;gap:8px}
		.jperf-wv-lab{font-size:12px;font-weight:700;letter-spacing:.02em;color:var(--j-ink)}
		.jperf-wv-badge{font-size:10px;font-weight:600;color:var(--wv-c);
			background:rgba(22,163,74,.12);border-radius:999px;padding:2px 8px;white-space:nowrap}
		.jperf-wv-chip.rate-mid .jperf-wv-badge{background:rgba(217,119,6,.14)}
		.jperf-wv-chip.rate-poor .jperf-wv-badge{background:rgba(220,38,38,.12)}
		.jperf-wv-val{font-size:22px;font-weight:600;letter-spacing:-.02em;margin-top:11px;
			font-variant-numeric:tabular-nums;color:var(--wv-c)}
		.jperf-wv-name{font-size:11.5px;color:var(--j-ink2);margin-top:3px}
		.jperf-wv-worst{font-size:10.5px;color:var(--j-muted);margin-top:6px;font-variant-numeric:tabular-nums}
		.jperf-wv-opt{font-size:10.5px;color:var(--j-muted);margin-top:8px;line-height:1.5;
			display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
		.jperf-wv-opt b{color:var(--j-accent);font-weight:600;margin-right:1px}
		.jperf-wv-note{font-size:10.5px;color:var(--j-muted);margin-top:9px;line-height:1.5}
		.jperf-wv-foot{grid-column:1/-1;font-size:11.5px;color:var(--j-muted);margin-top:2px}
		.jperf-wv-legend{grid-column:1/-1;display:flex;flex-wrap:wrap;align-items:center;gap:8px 14px;margin-top:10px;
			padding-top:12px;border-top:1px dashed var(--j-line);font-size:11px;color:var(--j-muted)}
		.jperf-wv-legend .lg{display:inline-flex;align-items:center;gap:5px;font-weight:600}
		.jperf-wv-legend .lg::before{content:"";width:8px;height:8px;border-radius:50%}
		.jperf-wv-legend .lg.good{color:#16a34a}.jperf-wv-legend .lg.good::before{background:#16a34a}
		.jperf-wv-legend .lg.mid{color:#d97706}.jperf-wv-legend .lg.mid::before{background:#d97706}
		.jperf-wv-legend .lg.poor{color:#dc2626}.jperf-wv-legend .lg.poor::before{background:#dc2626}
		.jperf-wv-legend .lg-note{margin-left:auto}

		/* 最慢路径 */
		.jperf-slow{grid-column:1/-1;margin-top:16px;background:var(--j-bg);border:1px solid var(--j-line);border-radius:var(--j-r);
			padding:15px 18px;box-shadow:0 1px 2px rgba(24,24,27,.04)}
		.jperf-slow-head{display:flex;align-items:baseline;gap:10px;margin-bottom:11px;flex-wrap:wrap}
		.jperf-slow-t{font-size:12.5px;font-weight:600;color:var(--j-ink)}
		.jperf-slow-hint{font-size:11px;color:var(--j-muted)}
		.jperf-slow-list{display:flex;flex-direction:column}
		.jperf-slow-row{display:flex;align-items:center;gap:11px;padding:9px 0;border-bottom:1px solid var(--j-line2)}
		.jperf-slow-row:last-child{border-bottom:none}
		.jperf-slow-row.rate-good{--wv-c:#16a34a}
		.jperf-slow-row.rate-mid{--wv-c:#d97706}
		.jperf-slow-row.rate-poor{--wv-c:#dc2626}
		.jperf-slow-rank{flex:none;width:20px;height:20px;border-radius:6px;background:var(--j-line2);
			color:var(--j-muted);font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center}
		.jperf-slow-path{flex:1;min-width:0;font-size:12.5px;color:var(--j-ink);font-variant-numeric:tabular-nums;
			overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
		.jperf-slow-meta{flex:none;font-size:11px;color:var(--j-muted);font-variant-numeric:tabular-nums;white-space:nowrap}
		.jperf-slow-bar{flex:none;width:72px;height:6px;background:var(--j-faint);border-radius:3px;overflow:hidden}
		.jperf-slow-bar i{display:block;height:100%;border-radius:3px;background:var(--wv-c);transition:width .9s cubic-bezier(.22,1,.36,1)}
		.jperf-slow-badge{flex:none;font-size:10px;font-weight:600;color:var(--wv-c);background:rgba(22,163,74,.12);
			border-radius:999px;padding:2px 8px;white-space:nowrap;text-align:center}
		.jperf-slow-row.rate-mid .jperf-slow-badge{background:rgba(217,119,6,.14)}
		.jperf-slow-row.rate-poor .jperf-slow-badge{background:rgba(220,38,38,.12)}
		@media(max-width:480px){
			.jperf-slow-meta{display:none}
			.jperf-slow-bar{width:54px}
		}

		@media(max-width:1024px){
			.jperf-stats{grid-template-columns:repeat(2,1fr)}
			.jperf-stat:nth-child(2n){border-right:none}
			.jperf-stat:nth-child(odd){border-bottom:1px solid var(--j-line2)}
		}
		@media(max-width:680px){
			.jperf-stats{grid-template-columns:repeat(2,1fr)}
			.jperf-stat:nth-child(2n){border-right:none}
			.jperf-panel{padding:16px}
			.jperf-panel-head{margin-bottom:14px}
			.jperf-gauge{gap:14px}
			.jperf-ring{width:92px;height:92px}
			.jperf-pct{font-size:20px}
			.jperf-pct u{font-size:11px}
			.jperf-kv{gap:7px}
			.jperf-kvrow{font-size:12px;padding-bottom:6px}
			.jperf-topbar{flex-direction:column;align-items:stretch;gap:14px;padding:20px 0 18px}
			.jperf-topright{flex-direction:column;align-items:stretch;gap:12px;width:100%}
			.jperf-topstatus{flex-wrap:wrap;gap:8px}
			.jperf-pill{flex:1 1 calc(50% - 4px);justify-content:center}
			.jperf-ts{order:3;flex:1 1 auto;white-space:nowrap}
			.jperf-btn-ghost{width:100%;justify-content:center;margin-left:0}
			.jperf-block-head{flex-direction:column;align-items:flex-start;gap:5px}
			.jperf-field{flex-direction:column;align-items:stretch;gap:9px}
			.jperf-input{width:100%}
			.jperf-actions{gap:8px}
			.jperf-actions .jperf-btn{flex:1 1 calc(50% - 4px);justify-content:center}
		}
		@media(max-width:480px){
			.jperf-topbar{padding:18px 0 16px}
			.jperf-mark{width:24px;height:24px}
			.jperf-mark svg{width:13px;height:13px}
			.jperf-ring{width:80px;height:80px}
			.jperf-pct{font-size:17px}
			.jperf-rlbl{font-size:9.5px}
			.jperf-kvrow{font-size:11.5px;gap:10px}
			.jperf-val{overflow-wrap:anywhere}
		}

		/* 暗色模式：跟随系统配色（prefers-color-scheme） */
		@media (prefers-color-scheme: dark){
			.jperf-wrap{
				--j-bg:#161619; --j-line:#2c2c34; --j-line2:#232329;
				--j-ink:#f4f4f5; --j-ink2:#a1a1aa; --j-muted:#71717a; --j-faint:#3f3f46;
				--j-accent:#818cf8; --j-accent-soft:#22222c; --j-ok:#4ade80; --j-r:14px;
			--j-dot-glow:rgba(74,222,128,.5);
			}
			.jperf-mark{background:var(--j-ink)}
			.jperf-mark svg{stroke:#161619}
			.jperf-btn-ghost:hover{background:#1d1d22;border-color:#3a3a42}
			.jperf-btn:hover{border-color:#3a3a42;background:#1d1d22}
			.jperf-btn-primary:hover{background:#6366f1;border-color:#6366f1}
			.jperf-btn-hero{border-color:#3e4194}
			.jperf-btn-hero:hover{background:#26284f;border-color:#4d50b0;box-shadow:none}
			.jperf-btn-danger{color:#f87171}
			.jperf-btn-danger svg{stroke:#f87171}
			.jperf-btn-danger:hover{border-color:#7f2b2b;background:#2a1717;box-shadow:none}
			.jperf-wv-empty{border-color:var(--j-line)}
			.jperf-meter i{background:var(--j-accent)}
			.jperf-unsaved{color:#fbbf24;background:#2a2110;border-color:#4d3a12}
			.jperf-unsaved.saved{color:#4ade80;background:#102a18;border-color:#1c4a2e}
		}
	</style>

	<script>
	(function(){
		'use strict';
		var NONCE = <?php echo wp_json_encode( $nonce ); ?>;

		/** 统一 POST 封装，失败文案集中处理。 */
		function post(action, extra){
			var fd = new FormData();
			fd.append('action', action);
			fd.append('nonce', NONCE);
			if (extra) { for (var k in extra) { fd.append(k, extra[k]); } }
			return fetch(ajaxurl, {method:'POST', body: fd, credentials:'same-origin'}).then(function(r){ return r.json(); });
		}

	/** 环形图 + 中心数字绘制动画（页面加载与看板刷新后各跑一次）。 */
	function animateBoards(){
		var C = 289;
		document.querySelectorAll('#jperf-status-zone .jperf-bar').forEach(function(bar){
			if (bar.getAttribute('data-null')) { return; }
			var pct = parseFloat(bar.getAttribute('data-pct')) || 0;
			requestAnimationFrame(function(){ bar.style.strokeDashoffset = C * (1 - pct / 100); });
		});
		document.querySelectorAll('#jperf-status-zone .jperf-pct').forEach(function(el){
			if (el.getAttribute('data-null')) { return; }
			var pct = parseFloat(el.getAttribute('data-pct')) || 0, cur = 0,
				step = Math.max(0.5, pct / 28),
				t = setInterval(function(){
					cur += step;
					if (cur >= pct) { cur = pct; clearInterval(t); }
					el.innerHTML = (cur % 1 === 0 ? cur : cur.toFixed(1)) + '<u>%</u>';
				}, 20);
		});
		countUpAll();
	}

	/** 数值从 0 缓动到目标：stats 命中率 / 综合评分卡。 */
	function countUpAll(){
		document.querySelectorAll('#jperf-status-zone [data-count]').forEach(function(el){
			var target = parseFloat(el.getAttribute('data-count')) || 0, dur = 950, start = null;
			function tick(ts){
				if (start === null) start = ts;
				var p = Math.min(1, (ts - start) / dur), e = 1 - Math.pow(1 - p, 3);
				el.textContent = Math.round(target * e);
				if (p < 1) { requestAnimationFrame(tick); }
				else { el.textContent = target; }
			}
			requestAnimationFrame(tick);
		});
	}

		/** 结果区渲染：textContent 逐条写入（无 innerHTML 拼接，杜绝注入/裂图）。 */
		function showResult(id, lines){
			var box = document.getElementById(id);
			box.innerHTML = '';
			box.classList.add('show');
			lines.forEach(function(t){
				var d = document.createElement('div');
				d.className = 'item';
				// 兼容「标签 | 值」结构：用 b 标记数值
				var i = document.createElementNS('http://www.w3.org/2000/svg','svg');
				i.setAttribute('viewBox','0 0 24 24');
				var p = document.createElementNS('http://www.w3.org/2000/svg','path');
				p.setAttribute('d','M20 6L9 17l-5-5');
				i.appendChild(p);
				d.appendChild(i);
				var span = document.createElement('span');
				span.textContent = t;
				d.appendChild(span);
				box.appendChild(d);
			});
		}

		function showErr(id, msg){
			var box = document.getElementById(id);
			box.innerHTML = '';
			box.classList.add('show');
			var d = document.createElement('div');
			d.className = 'item';
			d.textContent = '失败：' + msg;
			box.appendChild(d);
		}

		/** 刷新状态看板：整块替换（服务端同一渲染函数，结构零漂移）。 */
		function refreshStatus(){
			return post('jinyu_perf_status').then(function(j){
				if (j.success) {
					document.getElementById('jperf-status-zone').innerHTML = j.data.html;
					animateBoards();
					var at = document.getElementById('jperf-refreshed-at');
					if (at) { at.textContent = '更新于 ' + new Date().toLocaleTimeString(); }
				}
			});
		}

		var unsaved = document.getElementById('jperf-unsaved');
		function markUnsaved(){ if (unsaved) { unsaved.hidden = false; } }

		// 开关：点击 + 键盘可达
		document.querySelectorAll('#jperf-toggles .jperf-sw').forEach(function(sw){
			function toggle(){
				sw.classList.toggle('on');
				sw.setAttribute('aria-checked', sw.classList.contains('on') ? 'true' : 'false');
				markUnsaved();
			}
			sw.addEventListener('click', toggle);
			sw.addEventListener('keydown', function(e){
				if (e.key === ' ' || e.key === 'Enter') { e.preventDefault(); toggle(); }
			});
		});

		// 一键应用推荐优化
		var run = document.getElementById('jperf-run');
		if (run) {
			run.addEventListener('click', function(){
				var opts = {};
				document.querySelectorAll('#jperf-toggles .jperf-sw').forEach(function(sw){
					opts[sw.dataset.key] = sw.classList.contains('on') ? 1 : 0;
				});
				run.disabled = true;
				var lbl = run.querySelector('span');
				var old = lbl.textContent;
				lbl.textContent = '优化中…';
				post('jinyu_perf_optimize', {options: JSON.stringify(opts)})
					.then(function(j){
						run.disabled = false; lbl.textContent = old;
						if (!j.success) { showErr('jperf-result', j.data && j.data.msg ? j.data.msg : '未知错误'); return; }
						var d = j.data, lines = [];
						lines.push('清理过期 transient：' + d.cleaned_transients + ' 条');
						lines.push('优化数据表：' + d.optimized_tables + ' 张' + (d.table_list && d.table_list.length ? '（' + d.table_list.join('、') + '）' : ''));
						(d.cache || []).forEach(function(m){ lines.push(m); });
						var fmtHit = function(name, b, a){
							if (b === null || b === undefined) return name + '：未启用';
							return name + '：' + b + '% → ' + a + '%';
						};
						lines.push(fmtHit('OPcache 命中率', d.before_hit.opcache, d.after_hit.opcache));
						lines.push(fmtHit('Memcached 命中率', d.before_hit.memcached, d.after_hit.memcached));
						lines.push('自动加载选项：' + d.before_d.autoload_bytes + ' → ' + d.after_d.autoload_bytes);
						lines.push('过期 transient：' + d.before_d.transient_expired + ' → ' + d.after_d.transient_expired + ' 条');
						lines.push('数据表碎片：' + d.before_d.table_overhead + ' → ' + d.after_d.table_overhead);
						lines.push('开关已保存，下一次请求起生效。');
						showResult('jperf-result', lines);
						refreshStatus();
					})
					.catch(function(e){ run.disabled = false; lbl.textContent = old; showErr('jperf-result', e); });
			});
		}

		// 保存设置（仅落库开关与 TTL，不跑清理/优化）
		var save = document.getElementById('jperf-save');
		if (save) {
			save.addEventListener('click', function(){
				var opts = {};
				document.querySelectorAll('#jperf-toggles .jperf-sw').forEach(function(sw){
					opts[sw.dataset.key] = sw.classList.contains('on') ? 1 : 0;
				});
				save.disabled = true;
				var lbl = save.querySelector('span');
				var old = lbl.textContent;
				lbl.textContent = '保存中…';
				post('jinyu_perf_save', {options: JSON.stringify(opts)})
					.then(function(j){
						save.disabled = false; lbl.textContent = old;
						if (!j.success) { showErr('jperf-result', j.data && j.data.msg ? j.data.msg : '未知错误'); return; }
						showResult('jperf-result', [j.data.msg]);
						if (unsaved) {
							unsaved.hidden = false;
							unsaved.textContent = '✓ 已保存';
							unsaved.classList.add('saved');
							setTimeout(function(){
								unsaved.hidden = true;
								unsaved.textContent = '● 有改动未保存';
								unsaved.classList.remove('saved');
							}, 2500);
						}
						refreshStatus();
					})
					.catch(function(e){ save.disabled = false; lbl.textContent = old; showErr('jperf-result', e); });
			});
		}

		// 缓存管理独立按钮
		document.querySelectorAll('.jperf-btn[data-flush]').forEach(function(b){
			b.addEventListener('click', function(){
				b.disabled = true;
				var lbl = b.querySelector('span');
				var old = lbl.textContent;
				lbl.textContent = '清除中…';
				post('jinyu_perf_flush', {target: b.getAttribute('data-flush')})
					.then(function(j){
						b.disabled = false; lbl.textContent = old;
						if (!j.success) { showErr('jperf-cache-result', j.data && j.data.msg ? j.data.msg : '未知错误'); return; }
						showResult('jperf-cache-result', [j.data.msg]);
						refreshStatus();
					})
					.catch(function(e){ b.disabled = false; lbl.textContent = old; showErr('jperf-cache-result', e); });
			});
		});

		// 清空真实用户体验聚合
		var rwv = document.getElementById('jperf-reset-wv');
		if (rwv) {
			rwv.addEventListener('click', function(){
				if (!window.confirm('确定清空真实用户体验聚合数据？此操作不可撤销。')) { return; }
				rwv.disabled = true;
				var lbl = rwv.querySelector('span');
				var old = lbl.textContent;
				lbl.textContent = '清空中…';
				post('jinyu_perf_reset_wv')
					.then(function(j){
						rwv.disabled = false; lbl.textContent = old;
						if (!j.success) { showErr('jperf-wv-result', j.data && j.data.msg ? j.data.msg : '未知错误'); return; }
						showResult('jperf-wv-result', [j.data.msg]);
						refreshStatus();
					})
					.catch(function(e){ rwv.disabled = false; lbl.textContent = old; showErr('jperf-wv-result', e); });
			});
		}

		// 手动刷新看板
		var rf = document.getElementById('jperf-refresh');
		if (rf) {
			rf.addEventListener('click', function(){ rf.disabled = true; refreshStatus().then(function(){ rf.disabled = false; }); });
		}

		// 首次绘制
		animateBoards();
	})();
	</script>
	<?php
}
