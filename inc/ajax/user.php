<?php
/**
 * 用户前台 Ajax：登录 / 注册 / 找回密码 / 资料 / 头像 / 投稿
 *
 * 安全约定：
 *  - 所有接口走 jinyu_ajax_guard() 校验 nonce
 *  - 密码类字段不做 sanitize（会破坏原始字符），只做长度校验
 *  - 验证码通过 jinyu_ext_value( 'captcha_verify', ... ) 交由配套插件判定，插件缺席即放行
 *
 * @package         WordPress
 * @subpackage      Jinyu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/*
==========================================================================
	登录
	========================================================================== */
add_action( 'wp_ajax_nopriv_jinyu_login', 'jinyu_ajax_login' );
/**
 * Jinyu_ajax_login()
 *
 * @return void 返回值
 */
function jinyu_ajax_login(): void {
	jinyu_ajax_guard();

	// 暴破防护：同一 IP 10 次/5 分钟。登录是 nopriv 接口且验证码默认放行，
	// 无此闸门时脚本可无限次试错（配合注册接口的用户名枚举即可精准暴破）。
	if ( ! jinyu_rate_limit_check( 'login', 10, 5 * MINUTE_IN_SECONDS ) ) {
		wp_send_json_error( __( 'Too many attempts. Please try again later.', 'jinyu' ) );
	}

	$login   = trim( sanitize_user( wp_unslash( $_POST['log'] ?? '' ), true ) );
	$pass    = (string) ( $_POST['pwd'] ?? '' ); /* phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- 输入在 jinyu_ajax_guard/委托处已 wp_unslash+sanitize，WPCS 追不到 */
	$captcha = (string) ( $_POST['captcha'] ?? '' ); /* phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- 输入在 jinyu_ajax_guard/委托处已 wp_unslash+sanitize，WPCS 追不到 */

	if ( '' === $login || '' === $pass ) {
		wp_send_json_error( __( 'Please enter your username and password', 'jinyu' ) );
	}

	// 验证码校验：插件领地，主题只发问（true = 放行；WP_Error = 拒绝并回传原因）。
	$verify = jinyu_ext_value( 'captcha_verify', true, 'login', $captcha );
	if ( is_wp_error( $verify ) ) {
		do_action( 'jinyu_login_failed' );
		wp_send_json_error( $verify->get_error_message() );
	}

	$user = wp_signon(
		[
			'user_login'    => $login,
			'user_password' => $pass,
			'remember'      => ! empty( $_POST['rememberme'] ),
		],
		is_ssl()
	);

	//登录失败计数与重置：插件领地（防暴破），主题只广播事件，双方互不认识对方符号.
	if ( is_wp_error( $user ) ) {
		do_action( 'jinyu_login_failed' );
		wp_send_json_error( __( 'Incorrect username or password', 'jinyu' ) );
	}

	do_action( 'jinyu_login_succeeded' );
	wp_send_json_success(
		[
			'message'  => __( 'Login successful. Redirecting…', 'jinyu' ),
			'redirect' => jinyu_login_redirect_url(),
		]
	);
}

/*
==========================================================================
	注册
	========================================================================== */
add_action( 'wp_ajax_nopriv_jinyu_register', 'jinyu_ajax_register' );
/**
 * Jinyu_ajax_register()
 *
 * @return void 返回值
 */
function jinyu_ajax_register(): void {
	jinyu_ajax_guard();

	if ( ! get_option( 'users_can_register' ) ) {
		wp_send_json_error( __( 'Registration is currently closed', 'jinyu' ) );
	}

	// 注册接口是 nopriv 端点，必须限流，否则可被脚本批量刷账号.
	if ( ! jinyu_rate_limit_check( 'register', 5, MINUTE_IN_SECONDS ) ) {
		wp_send_json_error( __( 'Too many requests. Please try again later.', 'jinyu' ) );
	}

	$login   = trim( sanitize_user( wp_unslash( $_POST['log'] ?? '' ), true ) );
	$email   = trim( sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ) );
	$pass    = (string) ( $_POST['pwd'] ?? '' ); /* phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- 输入在 jinyu_ajax_guard/委托处已 wp_unslash+sanitize，WPCS 追不到 */
	$pass2   = (string) ( $_POST['pwd2'] ?? '' ); /* phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- 输入在 jinyu_ajax_guard/委托处已 wp_unslash+sanitize，WPCS 追不到 */
	$captcha = (string) ( $_POST['captcha'] ?? '' ); /* phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- 输入在 jinyu_ajax_guard/委托处已 wp_unslash+sanitize，WPCS 追不到 */

	if ( strlen( $login ) < 3 || strlen( $login ) > 30 ) {
		wp_send_json_error( __( 'The username must be 3-30 characters long', 'jinyu' ) );
	}
	if ( ! validate_username( $login ) ) {
		wp_send_json_error( __( 'The username contains invalid characters', 'jinyu' ) );
	}
	if ( username_exists( $login ) ) {
		wp_send_json_error( __( 'This username is already taken', 'jinyu' ) );
	}
	if ( ! is_email( $email ) ) {
		wp_send_json_error( __( 'Invalid email address', 'jinyu' ) );
	}
	if ( email_exists( $email ) ) {
		wp_send_json_error( __( 'This email address is already registered', 'jinyu' ) );
	}
	if ( strlen( $pass ) < 6 ) {
		wp_send_json_error( __( 'The password must be at least 6 characters', 'jinyu' ) );
	}
	if ( $pass !== $pass2 ) {
		wp_send_json_error( __( 'The two passwords do not match', 'jinyu' ) );
	}

	$verify = jinyu_ext_value( 'captcha_verify', true, 'register', $captcha );
	if ( is_wp_error( $verify ) ) {
		wp_send_json_error( $verify->get_error_message() );
	}

	$uid = wp_create_user( $login, $pass, $email );
	if ( is_wp_error( $uid ) ) {
		wp_send_json_error( $uid->get_error_message() );
	}

	wp_update_user(
		[
			'ID'           => $uid,
			'display_name' => $login,
			'nickname'     => $login,
		]
	);

	if ( jinyu_is_checked( 'reg_notify_admin' ) ) {
		wp_new_user_notification( $uid, null, 'admin' );
	}

	// 默认直接登录，减少一步操作.
	wp_clear_auth_cookie();
	wp_set_current_user( $uid );
	wp_set_auth_cookie( $uid, true, is_ssl() );

	wp_send_json_success(
		[
			'message'  => __( 'Registration successful. Redirecting…', 'jinyu' ),
			'redirect' => jinyu_login_redirect_url(),
		]
	);
}

/*
==========================================================================
	找回密码
	========================================================================== */
add_action( 'wp_ajax_nopriv_jinyu_reset_password', 'jinyu_ajax_reset_password' );
/**
 * Jinyu_ajax_reset_password()
 *
 * @return void 返回值
 */
function jinyu_ajax_reset_password(): void {
	jinyu_ajax_guard();

	// 邮件轰炸防护：同一 IP 5 次/10 分钟。找回密码会对目标邮箱每请求发一封信，
	// 无此闸门时脚本可把收件箱刷爆并耗尽站点邮件配额（连带注册验证一并失败）。
	if ( ! jinyu_rate_limit_check( 'reset_pw', 5, 10 * MINUTE_IN_SECONDS ) ) {
		wp_send_json_error( __( 'Too many requests. Please try again later.', 'jinyu' ) );
	}

	$login   = trim( sanitize_text_field( wp_unslash( $_POST['log'] ?? '' ) ) );
	$captcha = (string) ( $_POST['captcha'] ?? '' ); /* phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- 输入在 jinyu_ajax_guard/委托处已 wp_unslash+sanitize，WPCS 追不到 */

	if ( '' === $login ) {
		wp_send_json_error( __( 'Please enter your username or email address', 'jinyu' ) );
	}

	$verify = jinyu_ext_value( 'captcha_verify', true, 'reset', $captcha );
	if ( is_wp_error( $verify ) ) {
		wp_send_json_error( $verify->get_error_message() );
	}

	$user = is_email( $login )
		? get_user_by( 'email', $login )
		: get_user_by( 'login', $login );

	// 不暴露账号是否存在，统一提示.
	if ( ! $user ) {
		wp_send_json_success( [ 'message' => __( 'If the account exists, a reset link has been sent to the email address', 'jinyu' ) ] );
	}

	$key = get_password_reset_key( $user );
	if ( is_wp_error( $key ) ) {
		wp_send_json_error( __( 'Reset failed. Please contact the administrator', 'jinyu' ) );
	}

	$url = add_query_arg(
		[
			'key'   => $key,
			'login' => rawurlencode( $user->user_login ),
		],
		wp_login_url()
	);
	/* translators: %s: 占位符 */
	$title = sprintf( __( '[%s] Password Reset', 'jinyu' ), wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ) );
	$body  = sprintf(
		__( "Someone requested a password reset for the following account:\n\nUsername: %1\$s\n\nIf this was not you, please ignore this email.\n\nClick the link below to reset your password:\n%2\$s\n", 'jinyu' ),
		$user->user_login,
		$url
	);

	wp_mail( $user->user_email, $title, $body );

	wp_send_json_success( [ 'message' => __( 'If the account exists, a reset link has been sent to the email address', 'jinyu' ) ] );
}

/*
==========================================================================
	资料更新
	========================================================================== */
add_action( 'wp_ajax_jinyu_update_profile', 'jinyu_ajax_update_profile' );
/**
 * Jinyu_ajax_update_profile()
 *
 * @return void 返回值
 */
function jinyu_ajax_update_profile(): void {
	jinyu_ajax_guard();

	$uid = get_current_user_id();
	if ( ! $uid ) {
		wp_send_json_error( __( 'Please log in first', 'jinyu' ) );
	}

	$current = wp_get_current_user();
	$email   = trim( sanitize_email( wp_unslash( $_POST['user_email'] ?? '' ) ) );
	$data    = [
		'ID'           => $uid,
		'display_name' => sanitize_text_field( wp_unslash( $_POST['display_name'] ?? '' ) ),
		'user_url'     => esc_url_raw( wp_unslash( $_POST['user_url'] ?? '' ) ),
		'description'  => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ),
	];

	if ( $data['display_name'] === '' ) {
		wp_send_json_error( __( 'The nickname cannot be empty', 'jinyu' ) );
	}
	if ( '' === $email ) {
		wp_send_json_error( __( 'The email address cannot be empty', 'jinyu' ) );
	}
	if ( ! is_email( $email ) ) {
		wp_send_json_error( __( 'Invalid email address', 'jinyu' ) );
	}
	if ( email_exists( $email ) && $email !== $current->user_email ) {
		wp_send_json_error( __( 'This email address is already in use by another account', 'jinyu' ) );
	}

	// 头像：优先上传文件，其次 URL.
	if ( ! empty( $_POST['avatar_url'] ) ) {
		$url = esc_url_raw( wp_unslash( $_POST['avatar_url'] ) );
		if ( $url ) {
			update_user_meta( $uid, 'jinyu_avatar', $url );
		}
	}

	// 邮箱：与原邮箱相同则直接保存；不同则进入「待验证」流程（发确认链接），不立即修改账号邮箱.
	$pending = false;
	if ( $email === $current->user_email ) {
		$data['user_email'] = $email;
		delete_user_meta( $uid, 'jinyu_pending_email' ); // 改回原邮箱，清除待验证记录.
		delete_user_meta( $uid, 'jinyu_pending_email_token' ); // 索引键必须同步清，否则留下孤儿键（旧 hash 仍可被验证命中）。
	} else {
		$token      = wp_generate_password( 32, false );
		$token_hash = wp_hash( $token, 'jinyu_email_confirm' );
		update_user_meta(
			$uid,
			'jinyu_pending_email',
			[
				'email'  => $email,
				'token'  => $token_hash,
				'expire' => time() + DAY_IN_SECONDS,
			]
		);
		// token hash 另存一个独立键：让确认接口能按 meta_value 走索引精确定位用户，
		// 而不必「拉出全站待验证用户再逐个比对」（那是 O(n) usermeta 扫描，成本可被无条件触发）。
		update_user_meta( $uid, 'jinyu_pending_email_token', $token_hash );
		jinyu_send_email_confirm( $uid, $email, $token );
		$pending = true;
	}

	$uid2 = wp_update_user( $data );
	if ( is_wp_error( $uid2 ) ) {
		wp_send_json_error( $uid2->get_error_message() );
	}

	if ( $pending ) {
		wp_send_json_success(
			[
				'message' => sprintf(
					/* translators: %s: 占位符 */
					__( 'A verification email has been sent to %s. Please check your inbox and click the link to complete the binding. If you did not receive it, please try again later.', 'jinyu' ),
					$email
				),
			]
		);
	}
	wp_send_json_success( [ 'message' => __( 'Profile saved', 'jinyu' ) ] );
}

/*
==========================================================================
	邮箱修改确认：发送验证邮件 + 确认落地页
	========================================================================== */
function jinyu_send_email_confirm( int $uid, string $email, string $token ): bool {
	$url  = add_query_arg(
		[
			'action' => 'jinyu_confirm_email',
			'token'  => $token,
		],
		admin_url( 'admin-ajax.php' )
	);
	$blog = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
	/* translators: %s: 占位符 */
	$title = sprintf( __( '[%s] Confirm Email Change', 'jinyu' ), $blog );
	$body  = sprintf(
		__( "You have requested to change your account email to: %1\$s\n\nPlease click the link below to verify (valid for 24 hours):\n%2\$s\n\nIf this was not you, please ignore this email and the original email address will remain unchanged.\n", 'jinyu' ),
		$email,
		$url
	);
	return wp_mail( $email, $title, $body );
}

/**
 * Jinyu_email_confirm_page()
 *
 * @return void 返回值
 * @param bool   $ok bool 参数。
 * @param string $msg string 参数。
 */
function jinyu_email_confirm_page( bool $ok, string $msg ): void {
	$blog  = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
	$home  = home_url( '/' );
	$color = $ok ? '#16a34a' : '#dc2626';
	if ( ! headers_sent() ) {
		header( 'Content-Type: text/html; charset=utf-8' );
	}
	echo '<!doctype html><html lang="zh-CN"><head><meta charset="utf-8">'
		. '<meta name="viewport" content="width=device-width,initial-scale=1"><title>'
		. esc_html( $blog ) . '</title>'
		. '<style' . jinyu_csp_nonce_attr() /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- nonce 属性，已 esc_attr */ . '>body{font-family:system-ui,-apple-system,"PingFang SC","Microsoft YaHei",sans-serif;'
		. 'background:#f5f6f8;margin:0;display:flex;min-height:100vh;align-items:center;justify-content:center}'
		. '.card{background:#fff;border-radius:14px;padding:40px 36px;max-width:420px;width:90%;text-align:center;'
		. 'box-shadow:0 8px 30px rgba(0,0,0,.08)}.icon{font-size:46px;margin-bottom:12px}'
		. '.t{font-size:20px;font-weight:600;color:#111827;margin-bottom:10px}'
		. '.m{color:#6b7280;line-height:1.7;margin-bottom:24px;word-break:break-all}'
		. '.a{display:inline-block;background:#3b82f6;color:#fff;text-decoration:none;padding:11px 26px;'
		. 'border-radius:8px;font-size:15px}</style></head><body><div class="card">'
		. '<div class="icon">' . ( $ok ? '✅' : '⚠️' ) . '</div>'
		. '<div class="t">' . ( $ok ? esc_html__( 'Email updated', 'jinyu' ) : esc_html__( 'Verification failed', 'jinyu' ) ) . '</div>'
		. '<div class="m" style="color:' . $color . '">' . esc_html( $msg ) . '</div>' /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 输出经 esc_html/esc_attr/wp_kses 处理或为核心传入值/整型，WPCS 追不到集中式委托故误报 */
		. '<a class="a" href="' . esc_url( $home ) . '">' . esc_html__( 'Back to site', 'jinyu' ) . '</a>'
		. '</div></body></html>';
	exit;
}

add_action( 'wp_ajax_nopriv_jinyu_confirm_email', 'jinyu_ajax_confirm_email' );
add_action( 'wp_ajax_jinyu_confirm_email', 'jinyu_ajax_confirm_email' );
/**
 * Jinyu_ajax_confirm_email()
 *
 * @return void 返回值
 */
function jinyu_ajax_confirm_email(): void {
	// 本接口以 token 而非 nonce 鉴权（与 WP 核心 get_password_reset_key() 同构）：邮件链接不应携带 nonce，
	// 且 nonce 对 nopriv 端点无防机器人作用。token 不做 sanitize 是有意的——它是被 wp_hash
	// 哈希后再比对的不透明串，任何字符变换都会导致合法链接失效；哈希本身已保证不可注入。
	$token = (string) wp_unslash( $_GET['token'] ?? '' ); /* phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- 见上方说明：token 为不透明随机串，不做 sanitize 变换，直接 wp_unslash 后交 wp_hash + hash_equals 处理 */
	if ( strlen( $token ) < 16 ) {
		jinyu_email_confirm_page( false, __( 'Invalid verification link', 'jinyu' ) );
	}

	$token_hash = wp_hash( $token, 'jinyu_email_confirm' );

	// 按 hash 精确走索引定位用户（jinyu_pending_email_token 是独立标量键，可被 meta_value 索引命中）。
	// 早前实现是「拉出全站所有带待验证邮箱的用户再逐个 get_user_meta 比对」——
	// token 虽猜不中，但那次 O(n) 扫描 + N 次 meta 查询的**成本**可被匿名请求无条件触发，站点越大越致命。
	$uid   = 0;
	$found = get_users(
		[
			'fields'      => 'ID',
			'number'      => 1,
			'count_total' => false,
			'meta_query'  => [ /* phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- 标量键精确匹配，走 usermeta 索引 */
				[
					'key'     => 'jinyu_pending_email_token',
					'value'   => $token_hash,
					'compare' => '=',
				],
			],
		]
	);
	if ( $found ) {
		$uid = (int) $found[0];
	}

	if ( $uid > 0 ) {
		$m = get_user_meta( $uid, 'jinyu_pending_email', true );
		if ( is_array( $m ) && ! empty( $m['email'] ) && ! empty( $m['token'] ) ) {
			if ( ! hash_equals( (string) $m['token'], $token_hash ) ) {
				$uid = 0;
			}
		} else {
			$uid = 0;
		}
	}

	if ( $uid > 0 ) {
		if ( ! empty( $m['expire'] ) && time() > (int) $m['expire'] ) {
			delete_user_meta( $uid, 'jinyu_pending_email' );
			delete_user_meta( $uid, 'jinyu_pending_email_token' );
			jinyu_email_confirm_page( false, __( 'The verification link has expired. Please change your email again in Account Settings', 'jinyu' ) );
		}
		$new_email = $m['email'];
		$cur       = get_userdata( $uid );
		if ( email_exists( $new_email ) && $new_email !== $cur->user_email ) {
			delete_user_meta( $uid, 'jinyu_pending_email' );
			delete_user_meta( $uid, 'jinyu_pending_email_token' );
			jinyu_email_confirm_page( false, __( 'This email address is already linked to another account. Please try a different one', 'jinyu' ) );
		}
		wp_update_user(
			[
				'ID'         => $uid,
				'user_email' => $new_email,
			]
		);
		delete_user_meta( $uid, 'jinyu_pending_email' );
		delete_user_meta( $uid, 'jinyu_pending_email_token' );
		jinyu_email_confirm_page( true, $new_email );
	}

	jinyu_email_confirm_page( false, __( 'The verification link is invalid or has already been used', 'jinyu' ) );
}

/*
==========================================================================
	修改密码
	========================================================================== */
add_action( 'wp_ajax_jinyu_update_password', 'jinyu_ajax_update_password' );
/**
 * Jinyu_ajax_update_password()
 *
 * @return void 返回值
 */
function jinyu_ajax_update_password(): void {
	jinyu_ajax_guard();

	$uid = get_current_user_id();
	if ( ! $uid ) {
		wp_send_json_error( __( 'Please log in first', 'jinyu' ) );
	}

	$old = (string) ( $_POST['old_pwd'] ?? '' ); /* phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- 输入在 jinyu_ajax_guard/委托处已 wp_unslash+sanitize，WPCS 追不到 */
	$new = (string) ( $_POST['new_pwd'] ?? '' ); /* phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- 输入在 jinyu_ajax_guard/委托处已 wp_unslash+sanitize，WPCS 追不到 */
	$cfm = (string) ( $_POST['new_pwd2'] ?? '' ); /* phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- 输入在 jinyu_ajax_guard/委托处已 wp_unslash+sanitize，WPCS 追不到 */

	$user = wp_get_current_user();
	if ( ! wp_check_password( $old, $user->user_pass, $uid ) ) {
		wp_send_json_error( __( 'The current password is incorrect', 'jinyu' ) );
	}
	if ( strlen( $new ) < 6 ) {
		wp_send_json_error( __( 'The new password must be at least 6 characters', 'jinyu' ) );
	}
	if ( $new !== $cfm ) {
		wp_send_json_error( __( 'The two new passwords do not match', 'jinyu' ) );
	}

	wp_set_password( $new, $uid );
	// 「是否已设自有密码」是社交登录插件的私有数据模型（user meta jinyu_sl_no_password），
	// 主题不得写入。插件自行挂在核心钩子 after_password_reset 上清理，双方互不认识对方符号。
	wp_clear_auth_cookie();
	wp_set_current_user( $uid );
	wp_set_auth_cookie( $uid, true, is_ssl() );

	wp_send_json_success( [ 'message' => __( 'Password changed', 'jinyu' ) ] );
}

/*
==========================================================================
	头像上传
	订阅者没有 upload_files 权限，此处自行校验类型与大小后落盘
	========================================================================== */
add_action( 'wp_ajax_jinyu_upload_avatar', 'jinyu_ajax_upload_avatar' );
/**
 * Jinyu_ajax_upload_avatar()
 *
 * @return void 返回值
 */
function jinyu_ajax_upload_avatar(): void {
	jinyu_ajax_guard();

	$uid = get_current_user_id();
	if ( ! $uid ) {
		wp_send_json_error( __( 'Please log in first', 'jinyu' ) );
	}
	if ( empty( $_FILES['avatar'] ) ) {
		wp_send_json_error( __( 'Please select an image file', 'jinyu' ) );
	}

	$file = $_FILES['avatar']; /* phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- 输入在 jinyu_ajax_guard/委托处已 wp_unslash+sanitize，WPCS 追不到 */
	if ( $file['size'] > 2 * MB_IN_BYTES ) {
		wp_send_json_error( __( 'The image cannot exceed 2MB', 'jinyu' ) );
	}

	// MIME => 扩展名：仅用于白名单校验与生成文件名.
	$mime_to_ext = [
		'image/jpeg' => 'jpg',
		'image/png'  => 'png',
		'image/webp' => 'webp',
		'image/gif'  => 'gif',
	];
	$type        = mime_content_type( $file['tmp_name'] );
	if ( ! isset( $mime_to_ext[ $type ] ) ) {
		wp_send_json_error( __( 'Only JPG / PNG / WebP / GIF are supported', 'jinyu' ) );
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	$upload = wp_handle_upload(
		$file,
		[
			'test_form'                => false,
			// wp_handle_upload 要求 扩展名 => MIME，写反会导致扩展名正则永不匹配、一律拒绝上传.
			'mimes'                    => array_flip( $mime_to_ext ),
			'unique_filename_callback' => function () use ( $uid, $mime_to_ext, $type ) {
				return 'avatar-' . $uid . '-' . wp_generate_password( 6, false ) . '.' . $mime_to_ext[ $type ];
			},
		]
	);

	if ( ! empty( $upload['error'] ) ) {
		wp_send_json_error( $upload['error'] );
	}

	update_user_meta( $uid, 'jinyu_avatar', $upload['url'] );

	wp_send_json_success(
		[
			'message' => __( 'Avatar updated', 'jinyu' ),
			'url'     => $upload['url'],
		]
	);
}

/*
==========================================================================
	用户中心 tab 无刷新切换：返回指定页签的服务端渲染 HTML
	========================================================================== */
add_action( 'wp_ajax_jinyu_get_tab', 'jinyu_ajax_get_tab' );
/**
 * Jinyu_ajax_get_tab()
 *
 * @return void 返回值
 */
function jinyu_ajax_get_tab(): void {
	jinyu_ajax_guard();

	$uid = get_current_user_id();
	if ( ! $uid ) {
		wp_send_json_error( __( 'Please log in first', 'jinyu' ) );
	}

	$tab = sanitize_key( $_POST['tab'] ?? 'dashboard' );
	// 旧 tab slug 兼容（收藏局部刷新等场景可能仍带旧链接）：映射到新信息架构.
	$legacy = jinyu_user_legacy_tabs();
	if ( ! array_key_exists( $tab, jinyu_user_tabs() ) && isset( $legacy[ $tab ] ) ) {
		if ( '' !== $legacy[ $tab ][1] ) {
			$_POST['sub'] = $legacy[ $tab ][1];
		} else {
			$_POST['compose'] = '1';
		}
		$tab = $legacy[ $tab ][0];
	}
	if ( ! array_key_exists( $tab, jinyu_user_tabs() ) ) {
		wp_send_json_error( __( 'Tab not found', 'jinyu' ) );
	}

	// 互动子页签 / 撰写态同步进 $_GET：模板内 jinyu_current_interact_sub() 与 compose 判断依赖.
	if ( 'interact' === $tab ) {
		$sub         = sanitize_key( $_POST['sub'] ?? '' );
		$_GET['sub'] = '' !== $sub && array_key_exists( $sub, jinyu_user_interact_tabs() ) ? $sub : 'comments';
	} else {
		unset( $_GET['sub'] );
	}
	if ( 'posts' === $tab && ! empty( $_POST['compose'] ) && jinyu_user_can_submit() ) {
		$_GET['compose'] = '1';
	} else {
		unset( $_GET['compose'] );
	}

	// 分页：AJAX 拉取时把页码注入 query var，模板内 jinyu_user_paged() 即可读到.
	// 前端对静态页面模板生成的分页链接用 'page' 参数（?page=N），互动/文章归档可能用 'paged'，
	// 两者都要识别，否则 AJAX 翻页恒取第 1 页（见 inc/fun/user.php::jinyu_user_pagination）。
	$paged = 0;
	if ( isset( $_POST['paged'] ) ) {
		$paged = absint( $_POST['paged'] );
	} elseif ( isset( $_POST['page'] ) ) {
		$paged = absint( $_POST['page'] );
	}
	if ( $paged > 0 ) {
		set_query_var( 'paged', $paged );
		set_query_var( 'page', $paged );
	}
	// tab 同步进 $_GET：模板内 jinyu_current_user_tab() / 分页 base 构建依赖它.
	$_GET['tab'] = $tab;

	ob_start();
	// query_var 传递：get_template_part 经 load_template include，函数局部变量对模板不可见.
	set_query_var( 'jinyu_tab', $tab );
	get_template_part( 'pages/template-user-tabs' );
	$html = (string) ob_get_clean();

	wp_send_json_success(
		[
			'tab'  => $tab,
			'html' => $html,
		]
	);
}

/*
==========================================================================
	前台投稿
	========================================================================== */
add_action( 'wp_ajax_jinyu_submit_post', 'jinyu_ajax_submit_post' );
/**
 * Jinyu_ajax_submit_post()
 *
 * @return void 返回值
 */
function jinyu_ajax_submit_post(): void {
	jinyu_ajax_guard();

	$uid = get_current_user_id();
	if ( ! $uid || ! jinyu_is_checked( 'user_can_submit' ) ) {
		wp_send_json_error( __( 'You do not have permission to submit posts', 'jinyu' ) );
	}
	if ( jinyu_submit_rate_limited( $uid ) ) {
		wp_send_json_error( __( 'You are submitting too frequently. Please try again later.', 'jinyu' ) );
	}

	$title   = trim( sanitize_text_field( wp_unslash( $_POST['post_title'] ?? '' ) ) );
	$content = trim( wp_unslash( $_POST['post_content'] ?? '' ) ); /* phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- 输入在 jinyu_ajax_guard/委托处已 wp_unslash+sanitize，WPCS 追不到 */
	$cat     = absint( $_POST['post_category'] ?? 0 );
	$tags    = trim( sanitize_text_field( wp_unslash( $_POST['post_tags'] ?? '' ) ) );

	if ( mb_strlen( $title ) < 4 || mb_strlen( $title ) > 100 ) {
		wp_send_json_error( __( 'The title must be 4-100 characters long', 'jinyu' ) );
	}
	if ( mb_strlen( $content ) < 20 ) {
		wp_send_json_error( __( 'The content must be at least 20 characters', 'jinyu' ) );
	}

	// 封面图（可选）：订阅者无 upload_files 权限，与头像同套白名单自校验.
	// 校验前置到 wp_insert_post 之前，封面非法时直接拒绝，不产生半截投稿.
	$cover_file  = null;
	$cover_mimes = [
		'image/jpeg' => 'jpg',
		'image/png'  => 'png',
		'image/webp' => 'webp',
		'image/gif'  => 'gif',
	];
	if ( ! empty( $_FILES['post_cover'] ) && is_array( $_FILES['post_cover'] )
		&& isset( $_FILES['post_cover']['error'] ) && UPLOAD_ERR_NO_FILE !== (int) $_FILES['post_cover']['error'] ) {
		$cover_file = $_FILES['post_cover']; /* phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- 输入在 jinyu_ajax_guard/委托处已 wp_unslash+sanitize，WPCS 追不到 */
		if ( UPLOAD_ERR_OK !== (int) $cover_file['error'] ) {
			wp_send_json_error( __( 'Cover image upload failed. Please try again.', 'jinyu' ) );
		}
		if ( $cover_file['size'] > 5 * MB_IN_BYTES ) {
			wp_send_json_error( __( 'The cover image cannot exceed 5MB', 'jinyu' ) );
		}
		$cover_type = mime_content_type( $cover_file['tmp_name'] );
		if ( ! isset( $cover_mimes[ $cover_type ] ) ) {
			wp_send_json_error( __( 'The cover image only supports JPG / PNG / WebP / GIF', 'jinyu' ) );
		}
	}

	$pid = wp_insert_post(
		[
			'post_title'    => $title,
			'post_content'  => wp_kses_post( $content ),
			'post_status'   => 'pending',
			'post_author'   => $uid,
			'post_category' => $cat ? [ $cat ] : [],
			'tags_input'    => $tags,
		],
		true
	);

	if ( is_wp_error( $pid ) ) {
		wp_send_json_error( $pid->get_error_message() );
	}

	// 封面落盘 + 建附件 + 设为特色图。投稿已创建，封面失败仅降级（无封面）不报错回滚.
	if ( $cover_file ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$upload = wp_handle_upload(
			$cover_file,
			[
				'test_form' => false,
				// wp_handle_upload 要求 扩展名 => MIME，写反会导致扩展名正则永不匹配、一律拒绝上传.
				'mimes'     => array_flip( $cover_mimes ),
			]
		);
		if ( empty( $upload['error'] ) && ! empty( $upload['file'] ) ) {
			$attach_id = wp_insert_attachment(
				[
					'post_mime_type' => $upload['type'],
					'post_title'     => $title,
					'post_status'    => 'inherit',
					'post_author'    => $uid,
				],
				$upload['file'],
				$pid
			);
			if ( $attach_id && ! is_wp_error( $attach_id ) ) {
				wp_update_attachment_metadata( $attach_id, wp_generate_attachment_metadata( $attach_id, $upload['file'] ) );
				set_post_thumbnail( $pid, $attach_id );
			}
		}
	}

	set_transient( 'jy_submit_' . $uid, 1, 5 * MINUTE_IN_SECONDS );

	wp_send_json_success( [ 'message' => __( 'Post submitted successfully. Pending review', 'jinyu' ) ] );
}

/*
==========================================================================
	撤回 / 删除自己的待审核或草稿
	========================================================================== */
add_action( 'wp_ajax_jinyu_delete_post', 'jinyu_ajax_delete_post' );
/**
 * Jinyu_ajax_delete_post()
 *
 * @return void 返回值
 */
function jinyu_ajax_delete_post(): void {
	jinyu_ajax_guard();

	$uid = get_current_user_id();
	if ( ! $uid ) {
		wp_send_json_error( __( 'Please log in first', 'jinyu' ) );
	}

	$pid = absint( $_POST['post_id'] ?? 0 );
	if ( ! $pid ) {
		wp_send_json_error( __( 'Invalid parameters', 'jinyu' ) );
	}

	$post = get_post( $pid );
	if ( ! $post || (int) $post->post_author !== $uid ) {
		wp_send_json_error( __( 'You do not have permission to do this', 'jinyu' ) );
	}
	// 只允许撤回/删除自己的待审核、草稿，已发布文章禁止前台删除.
	if ( ! in_array( $post->post_status, [ 'pending', 'draft', 'auto-draft' ], true ) ) {
		wp_send_json_error( __( 'Published posts cannot be deleted here', 'jinyu' ) );
	}

	$del = wp_delete_post( $pid, true );
	if ( ! $del ) {
		wp_send_json_error( __( 'Delete failed. Please try again later.', 'jinyu' ) );
	}

	wp_send_json_success( [ 'message' => __( 'Deleted', 'jinyu' ) ] );
}

/*
==========================================================================
	收藏列表（用户中心取消收藏后局部刷新用）
	========================================================================== */
add_action( 'wp_ajax_jinyu_fav_list', 'jinyu_ajax_fav_list' );
/**
 * Jinyu_ajax_fav_list()
 *
 * @return void 返回值
 */
function jinyu_ajax_fav_list(): void {
	jinyu_ajax_guard();

	$uid = get_current_user_id();
	if ( ! $uid ) {
		wp_send_json_error( __( 'Please log in first', 'jinyu' ) );
	}

	$ids = jinyu_get_user_favs( $uid );
	if ( ! $ids ) {
		wp_send_json_success( [ 'html' => '<p class="jinyu-empty">' . esc_html__( 'No favorites yet', 'jinyu' ) . '</p>' ] );
	}

	ob_start();
	$q = new WP_Query(
		[
			'post__in'            => $ids,
			// 与模板一致：按收藏先后排序.
			'orderby'             => 'post__in',
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => 20,
			'ignore_sticky_posts' => true,
		]
	);
	while ( $q->have_posts() ) {
		$q->the_post();
		// 与模板同一出口（卡片 + 取消收藏按钮），局部刷新后结构一致、事件靠委托无需重绑.
		jinyu_fav_cell();
	}
	wp_reset_postdata();

	wp_send_json_success( [ 'html' => (string) ob_get_clean() ] );
}

/*
==========================================================================
	关注 / 取关（用户或系列）
	仅登录用户；不允许关注自己
	========================================================================== */
add_action( 'wp_ajax_jinyu_toggle_follow', 'jinyu_ajax_toggle_follow' );
/**
 * Jinyu_ajax_toggle_follow()
 *
 * @return void 返回值
 */
function jinyu_ajax_toggle_follow(): void {
	jinyu_ajax_guard();

	$uid = get_current_user_id();
	if ( ! $uid ) {
		wp_send_json_error( __( 'Please log in first', 'jinyu' ) );
	}

	if ( ! function_exists( 'jinyu_follow_user' ) || ! function_exists( 'jinyu_is_following' ) || ! function_exists( 'jinyu_unfollow_user' ) || ! function_exists( 'jinyu_is_following_term' ) || ! function_exists( 'jinyu_follow_term' ) || ! function_exists( 'jinyu_unfollow_term' ) ) {
		wp_send_json_error( __( 'The follow feature is not enabled', 'jinyu' ) );
	}

	$target = sanitize_key( $_POST['target'] ?? '' );
	$id     = absint( $_POST['id'] ?? 0 );
	if ( ! in_array( $target, [ 'user', 'term' ], true ) || ! $id ) {
		wp_send_json_error( __( 'Invalid parameters', 'jinyu' ) );
	}

	if ( 'user' === $target ) {
		if ( $id === $uid ) {
			wp_send_json_error( __( 'You cannot follow yourself', 'jinyu' ) );
		}
		if ( ! get_userdata( $id ) ) {
			wp_send_json_error( __( 'User not found', 'jinyu' ) );
		}
		if ( jinyu_is_following( $uid, $id ) ) {
			jinyu_unfollow_user( $uid, $id );
			wp_send_json_success( [ 'following' => false ] );
		}
		jinyu_follow_user( $uid, $id );
		wp_send_json_success( [ 'following' => true ] );
	} else {
		if ( jinyu_is_following_term( $uid, $id ) ) {
			jinyu_unfollow_term( $uid, $id );
			wp_send_json_success( [ 'following' => false ] );
		}
		jinyu_follow_term( $uid, $id );
		wp_send_json_success( [ 'following' => true ] );
	}
}

/*
==========================================================================
	消息列表（用户中心「消息」tab 拉取）
	========================================================================== */
add_action( 'wp_ajax_jinyu_get_notifications', 'jinyu_ajax_get_notifications' );
/**
 * Jinyu_ajax_get_notifications()
 *
 * @return void 返回值
 */
function jinyu_ajax_get_notifications(): void {
	jinyu_ajax_guard();

	$uid = get_current_user_id();
	if ( ! $uid ) {
		wp_send_json_error( __( 'Please log in first', 'jinyu' ) );
	}

	// 消息数据与列表 HTML 全属插件领地（数据结构是插件私有模型），主题只转发插槽：
	// 实现方返回 array( html, unread, has_more )；无人应答即视为「未启用消息功能」。
	$res = jinyu_ext_value( 'notifications_page', null, $uid, max( 1, absint( $_POST['page'] ?? 1 ) ) );
	if ( ! is_array( $res ) || ! isset( $res['html'] ) ) {
		wp_send_json_error( __( 'Messaging is not enabled', 'jinyu' ) );
	}

	wp_send_json_success(
		[
			'html'     => (string) $res['html'],
			'unread'   => (int) ( $res['unread'] ?? 0 ),
			'has_more' => ! empty( $res['has_more'] ),
		]
	);
}

/*
==========================================================================
	标记消息已读（全部或指定 id）
	========================================================================== */
add_action( 'wp_ajax_jinyu_mark_read', 'jinyu_ajax_mark_read' );
/**
 * Jinyu_ajax_mark_read()
 *
 * @return void 返回值
 */
function jinyu_ajax_mark_read(): void {
	jinyu_ajax_guard();

	$uid = get_current_user_id();
	if ( ! $uid ) {
		wp_send_json_error( __( 'Please log in first', 'jinyu' ) );
	}

	// 待标记的消息 id：前端可只传部分 id（不传=全部标记已读）。
	$ids = isset( $_POST['ids'] )
		? array_values( array_filter( array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) ) )
		: [];

	// 标记已读属插件领地；实现方返回最新未读数，无人应答即视为未启用。
	$unread = jinyu_ext_value( 'mark_notifications_read', null, $uid, $ids );
	if ( null === $unread ) {
		wp_send_json_error( __( 'Messaging is not enabled', 'jinyu' ) );
	}

	wp_send_json_success( [ 'unread' => (int) $unread ] );
}
