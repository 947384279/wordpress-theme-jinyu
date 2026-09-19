<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ───────── 后端地址与共享密钥（均可在 wp-config.php 覆盖） ───────── */

// 上报 / 遥测后端（独立更新主机 update.qicaiyun.top，web 根）
if ( ! defined( 'JINYU_REPORT_SERVER' ) ) {
	define( 'JINYU_REPORT_SERVER', 'https://update.qicaiyun.top' );
}
// 上报 HMAC 共享密钥：与后端 config.local.php 的 secret 一致。
// 真实值建议放 wp-config.php；主题分发版此处为占位，部署时覆盖。
if ( ! defined( 'JINYU_REPORT_SECRET' ) ) {
	define( 'JINYU_REPORT_SECRET', 'CHANGE_ME_SHARED_SECRET' );
}
// 更新分发改指独立新主机（覆盖旧的主域名地址；wp-config 仍可覆盖）
if ( ! defined( 'JINYU_UPDATE_SERVER' ) ) {
	define( 'JINYU_UPDATE_SERVER', 'https://update.qicaiyun.top/jinyu-update.json' );
}

/* ───────── 每站安装令牌：授权与盗版检测的身份 ───────── */
if ( ! function_exists( 'jinyu_install_token' ) ) {
	function jinyu_install_token(): string {
		$t = get_option( 'jinyu_install_token' );
		if ( ! $t ) {
			$t = bin2hex( random_bytes( 16 ) );
			update_option( 'jinyu_install_token', $t, false );
		}

		return $t;
	}
}

/* 完整性自检：公钥 / 更新通道被剥离（nulled 常见手法）即视为异常 */
if ( ! function_exists( 'jinyu_integrity_ok' ) ) {
	function jinyu_integrity_ok(): bool {
		return defined( 'JINYU_UPDATE_PUBKEY' ) && function_exists( 'jinyu_verify_update_signature' );
	}
}

/* ───────── 统一上报客户端（HMAC 签名） ───────── */
if ( ! function_exists( 'jinyu_report_post' ) ) {
	function jinyu_report_post( string $endpoint, array $payload ): bool {
		if ( defined( 'JINYU_DISABLE_REPORT' ) && JINYU_DISABLE_REPORT ) {
			return false;
		}
		// 密钥未配置或仍是占位符：禁用上报，避免发出必然失败的 HMAC 请求
		if ( ! defined( 'JINYU_REPORT_SECRET' ) || JINYU_REPORT_SECRET === 'CHANGE_ME_SHARED_SECRET' ) {
			return false;
		}
		$payload['site_url']      = home_url();
		$payload['theme_version'] = JINYU_CUR_VER;
		$payload['wp_version']    = get_bloginfo( 'version' );
		$payload['php_version']   = PHP_VERSION;
		$payload['license_token'] = jinyu_install_token();
		$payload['integrity_ok']  = jinyu_integrity_ok();

		$body = wp_json_encode( $payload );
		if ( false === $body ) {
			return false;
		}
		$sig  = hash_hmac( 'sha256', $body, JINYU_REPORT_SECRET );
		$resp = wp_remote_post(
			rtrim( JINYU_REPORT_SERVER, '/' ) . '/' . $endpoint . '.php',
			[
				'timeout' => 10,
				'headers' => [
					'Content-Type' => 'application/json',
					'X-JINYU-HMAC' => $sig,
				],
				'body'    => $body,
			]
		);
		if ( is_wp_error( $resp ) ) {
			return false;
		}
		$code = (int) wp_remote_retrieve_response_code( $resp );

		return in_array( $code, [ 200, 201 ], true );
	}
}

/* 对外便捷函数：用户反馈 / 错误上报 */
if ( ! function_exists( 'jinyu_send_report' ) ) {
	function jinyu_send_report( string $type, string $message, array $payload = [] ): bool {
		return jinyu_report_post( 'report', array_merge( [ 'type' => $type, 'message' => $message ], $payload ) );
	}
}

/* ───────── 激活遥测（默认开，可关，已披露） ───────── */
if ( ! function_exists( 'jinyu_telemetry_enabled' ) ) {
	function jinyu_telemetry_enabled(): bool {
		if ( defined( 'JINYU_DISABLE_TELEMETRY' ) && JINYU_DISABLE_TELEMETRY ) {
			return false;
		}

		return (bool) get_option( 'jinyu_telemetry_enabled', true );
	}
}

add_action( 'after_switch_theme', function () {
	if ( jinyu_telemetry_enabled() ) {
		jinyu_report_post( 'activate', [] );
	}
} );

// 每日心跳：刷新 last_seen，后端据此识别失活站点
add_action( 'jinyu_telemetry_cron', function () {
	if ( jinyu_telemetry_enabled() ) {
		jinyu_report_post( 'activate', [] );
	}
} );
if ( ! wp_next_scheduled( 'jinyu_telemetry_cron' ) ) {
	wp_schedule_event( time(), 'daily', 'jinyu_telemetry_cron' );
}

/* ───────── 后台：反馈与上报页面（父菜单 jinyu-options 的子页） ───────── */
add_action( 'admin_menu', function () {
	add_submenu_page(
		'jinyu-options',
		__( '反馈与上报', JINYU ),
		__( '反馈与上报', JINYU ),
		'manage_options',
		'jinyu-report',
		'jinyu_report_page'
	);
}, 20 );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( strpos( (string) $hook, 'jinyu-report' ) === false ) {
		return;
	}
	wp_enqueue_script( 'jinyu-report', JINYU_ABS_URI . '/assets/js/jinyu-report.js', [ 'jquery' ], JINYU_CUR_VER, true );
	wp_localize_script( 'jinyu-report', 'JINYU_REPORT', [
		'ajax'  => admin_url( 'admin-ajax.php' ),
		'nonce' => wp_create_nonce( 'jinyu_report' ),
	] );
} );

add_action( 'wp_ajax_jinyu_send_report', function () {
	check_ajax_referer( 'jinyu_report', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die();
	}
	$type = in_array( $_POST['type'] ?? '', [ 'feedback', 'error' ], true ) ? $_POST['type'] : 'feedback';
	$msg  = sanitize_textarea_field( $_POST['message'] ?? '' );
	if ( '' === $msg ) {
		wp_send_json( [ 'ok' => false, 'msg' => '内容不能为空' ] );
	}
	$ok = jinyu_send_report( $type, $msg, [ 'payload' => $_POST['extra'] ?? null ] );
	wp_send_json( [
		'ok'  => $ok,
		'msg' => $ok ? '已上报，感谢反馈' : '上报失败（后端不可达或签名被拒）',
	] );
} );

function jinyu_report_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( isset( $_POST['jinyu_report_save'] ) && check_admin_referer( 'jinyu_report_save', 'jinyu_report_save_nonce' ) ) {
		update_option( 'jinyu_telemetry_enabled', ! empty( $_POST['telemetry'] ) ? 1 : 0 );
		echo '<div class="notice notice-success"><p>已保存</p></div>';
	}
	$on = jinyu_telemetry_enabled();
	?>
	<div class="wrap">
		<h1>金玉 · 反馈与上报</h1>

		<h2>激活量遥测（可选）</h2>
		<form method="post">
			<?php wp_nonce_field( 'jinyu_report_save', 'jinyu_report_save_nonce' ); ?>
			<label>
				<input type="checkbox" name="telemetry" value="1" <?php checked( $on ); ?> />
				允许向更新服务器上报「激活 / 版本分布」遥测（默认开启；可在 wp-config.php 定义
				<code>JINYU_DISABLE_REPORT</code> 强制关闭）
			</label>
			<p class="description">仅上报：站点域名、主题版本、WP/PHP 版本、安装令牌（用于识别盗版）。不含文章 / 用户等隐私数据。</p>
			<p><button class="button button-primary" name="jinyu_report_save">保存</button></p>
		</form>

		<h2>问题反馈 / 错误上报</h2>
		<p class="description">提交后以 HMAC 签名 POST 到 <code><?php echo esc_html( JINYU_REPORT_SERVER ); ?>/report.php</code>。可选勾选附带浏览器控制台错误。</p>
		<table class="form-table">
			<tr>
				<th><label for="jinyu-rpt-type">类型</label></th>
				<td>
					<select id="jinyu-rpt-type">
						<option value="feedback">功能反馈 / 建议</option>
						<option value="error">报错 / Bug</option>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="jinyu-rpt-msg">内容</label></th>
				<td><textarea id="jinyu-rpt-msg" class="large-text" rows="6" placeholder="描述你遇到的情况…"></textarea></td>
			</tr>
			<tr>
				<th></th>
				<td><label><input type="checkbox" id="jinyu-rpt-console" value="1" /> 附带浏览器控制台错误（仅 error 类型建议勾选）</label></td>
			</tr>
		</table>
		<p>
			<button class="button button-primary" id="jinyu-rpt-send">发送上报</button>
			<span id="jinyu-rpt-status"></span>
		</p>
		<p class="description">本机安装令牌：<code><?php echo esc_html( substr( jinyu_install_token(), 0, 8 ) ); ?>…</code></p>
	</div>
	<?php
}
