<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SMTP 发信通道。
 *
 * 配置来自主题设置「邮件发送」面板（option: jinyu_options）。开启 smtp_enable 后，
 * 全站系统邮件（评论回复、找回密码、注册通知等）改走 SMTP，绕开虚拟主机 mail() 的
 * 频繁丢信 / 进垃圾箱问题。
 *
 * 实现基于 WordPress 自带的 PHPMailer（wp-includes/PHPMailer），不引入第三方依赖。
 */

if ( ! function_exists( 'jinyu_smtp_config' ) ) {
	/**
	 * 取规范化后的 SMTP 配置。
	 *
	 * $override 用于「不保存直接测试」：优先用前端当前填写的值，未填才回落到已保存配置。
	 *
	 * @param array $override 覆盖项（smtp_host / smtp_port / smtp_secure / smtp_user / smtp_pwd / smtp_from）。
	 * @return array{enabled:bool,host:string,port:int,secure:string,user:string,pwd:string,from:string}
	 */
	function jinyu_smtp_config( array $override = array() ): array {
		$c = array(
			'enabled' => (bool) jinyu_get_option( 'smtp_enable', 0 ),
			'host'    => trim( (string) jinyu_get_option( 'smtp_host', '' ) ),
			'port'    => (int) jinyu_get_option( 'smtp_port', 465 ),
			'secure'  => (string) jinyu_get_option( 'smtp_secure', 'tls' ),
			'user'    => trim( (string) jinyu_get_option( 'smtp_user', '' ) ),
			'pwd'     => '',
			'from'    => trim( (string) jinyu_get_option( 'smtp_from', '' ) ),
		);

		foreach ( array( 'host', 'port', 'secure', 'user', 'pwd', 'from' ) as $k ) {
			if ( isset( $override[ $k ] ) && is_scalar( $override[ $k ] ) && (string) $override[ $k ] !== '' ) {
				$c[ $k ] = $k === 'port' ? (int) $override['port'] : trim( (string) $override[ $k ] );
			}
		}

		// 授权码入库为密文：没走 override 时要从库中取出来再解密
		// （解密失败回落空串，由调用方判断）。
		$c['pwd'] = (string) jinyu_decrypt(
			$c['pwd'] !== '' ? $c['pwd'] : (string) jinyu_get_option( 'smtp_pwd', '' )
		);
		if ( $c['user'] !== '' && $c['from'] === '' ) {
			$c['from'] = $c['user'];
		}
		if ( $c['secure'] === 'auto' ) {
			$c['secure'] = $c['port'] === 465 ? 'ssl' : 'tls';
		}
		return $c;
	}

	/**
	 * 配置是否可用（缺主机 / 账号 / 密码均视为未配置）。
	 */
	function jinyu_smtp_ready( array $c ): bool {
		return $c['host'] !== '' && $c['user'] !== '' && $c['pwd'] !== '';
	}
}

if ( ! function_exists( 'jinyu_smtp_require_phpmailer' ) ) {
	/**
	 * 确保 PHPMailer 类已加载。
	 *
	 * WordPress 只在 wp_mail() 里 require class-phpmailer.php，所以「没有发过信的后台请求」
	 * 里 PHPMailer 根本没进 autoload 映射 —— 直接 class_exists() 判断会误报「缺少 PHPMailer」。
	 * 这里先走 WP 的统一入口，入口不存在时再逐个 require 命名空间文件（均带 file_exists 保护，
	 * 避免缺失文件导致 require fatal）。
	 */
	function jinyu_smtp_require_phpmailer(): void {
		// 走 WP 统一入口（它只加载 PHPMailer 与 Exception，SMTP 类还要另 require）
		$entry = ABSPATH . WPINC . '/class-phpmailer.php';
		if ( file_exists( $entry ) ) {
			require_once $entry;
		}
		$dir = ABSPATH . WPINC . '/PHPMailer';
		foreach ( array( 'PHPMailer.php', 'SMTP.php', 'Exception.php' ) as $f ) {
			$file = $dir . '/' . $f;
			if ( file_exists( $file ) && ! class_exists( 'PHPMailer\\PHPMailer\\' . basename( $f, '.php' ), false ) ) {
				require_once $file;
			}
		}
	}
}

/**
 * 挂载 PHPMailer：仅在「已启用 + 配置完整」时才改道，避免误配置把全站邮件打挂。
 */
add_action( 'phpmailer_init', function ( $mailer ) {
	$c = jinyu_smtp_config();
	if ( ! $c['enabled'] || ! jinyu_smtp_ready( $c ) ) {
		return;
	}
	jinyu_smtp_require_phpmailer();

	$mailer->isSMTP();
	$mailer->Host        = $c['host'];
	$mailer->Port        = $c['port'];
	$mailer->SMTPSecure  = $c['secure'] === 'none' ? '' : $c['secure'];
	$mailer->SMTPAuth    = true;
	$mailer->Username    = $c['user'];
	$mailer->Password    = $c['pwd'];
	$mailer->SMTPOptions = array(
		'ssl' => array(
			'verify_peer'       => true,
			'verify_peer_name'  => true,
			'verify_depth'      => 3,
		),
	);
	// 后台发信最怕卡死：连接与响应各给 12 秒，超时快速失败而不是挂住请求
	$mailer->Timeout      = 12;
	$mailer->SMTPAutoTLS  = false;
	// 发件人统一走配置值，避免本地域名被服务商拒收
	$mailer->From         = $c['from'] !== '' ? $c['from'] : $c['user'];
	$mailer->FromName     = get_bloginfo( 'name' );
	$mailer->Sender       = $c['user'];
}, 10 );

/**
 * 测试发信（维护工具面板「发送测试邮件」）。
 *
 * 允许使用表单里尚未保存的配置，因此参数由 POST 直接透传，绕过 option 读取。
 */
add_action( 'wp_ajax_jinyu_test_smtp', function () {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_send_json_error( __( '权限不足', 'jinyu' ) );
	}
	// nonce 沿用设置页的 jinyu_save_options（与「保存设置」同一张票）
	check_ajax_referer( 'jinyu_save_options', '_ajax_nonce' );

	jinyu_smtp_require_phpmailer();
	if ( ! class_exists( 'PHPMailer\PHPMailer\PHPMailer' ) ) {
		wp_send_json_error( __( '当前环境缺少 PHPMailer，无法发送测试邮件。', 'jinyu' ) );
	}

	$c = jinyu_smtp_config(
		array(
			'host'   => isset( $_POST['smtp_host'] ) ? wp_unslash( $_POST['smtp_host'] ) : '', // phpcs:ignore WordPress.Security.NonceVerification
			'port'   => isset( $_POST['smtp_port'] ) ? (int) $_POST['smtp_port'] : 0, // phpcs:ignore WordPress.Security.NonceVerification
			'secure' => isset( $_POST['smtp_secure'] ) ? sanitize_text_field( wp_unslash( $_POST['smtp_secure'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification
			'user'   => isset( $_POST['smtp_user'] ) ? sanitize_email( wp_unslash( $_POST['smtp_user'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification
			'pwd'    => isset( $_POST['smtp_pwd'] ) ? wp_unslash( $_POST['smtp_pwd'] ) : '', // phpcs:ignore WordPress.Security.NonceVerification
			'from'   => isset( $_POST['smtp_from'] ) ? sanitize_email( wp_unslash( $_POST['smtp_from'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification
		)
	);

	if ( ! jinyu_smtp_ready( $c ) ) {
		wp_send_json_error( __( 'SMTP 尚未配置完整：需要服务器、发件账号与授权码。', 'jinyu' ) );
	}

	$mail = new PHPMailer\PHPMailer\PHPMailer( true );
	try {
		$mail->isSMTP();
		$mail->Host       = $c['host'];
		$mail->Port       = $c['port'];
		$mail->SMTPSecure = $c['secure'] === 'none' ? '' : $c['secure'];
		$mail->SMTPAuth   = true;
		$mail->Username   = $c['user'];
		$mail->Password   = $c['pwd'];
		$mail->Timeout    = 12;
		$mail->SMTPAutoTLS = false;

		$from = $c['from'] !== '' ? $c['from'] : $c['user'];
		$mail->setFrom( $from, get_bloginfo( 'name' ) );
		$mail->Sender = $c['user'];
		// 收件人：优先测试邮箱输入框，其次站点管理员
		$to = isset( $_POST['smtp_test_to'] ) ? sanitize_email( wp_unslash( $_POST['smtp_test_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( $to === '' || ! is_email( $to ) ) {
			$admin = get_option( 'admin_email' );
			$to    = is_email( $admin ) ? $admin : $c['user'];
		}
		$mail->addAddress( $to );
		$mail->isHTML( false );
		$mail->Subject = sprintf(
			/* translators: %s: 站点名称 */
			__( 'SMTP 测试邮件 · %s', 'jinyu' ),
			get_bloginfo( 'name' )
		);
		$mail->Body = sprintf(
			"这是一封来自「%s」的 SMTP 测试邮件。\n\n服务器：%s\n端口：%d\n加密：%s\n发件人：%s\n收件人：%s\n时间：%s\n",
			get_bloginfo( 'name' ),
			$c['host'],
			$c['port'],
			$c['secure'] === '' ? '无' : $c['secure'],
			$from,
			$to,
			gmdate( 'Y-m-d H:i:s' ) . ' (UTC+0)'
		);

		$mail->send();
		wp_send_json_success(
			sprintf(
				/* translators: %s: 收件邮箱 */
				__( '测试邮件已发送至 %s，请查收（含垃圾箱）。', 'jinyu' ),
				$to
			)
		);
	} catch ( \PHPMailer\PHPMailer\Exception $e ) {
		wp_send_json_error( $e->getMessage() );
	} catch ( \Throwable $e ) {
		wp_send_json_error( __( '发送失败：' ) . $e->getMessage() );
	}
} );
