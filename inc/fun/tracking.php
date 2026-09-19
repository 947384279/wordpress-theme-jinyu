<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 金玉 · 销售与变现追踪后端
 *
 * 统一事件表 jinyu_events（kind/post_id/meta/ip_hash/created_at）记录：
 *   - go      联盟/外链点击（meta = 目标域名）
 *   - ad_imp  广告位曝光（meta = 广告位 key）
 *   - ad_clk  广告位点击（meta = 广告位 key）
 *   - cta     全站 CTA 点击（meta = CTA 文案）
 *   - sub     邮件订阅（meta = 邮箱）
 * 另设 jinyu_subscribers 沉淀订阅名单，jinyu_visit_source 沉淀每日流量来源(medium)。
 *
 * 度量是一切销售优化的前提：本文件把「联盟点击 / 广告曝光点击 / CTA 点击 / 订阅 / 来源」
 * 全部落库，并在后台「变现数据」页面与仪表盘小工具可视化。
 */

/* ───────────────────────── 建表 ───────────────────────── */

if ( ! function_exists( 'jinyu_tracking_install' ) ) {
	/**
	 * 建表（主题启用时 + 运行时兜底各来一次）。
	 * 用 dbDelta 保证幂等：字段变更只增不改。
	 */
	function jinyu_tracking_install() {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();

		$events = $wpdb->prefix . 'jinyu_events';
		$subs   = $wpdb->prefix . 'jinyu_subscribers';
		$src    = $wpdb->prefix . 'jinyu_visit_source';

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( "CREATE TABLE $events (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			kind VARCHAR(20) NOT NULL,
			post_id BIGINT UNSIGNED DEFAULT 0,
			meta VARCHAR(255) DEFAULT '',
			ip_hash CHAR(40) DEFAULT '',
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			KEY idx_kind_date (kind, created_at),
			KEY idx_post (post_id)
		) $charset;" );

		dbDelta( "CREATE TABLE $subs (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			email VARCHAR(191) NOT NULL,
			source VARCHAR(60) DEFAULT '',
			ip_hash CHAR(40) DEFAULT '',
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			UNIQUE KEY uk_email (email)
		) $charset;" );

		dbDelta( "CREATE TABLE $src (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			stat_date DATE NOT NULL,
			medium VARCHAR(40) NOT NULL,
			pv INT UNSIGNED DEFAULT 0,
			uv INT UNSIGNED DEFAULT 0,
			UNIQUE KEY uk_date_medium (stat_date, medium)
		) $charset;" );
	}
}

// 主题启用时建表（避免依赖其它钩子顺序）
add_action( 'after_switch_theme', 'jinyu_tracking_install' );

// 运行时兜底：首屏访问确保表存在（带 transient 锁，12h 一次）
add_action( 'wp_footer', function () {
	if ( get_transient( 'jinyu_tracking_checked' ) ) {
		return;
	}
	jinyu_tracking_install();
	set_transient( 'jinyu_tracking_checked', 1, HOUR_IN_SECONDS * 12 );
}, 1 );

/* ───────────────────────── 事件记录 ───────────────────────── */

if ( ! function_exists( 'jinyu_track_event_raw' ) ) {
	/**
	 * 落库一条事件（不做上下文守卫，调用方自行保证合法性）。
	 */
	function jinyu_track_event_raw( string $kind, array $args = [] ) {
		global $wpdb;
		$table = $wpdb->prefix . 'jinyu_events';
		$post_id = (int) ( $args['post_id'] ?? 0 );
		$meta = isset( $args['meta'] ) ? mb_substr( (string) $args['meta'], 0, 255 ) : '';
		$ip = isset( $args['ip'] ) ? (string) $args['ip'] : ( $_SERVER['REMOTE_ADDR'] ?? '' );
		$ip_hash = $ip ? hash( 'sha1', $ip . wp_salt( 'nonce' ) ) : '';

		$wpdb->insert( $table, [
			'kind'        => $kind,
			'post_id'     => $post_id,
			'meta'        => $meta,
			'ip_hash'     => $ip_hash,
			'created_at'  => current_time( 'mysql' ),
		], [ '%s', '%d', '%s', '%s', '%s' ] );
	}
}

if ( ! function_exists( 'jinyu_track_event' ) ) {
	/**
	 * 记录一条转化事件（带上下文守卫，用于服务端直接调用，如 go-link）。
	 *
	 * @param string $kind   事件类型：go | ad_imp | ad_clk | cta | sub
	 * @param array  $args   post_id / meta / ip（可选，默认取当前文章与客户端 IP）
	 */
	function jinyu_track_event( string $kind, array $args = [] ) {
		// 后台、机器人、feed 不记录
		if ( is_admin() || is_robots() || is_feed() ) {
			return;
		}
		if ( empty( $args['post_id'] ) && is_singular() ) {
			$args['post_id'] = get_the_ID();
		}
		jinyu_track_event_raw( $kind, $args );
	}
}

if ( ! function_exists( 'jinyu_visit_medium' ) ) {
	/**
	 * 根据 referer / UTM 推断流量来源 medium。
	 * direct | search | social | referral | utm(具体 campaign)
	 */
	function jinyu_visit_medium(): string {
		// UTM 优先：具体 campaign 名最利于归因
		$campaign = '';
		if ( ! empty( $_GET['utm_campaign'] ) ) {
			$campaign = preg_replace( '/[^a-zA-Z0-9_\-]/', '', substr( $_GET['utm_campaign'], 0, 40 ) );
		} elseif ( ! empty( $_GET['campaign'] ) ) {
			$campaign = preg_replace( '/[^a-zA-Z0-9_\-]/', '', substr( $_GET['campaign'], 0, 40 ) );
		}
		if ( $campaign ) {
			return 'utm:' . $campaign;
		}

		$ref = isset( $_SERVER['HTTP_REFERER'] ) ? (string) $_SERVER['HTTP_REFERER'] : '';
		if ( ! $ref ) {
			return 'direct';
		}

		$host = wp_parse_url( $ref, PHP_URL_HOST );
		if ( ! $host ) {
			return 'referral';
		}
		$self = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( $host === $self ) {
			return 'direct';
		}

		$search = [ 'google', 'bing', 'baidu', 'sogou', 'so.com', 'yandex', 'duckduckgo', 'yahoo' ];
		foreach ( $search as $s ) {
			if ( strpos( $host, $s ) !== false ) {
				return 'search';
			}
		}

		$social = [ 'weibo', 'qq.com', 'qzone', 'wx.qq', 'weixin', 't.cn', 'douyin', 'bytedance', 'x.com', 'twitter', 'facebook', 'instagram', 'zhihu', 'tieba', 'xiaohongshu', 'xhslink', 'bilibili', 'kuaishou' ];
		foreach ( $social as $s ) {
			if ( strpos( $host, $s ) !== false ) {
				return 'social';
			}
		}

		return 'referral';
	}
}

// 每次前台访问记录流量来源（PV/UV 分 medium 落库）
// ⚠️ 必须在 header 发出前调用 setcookie，故挂 template_redirect（早于 get_header），
// 不能挂 wp_footer（那时 header 已发，setcookie 会失败、UV 被记成 = PV）。
add_action( 'template_redirect', 'jinyu_track_visit_source', 10 );
if ( ! function_exists( 'jinyu_track_visit_source' ) ) {
	function jinyu_track_visit_source() {
		if ( is_admin() || is_robots() || is_feed() ) {
			return;
		}
		global $wpdb;
		$table = $wpdb->prefix . 'jinyu_visit_source';
		$today = current_time( 'Y-m-d' );
		$medium = jinyu_visit_medium();

		$is_new_uv = false;
		$uv_key = 'jinyu_src_' . $today . '_' . $medium;
		if ( ! isset( $_COOKIE[ $uv_key ] ) ) {
			// setcookie 必须在 header 发出前（template_redirect 阶段）调用，故保留此处；
			// 实际写库延迟到 shutdown，避免阻塞页面渲染。
			setcookie( $uv_key, '1', time() + 86400, '/' );
			$is_new_uv = true;
		}

		// 延迟到 shutdown 写库：PV 每次 +1，UV 仅新访客 +1（不阻塞响应）
		add_action( 'shutdown', function () use ( $wpdb, $table, $today, $medium, $is_new_uv ) {
			$wpdb->query( $wpdb->prepare(
				"INSERT INTO $table (stat_date, medium, pv, uv) VALUES (%s, %s, 1, 1)
				 ON DUPLICATE KEY UPDATE pv = pv + 1",
				$today, $medium
			) );
			if ( $is_new_uv ) {
				$wpdb->query( $wpdb->prepare(
					"UPDATE $table SET uv = uv + 1 WHERE stat_date = %s AND medium = %s",
					$today, $medium
				) );
			}
		} );
	}
}

/* ───────────────────────── 订阅名单 ───────────────────────── */

if ( ! function_exists( 'jinyu_subscribe_email' ) ) {
	/**
	 * 写入订阅邮箱（去重）。返回 'ok' | 'dup' | 'invalid'。
	 */
	function jinyu_subscribe_email( string $email, string $source = '' ): string {
		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			return 'invalid';
		}
		global $wpdb;
		$table = $wpdb->prefix . 'jinyu_subscribers';
		$ip = $_SERVER['REMOTE_ADDR'] ?? '';
		$ip_hash = $ip ? hash( 'sha1', $ip . wp_salt( 'nonce' ) ) : '';

		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE email = %s", $email ) );
		if ( $exists ) {
			return 'dup';
		}
		$wpdb->insert( $table, [
			'email'       => $email,
			'source'      => mb_substr( $source, 0, 60 ),
			'ip_hash'    => $ip_hash,
			'created_at' => current_time( 'mysql' ),
		], [ '%s', '%s', '%s', '%s' ] );

		// 同步记一条事件，便于「变现数据」统一时间轴
		// ⚠️ 本函数由 AJAX（admin-ajax.php）调用，is_admin() 为真，
		// jinyu_track_event() 的守卫会丢弃，故直接用 _raw。
		jinyu_track_event_raw( 'sub', [ 'meta' => $email ] );
		return 'ok';
	}
}

/* ───────────────────────── AJAX：订阅 ───────────────────────── */

add_action( 'wp_ajax_nopriv_jinyu_subscribe', 'jinyu_ajax_subscribe' );
add_action( 'wp_ajax_jinyu_subscribe', 'jinyu_ajax_subscribe' );
if ( ! function_exists( 'jinyu_ajax_subscribe' ) ) {
	function jinyu_ajax_subscribe() {
		if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'jinyu_front' ) ) {
			wp_send_json_error( [ 'msg' => __( '请求已过期，请刷新重试', JINYU ) ] );
		}
		$email = isset( $_POST['email'] ) ? (string) $_POST['email'] : '';
		$source = isset( $_POST['source'] ) ? (string) $_POST['source'] : 'form';
		$res = jinyu_subscribe_email( $email, $source );
		if ( $res === 'invalid' ) {
			wp_send_json_error( [ 'msg' => __( '邮箱格式不正确', JINYU ) ] );
		}
		if ( $res === 'dup' ) {
			wp_send_json_success( [ 'msg' => __( '您已订阅过，无需重复~', JINYU ), 'dup' => true ] );
		}
		wp_send_json_success( [ 'msg' => jinyu_get_option( 'subscribe_success', __( '订阅成功，感谢支持！', JINYU ) ) ] );
	}
}

/* ───────────────────────── AJAX：前端事件（广告曝光/点击、CTA 点击） ───────────────────────── */

add_action( 'wp_ajax_nopriv_jinyu_track_event', 'jinyu_ajax_track_event' );
add_action( 'wp_ajax_jinyu_track_event', 'jinyu_ajax_track_event' );
if ( ! function_exists( 'jinyu_ajax_track_event' ) ) {
	function jinyu_ajax_track_event() {
		if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'jinyu_front' ) ) {
			wp_send_json_error();
		}
		$kind = isset( $_POST['kind'] ) ? preg_replace( '/[^a-z_]/', '', substr( (string) $_POST['kind'], 0, 20 ) ) : '';
		if ( ! in_array( $kind, [ 'ad_imp', 'ad_clk', 'cta' ], true ) ) {
			wp_send_json_error();
		}
		// admin-ajax.php 下 is_admin() 为真，故绕过守卫直接落库
		jinyu_track_event_raw( $kind, [
			'post_id' => isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0,
			'meta'    => isset( $_POST['meta'] ) ? sanitize_text_field( substr( (string) $_POST['meta'], 0, 255 ) ) : '',
		] );
		wp_send_json_success();
	}
}

add_action( 'wp_dashboard_setup', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	wp_add_dashboard_widget( 'jinyu_sales_widget', '金玉 · 变现概览', 'jinyu_sales_widget_render' );
} );

if ( ! function_exists( 'jinyu_sales_widget_render' ) ) {
	function jinyu_sales_widget_render() {
		// 后台仪表盘小工具：聚合查询（COUNT/SUM/GROUP BY）结果缓存 5 分钟，避免每次打开仪表盘都扫表。
		// 后台数据允许近似，5 分钟延迟可接受。
		$cached = get_transient( 'jinyu_sales_widget' );
		if ( $cached !== false ) {
			echo $cached;
			return;
		}
		ob_start();
		jinyu_tracking_install(); // 确保表存在
		global $wpdb;
		$events = $wpdb->prefix . 'jinyu_events';
		$subs   = $wpdb->prefix . 'jinyu_subscribers';
		$src    = $wpdb->prefix . 'jinyu_visit_source';

		$clicks = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $events WHERE kind = %s", 'go' ) );
		$cta    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $events WHERE kind = %s", 'cta' ) );
		$ad_clk = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $events WHERE kind = %s", 'ad_clk' ) );
		$sub_n  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $subs" );
		$top_src = $wpdb->get_results( "SELECT medium, SUM(pv) AS pv FROM $src GROUP BY medium ORDER BY pv DESC LIMIT 5" );

		echo '<style>.jinyu-sales-w li{display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px dashed #eee}.jinyu-sales-w b{color:#e8590c}</style>';
		echo '<ul class="jinyu-sales-w">';
		echo '<li><span>联盟点击(go)</span><b>' . number_format( $clicks ) . '</b></li>';
		echo '<li><span>广告点击</span><b>' . number_format( $ad_clk ) . '</b></li>';
		echo '<li><span>CTA 点击</span><b>' . number_format( $cta ) . '</b></li>';
		echo '<li><span>邮件订阅</span><b>' . number_format( $sub_n ) . '</b></li>';
		echo '</ul>';
		echo '<p style="margin:8px 0 4px"><b>流量来源 TOP5</b></p>';
		if ( $top_src ) {
			echo '<ul class="jinyu-sales-w">';
			foreach ( $top_src as $r ) {
				echo '<li><span>' . esc_html( $r->medium ) . '</span><b>' . number_format( (int) $r->pv ) . ' PV</b></li>';
			}
			echo '</ul>';
		} else {
			echo '<p>暂无数据</p>';
		}
		echo '<p style="margin-top:8px"><a class="button" href="' . admin_url( 'admin.php?page=jinyu-sales' ) . '">' . esc_html__( '查看变现数据', JINYU ) . '</a></p>';
		$html = ob_get_clean();
		set_transient( 'jinyu_sales_widget', $html, 5 * MINUTE_IN_SECONDS );
		echo $html;
	}
}

/* ───────────────────────── 后台：变现数据页面 ───────────────────────── */

/* 用 priority 20 晚于 Jinyu_Setting(默认 10) 注册，确保父菜单 jinyu-options 已存在，
   否则 add_submenu_page 执行时父菜单尚未注册 → 子菜单变孤儿被 WP 丢弃（后台无此菜单项）。 */
add_action( 'admin_menu', function () {
	add_submenu_page(
		'jinyu-options',
		__( '变现数据', JINYU ),
		__( '变现数据', JINYU ),
		'manage_options',
		'jinyu-sales',
		'jinyu_sales_page_render'
	);
}, 20 );

/**
 * 订阅名单 CSV 导出。
 * ⚠️ 必须在后台页面 HTML 输出之前拦截（admin_init），否则 header() 已无法发送
 * （WP 在渲染子菜单页回调前已输出后台头部，会触发 "Cannot modify header information"）。
 */
add_action( 'admin_init', function () {
	if ( empty( $_GET['page'] ) || $_GET['page'] !== 'jinyu-sales' ) {
		return;
	}
	if ( empty( $_GET['export'] ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'jinyu_export_subs' ) ) {
		wp_die( __( '导出链接已失效，请重新点击导出按钮。', JINYU ) );
	}
	global $wpdb;
	$subs = $wpdb->prefix . 'jinyu_subscribers';
	$rows = $wpdb->get_results( "SELECT email, source, created_at FROM $subs ORDER BY id DESC" );
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="jinyu-subscribers-' . current_time( 'Y-m-d' ) . '.csv"' );
	// 输出 BOM 让 Excel 正确识别 UTF-8
	echo "\xEF\xBB\xBF";
	echo "email,source,created_at\n";
	foreach ( $rows as $r ) {
		$email = str_replace( [ '"', ',', "\n", "\r" ], [ '""', ' ', ' ', ' ' ], (string) $r->email );
		$source = str_replace( [ '"', ',', "\n", "\r" ], [ '""', ' ', ' ', ' ' ], (string) $r->source );
		echo '"' . $email . '","' . $source . '","' . $r->created_at . "\"\n";
	}
	exit;
} );

if ( ! function_exists( 'jinyu_sales_page_render' ) ) {
	function jinyu_sales_page_render() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	jinyu_tracking_install(); // 确保表存在（首次访问前打开本页也不报错）
	global $wpdb;
	$events = $wpdb->prefix . 'jinyu_events';
		$subs   = $wpdb->prefix . 'jinyu_subscribers';
		$src    = $wpdb->prefix . 'jinyu_visit_source';

		// 订阅名单导出已在 admin_init 阶段拦截输出（早于后台头部，可正常发送 header）。
		// 此处不再处理 export，避免 "Cannot modify header information"。

		$days = (int) ( $_GET['days'] ?? 30 );
		$days = max( 1, min( 365, $days ) );

		// 聚合查询（COUNT/SUM/GROUP BY）结果缓存 5 分钟：后台数据允许近似，避免每次打开变现页都扫表。
		// 查询本身走 jinyu_events 的 idx_kind_date(kind,created_at) 与 jinyu_visit_source 的 uk_date_medium 索引，已非全表扫，
		// 但每次渲染仍重复计算，缓存后进一步降低后台 DB 压力。
		$jinyu_sales_cache_key = 'jinyu_sales_stats_' . $days;
		$jinyu_sales_stats     = get_transient( $jinyu_sales_cache_key );
		if ( $jinyu_sales_stats === false ) {
			$jinyu_sales_stats = [];
			$jinyu_sales_stats['total_go']     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $events WHERE kind=%s AND created_at>=DATE_SUB(NOW(),INTERVAL %d DAY)", 'go', $days ) );
			$jinyu_sales_stats['total_cta']    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $events WHERE kind=%s AND created_at>=DATE_SUB(NOW(),INTERVAL %d DAY)", 'cta', $days ) );
			$jinyu_sales_stats['total_ad_clk'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $events WHERE kind=%s AND created_at>=DATE_SUB(NOW(),INTERVAL %d DAY)", 'ad_clk', $days ) );
			$jinyu_sales_stats['total_sub']    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $subs WHERE created_at>=DATE_SUB(NOW(),INTERVAL %d DAY)", $days ) );
			$jinyu_sales_stats['top_domains']  = $wpdb->get_results( $wpdb->prepare(
				"SELECT meta AS host, COUNT(*) AS cnt FROM $events WHERE kind=%s AND created_at>=DATE_SUB(NOW(),INTERVAL %d DAY) GROUP BY meta ORDER BY cnt DESC LIMIT 10",
				'go', $days
			) );
			$jinyu_sales_stats['top_posts']    = $wpdb->get_results( $wpdb->prepare(
				"SELECT post_id, COUNT(*) AS cnt FROM $events WHERE kind=%s AND post_id>0 AND created_at>=DATE_SUB(NOW(),INTERVAL %d DAY) GROUP BY post_id ORDER BY cnt DESC LIMIT 10",
				'go', $days
			) );
			$jinyu_sales_stats['daily']        = $wpdb->get_results( $wpdb->prepare(
				"SELECT DATE(created_at) AS d, COUNT(*) AS cnt FROM $events WHERE kind=%s AND created_at>=DATE_SUB(NOW(),INTERVAL %d DAY) GROUP BY d ORDER BY d ASC",
				'go', $days
			) );
			$jinyu_sales_stats['source_rows']  = $wpdb->get_results( $wpdb->prepare(
				"SELECT medium, SUM(pv) AS pv, SUM(uv) AS uv FROM $src WHERE stat_date>=DATE_SUB(NOW(),INTERVAL %d DAY) GROUP BY medium ORDER BY pv DESC",
				$days
			) );
			set_transient( $jinyu_sales_cache_key, $jinyu_sales_stats, 5 * MINUTE_IN_SECONDS );
		}
		$total_go     = $jinyu_sales_stats['total_go'];
		$total_cta    = $jinyu_sales_stats['total_cta'];
		$total_ad_clk = $jinyu_sales_stats['total_ad_clk'];
		$total_sub    = $jinyu_sales_stats['total_sub'];
		$top_domains  = $jinyu_sales_stats['top_domains'];
		$top_posts    = $jinyu_sales_stats['top_posts'];
		$daily        = $jinyu_sales_stats['daily'];
		$source_rows  = $jinyu_sales_stats['source_rows'];

		$fmt = 'number_format_i18n';

		$medium_label = static function ( $m ) {
			$map = [
				'direct'   => '直接访问',
				'search'   => '搜索引擎',
				'social'   => '社交媒体',
				'referral' => '外部引荐',
			];
			$m = (string) $m;
			if ( strpos( $m, 'utm:' ) === 0 ) {
				return '活动 · ' . substr( $m, 4 );
			}
			return $map[ $m ] ?? $m;
		};

		$export_url = wp_nonce_url( admin_url( 'admin.php?page=jinyu-sales&export=1' ), 'jinyu_export_subs' );
		$page_url   = admin_url( 'admin.php?page=jinyu-sales' );

		// 每日趋势：补齐缺失日期，保证横轴连续
		$trend     = [];
		$trend_max = 0;
		try {
			$cursor = new DateTime( current_time( 'Y-m-d' ) );
			$cursor->modify( '-' . ( $days - 1 ) . ' days' );
			$cnt_by_date = [];
			foreach ( $daily as $r ) {
				$cnt_by_date[ $r->d ] = (int) $r->cnt;
			}
			for ( $i = 0; $i < $days; $i++ ) {
				$d       = $cursor->format( 'Y-m-d' );
				$cnt     = $cnt_by_date[ $d ] ?? 0;
				$trend[] = [ 'd' => $d, 'cnt' => $cnt ];
				$trend_max = max( $trend_max, $cnt );
				$cursor->modify( '+1 day' );
			}
		} catch ( Exception $e ) {
			$trend = [];
		}

		// 趋势 SVG 面积图（自绘，零依赖）
		$trend_svg = '';
		if ( $trend_max > 0 && $trend ) {
			$n  = count( $trend );
			$w  = 760; $h = 220; $pl = 12; $pr = 12; $pt = 22; $pb = 28;
			$xs = static function ( $i ) use ( $n, $w, $pl, $pr ) {
				return $n > 1 ? $pl + $i * ( $w - $pl - $pr ) / ( $n - 1 ) : ( $w / 2 );
			};
			$ys = static function ( $c ) use ( $trend_max, $h, $pt, $pb ) {
				return $pt + ( 1 - $c / $trend_max ) * ( $h - $pt - $pb );
			};
			$line = '';
			foreach ( $trend as $i => $p ) {
				$line .= ( $i ? ' L' : 'M' ) . round( $xs( $i ), 1 ) . ' ' . round( $ys( $p['cnt'] ), 1 );
			}
			$area    = $line . ' L' . round( $xs( $n - 1 ), 1 ) . ' ' . ( $h - $pb ) . ' L' . round( $xs( 0 ), 1 ) . ' ' . ( $h - $pb ) . ' Z';
			$last_i  = $n - 1;
			$lx      = round( $xs( $last_i ), 1 );
			$ly      = round( $ys( $trend[ $last_i ]['cnt'] ), 1 );
			$trend_svg = '<svg viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="每日联盟点击趋势图" preserveAspectRatio="none">'
				. '<defs><linearGradient id="jysx-grad" x1="0" y1="0" x2="0" y2="1">'
				. '<stop offset="0" stop-color="#e8590c" stop-opacity=".26"/>'
				. '<stop offset="1" stop-color="#e8590c" stop-opacity="0"/>'
				. '</linearGradient></defs>'
				. '<path d="' . $area . '" fill="url(#jysx-grad)"/>'
				. '<path d="' . $line . '" fill="none" stroke="#e8590c" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"/>'
				. '<circle cx="' . $lx . '" cy="' . $ly . '" r="4" fill="#e8590c" stroke="#fff" stroke-width="2"/>'
				. '<text x="' . $pl . '" y="14" font-size="12" fill="#8c8f94">峰值 ' . $fmt( $trend_max ) . '</text>'
				. '<text x="' . $pl . '" y="' . ( $h - 8 ) . '" font-size="11" fill="#a7aaad">' . esc_html( $trend[0]['d'] ) . '</text>'
				. '<text x="' . ( $w - $pr ) . '" y="' . ( $h - 8 ) . '" font-size="11" fill="#a7aaad" text-anchor="end">' . esc_html( $trend[ $last_i ]['d'] ) . '</text>'
				. '</svg>';
		}

		$card_meta = [
			'go'  => [ '联盟点击', '#e8590c', '#fdeee4', '<path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/>' ],
			'ad'  => [ '广告点击', '#7c3aed', '#f3eefe', '<path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/>' ],
			'cta' => [ 'CTA 点击', '#0ea5a4', '#e6f7f6', '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4"/><circle cx="12" cy="12" r="1"/>' ],
			'sub' => [ '邮件订阅', '#2563eb', '#e8effd', '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>' ],
		];
		$card_vals = [ 'go' => $total_go, 'ad' => $total_ad_clk, 'cta' => $total_cta, 'sub' => $total_sub ];
		?>
		<div class="wrap jysx">
			<style>
				.jysx{max-width:1200px}
				.jysx *,.jysx *::before,.jysx *::after{box-sizing:border-box}
				.jysx-head{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin:6px 0 20px}
				.jysx-title{display:flex;align-items:center;gap:12px;padding:0}
				.jysx-title .dashicon-before{display:none}
				.jysx-badge{display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,#e8590c,#f97316);color:#fff;font-size:12px;font-weight:600;padding:4px 10px;border-radius:999px;letter-spacing:.5px}
				.jysx-seg{display:inline-flex;background:#fff;border:1px solid #dcdcde;border-radius:9px;padding:3px;gap:2px}
				.jysx-seg a{text-decoration:none;font-size:13px;color:#50575e;padding:5px 14px;border-radius:6px;transition:all .15s}
				.jysx-seg a:hover{color:#e8590c}
				.jysx-seg a.on{background:#e8590c;color:#fff;font-weight:600;box-shadow:0 1px 4px rgba(232,89,12,.35)}
				.jysx-cards{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:16px}
				.jysx-card{display:flex;align-items:center;gap:14px;background:#fff;border:1px solid #e2e4e7;border-radius:14px;padding:18px 20px;box-shadow:0 1px 2px rgba(0,0,0,.03);transition:box-shadow .2s,transform .2s}
				.jysx-card:hover{box-shadow:0 6px 20px rgba(0,0,0,.07);transform:translateY(-2px)}
				.jysx-ico{flex:0 0 auto;width:44px;height:44px;border-radius:11px;display:flex;align-items:center;justify-content:center}
				.jysx-ico svg{width:22px;height:22px;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
				.jysx-num{display:block;font-size:26px;font-weight:700;line-height:1.15;color:#1d2327;font-variant-numeric:tabular-nums}
				.jysx-lab{display:block;font-size:13px;color:#50575e;margin-top:2px}
				.jysx-sub{display:block;font-size:12px;color:#a7aaad;margin-top:1px}
				.jysx-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
				.jysx-panel{background:#fff;border:1px solid #e2e4e7;border-radius:14px;box-shadow:0 1px 2px rgba(0,0,0,.03);overflow:hidden}
				.jysx-panel.full{grid-column:1 / -1}
				.jysx-phead{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:14px 20px;border-bottom:1px solid #f0f0f1}
				.jysx-ptitle{font-size:14px;font-weight:600;color:#1d2327;margin:0;display:flex;align-items:center;gap:8px}
				.jysx-ptitle::before{content:"";width:4px;height:14px;border-radius:2px;background:#e8590c;display:inline-block}
				.jysx-pact{font-size:12px;text-decoration:none;color:#e8590c;font-weight:600}
				.jysx-pact:hover{color:#c74a08}
				.jysx-pbody{padding:16px 20px}
				.jysx-chart svg{display:block;width:100%;height:auto}
				.jysx-table{width:100%;border-collapse:collapse}
				.jysx-table th{text-align:left;font-size:12px;font-weight:600;color:#787c82;text-transform:none;padding:0 0 8px;border-bottom:1px solid #f0f0f1}
				.jysx-table td{padding:9px 0;border-bottom:1px solid #f6f7f7;vertical-align:middle}
				.jysx-table tr:last-child td{border-bottom:0}
				.jysx-table .num{text-align:right;font-variant-numeric:tabular-nums;font-weight:600;color:#1d2327;white-space:nowrap;width:70px}
				.jysx-name{font-size:13px;color:#2c3338;word-break:break-all}
				.jysx-name a{text-decoration:none;color:#2c3338}
				.jysx-name a:hover{color:#e8590c}
				.jysx-tag{display:inline-block;font-size:11px;color:#787c82;background:#f0f0f1;border-radius:5px;padding:1px 7px;margin-left:6px;vertical-align:1px}
				.jysx-bar{height:5px;border-radius:3px;background:#f0f0f1;margin-top:6px;overflow:hidden}
				.jysx-bar i{display:block;height:100%;border-radius:3px;background:linear-gradient(90deg,#e8590c,#fb923c)}
				.jysx-bar.teal i{background:linear-gradient(90deg,#0ea5a4,#2dd4bf)}
				.jysx-bar.blue i{background:linear-gradient(90deg,#2563eb,#60a5fa)}
				.jysx-empty{border:1px dashed #dcdcde;border-radius:10px;padding:28px 20px;text-align:center;color:#8c8f94;font-size:13px;line-height:1.8}
				.jysx-empty b{color:#50575e}
				.jysx-btn{display:inline-flex;align-items:center;gap:6px;background:#e8590c;color:#fff !important;font-size:12px;font-weight:600;text-decoration:none;padding:5px 12px;border-radius:7px;border:none;cursor:pointer;transition:background .15s}
				.jysx-btn:hover{background:#c74a08}
				@media (max-width:1100px){.jysx-cards{grid-template-columns:repeat(2,1fr)}}
				@media (max-width:900px){.jysx-grid{grid-template-columns:1fr}}
				@media (max-width:600px){.jysx-cards{grid-template-columns:1fr}}
			</style>

			<div class="jysx-head">
				<h1 class="jysx-title">金玉 · 变现数据 <span class="jysx-badge">数据看板</span></h1>
				<div class="jysx-seg"><?php
					$periods = [ 7 => '近 7 天', 30 => '近 30 天', 90 => '近 90 天', 365 => '近 1 年' ];
					foreach ( $periods as $dnum => $label ) {
						echo '<a href="' . esc_url( add_query_arg( 'days', $dnum, $page_url ) ) . '" class="' . ( (int) $days === $dnum ? 'on' : '' ) . '">' . esc_html( $label ) . '</a>';
					}
				?></div>
			</div>

			<div class="jysx-cards"><?php
				foreach ( $card_meta as $key => $cmeta ) {
					list( $clabel, $ccolor, $cbg, $cicon ) = $cmeta;
					$val = $card_vals[ $key ];
					echo '<div class="jysx-card">'
						. '<span class="jysx-ico" style="background:' . esc_attr( $cbg ) . '"><svg style="stroke:' . esc_attr( $ccolor ) . '" viewBox="0 0 24 24">' . $cicon . '</svg></span>'
						. '<span><b class="jysx-num">' . $fmt( $val ) . '</b>'
						. '<span class="jysx-lab">' . esc_html( $clabel ) . '</span>'
						. '<span class="jysx-sub">日均 ' . $fmt( round( $val / max( 1, $days ), 1 ) ) . ' 次 · 近 ' . esc_html( $days ) . ' 天</span></span>'
						. '</div>';
				}
			?></div>

			<div class="jysx-grid">
				<div class="jysx-panel full">
					<div class="jysx-phead"><h2 class="jysx-ptitle">每日联盟点击趋势</h2></div>
					<div class="jysx-pbody jysx-chart"><?php
						if ( $trend_svg ) {
							echo $trend_svg; // 已转义的自绘 SVG
						} else {
							echo '<div class="jysx-empty">暂无点击数据。<br>请确认后台「<b>销售与变现 › 联盟点击追踪</b>」已开启，且 <b>/go/</b> 短链在被使用。</div>';
						}
					?></div>
				</div>

				<div class="jysx-panel">
					<div class="jysx-phead"><h2 class="jysx-ptitle">联盟点击 TOP 域名</h2></div>
					<div class="jysx-pbody"><?php
						if ( $top_domains ) {
							$max_d = max( array_map( static fn( $r ) => (int) $r->cnt, $top_domains ) );
							echo '<table class="jysx-table"><tbody>';
							foreach ( $top_domains as $i => $r ) {
								$pct = $max_d > 0 ? round( (int) $r->cnt / $max_d * 100 ) : 0;
								echo '<tr><td><span class="jysx-name">' . esc_html( $r->host ?: '未知来源' ) . '</span>'
									. '<div class="jysx-bar"><i style="width:' . $pct . '%"></i></div></td>'
									. '<td class="num">' . $fmt( (int) $r->cnt ) . '</td></tr>';
							}
							echo '</tbody></table>';
						} else {
							echo '<div class="jysx-empty">暂无数据<br>联盟点击落地后会在这里按域名汇总</div>';
						}
					?></div>
				</div>

				<div class="jysx-panel">
					<div class="jysx-phead"><h2 class="jysx-ptitle">联盟点击 TOP 文章</h2></div>
					<div class="jysx-pbody"><?php
						if ( $top_posts ) {
							$max_p = max( array_map( static fn( $r ) => (int) $r->cnt, $top_posts ) );
							echo '<table class="jysx-table"><tbody>';
							foreach ( $top_posts as $r ) {
								$pct = $max_p > 0 ? round( (int) $r->cnt / $max_p * 100 ) : 0;
								$title = get_the_title( $r->post_id ) ?: '（已删除文章 #' . $r->post_id . '）';
								$link  = get_edit_post_link( $r->post_id );
								echo '<tr><td><span class="jysx-name">' . ( $link ? '<a href="' . esc_url( $link ) . '">' . esc_html( $title ) . '</a>' : esc_html( $title ) ) . '</span>'
									. '<div class="jysx-bar"><i style="width:' . $pct . '%"></i></div></td>'
									. '<td class="num">' . $fmt( (int) $r->cnt ) . '</td></tr>';
							}
							echo '</tbody></table>';
						} else {
							echo '<div class="jysx-empty">暂无数据<br>带联盟链接的文章被点击后在这里排行</div>';
						}
					?></div>
				</div>

				<div class="jysx-panel">
					<div class="jysx-phead"><h2 class="jysx-ptitle">流量来源</h2></div>
					<div class="jysx-pbody"><?php
						if ( $source_rows ) {
							$max_pv = max( array_map( static fn( $r ) => (int) $r->pv, $source_rows ) );
							echo '<table class="jysx-table"><thead><tr><th>来源</th><th class="num">PV</th><th class="num">UV</th></tr></thead><tbody>';
							foreach ( $source_rows as $r ) {
								$pct = $max_pv > 0 ? round( (int) $r->pv / $max_pv * 100 ) : 0;
								echo '<tr><td><span class="jysx-name">' . esc_html( $medium_label( $r->medium ) ) . '</span>'
									. '<div class="jysx-bar blue"><i style="width:' . $pct . '%"></i></div></td>'
									. '<td class="num">' . $fmt( (int) $r->pv ) . '</td>'
									. '<td class="num">' . $fmt( (int) $r->uv ) . '</td></tr>';
							}
							echo '</tbody></table>';
						} else {
							echo '<div class="jysx-empty">暂无数据<br>前台访问被记录后在这里按来源汇总</div>';
						}
					?></div>
				</div>

				<div class="jysx-panel">
					<div class="jysx-phead">
						<h2 class="jysx-ptitle">邮件订阅名单</h2>
						<a class="jysx-btn" href="<?php echo esc_url( $export_url ); ?>">导出 CSV</a>
					</div>
					<div class="jysx-pbody"><?php
						$subs_rows = $wpdb->get_results( "SELECT email, source, created_at FROM $subs ORDER BY id DESC LIMIT 200" );
						if ( $subs_rows ) {
							echo '<table class="jysx-table"><thead><tr><th>邮箱</th><th>来源</th><th class="num">订阅时间</th></tr></thead><tbody>';
							foreach ( $subs_rows as $r ) {
								echo '<tr><td><span class="jysx-name">' . esc_html( $r->email ) . '</span></td>'
									. '<td><span class="jysx-name">' . esc_html( $r->source ?: '站内表单' ) . '</span></td>'
									. '<td class="num">' . esc_html( $r->created_at ) . '</td></tr>';
							}
							echo '</tbody></table>';
						} else {
							echo '<div class="jysx-empty">暂无订阅<br>访客通过文末订阅卡提交邮箱后会出现在这里</div>';
						}
					?></div>
				</div>
			</div>
		</div>
		<?php
	}
}
