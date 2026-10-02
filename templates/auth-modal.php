<?php
/**
 * 登录 / 注册弹窗模板片段
 *
 * @package         WordPress
 * @subpackage      Jinyu
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 登录 / 注册 / 找回密码弹窗
 * 由 footer.php 全局引入；未开启用户中心时不输出。
 */
if ( ! jinyu_is_checked( 'user_center_enable' ) ) {
	return;
}

$nonce = wp_create_nonce( 'jinyu_front' );
?>
<div class="jinyu-mask jinyu-auth-mask" hidden role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Log in or register', 'jinyu' ); ?>">
	<div class="jinyu-auth">
	<button type="button" class="jinyu-auth-close" data-jinyu-auth-close aria-label="<?php esc_attr_e( 'Close', 'jinyu' ); ?>">
		<i class="fa-solid fa-xmark" aria-hidden="true"></i>
	</button>

	<?php
	// 弹窗 Logo 只取图标不取整张站点 Logo（Logo 图含站名文字，与下方标题重复）.
	// 回退链：WP 站点图标 → Web 根目录 /favicon.ico → 火苗图标兜底（img 加载失败自动露出火苗）.
	$auth_icon_url = trim( (string) get_site_icon_url( 192 ) );
	if ( '' === $auth_icon_url ) {
		$auth_icon_url = esc_url( home_url( '/favicon.ico' ) );
	}
	$auth_logo_alt = esc_attr( get_bloginfo( 'name' ) );
	?>
	<header class="jinyu-auth-head">
		<div class="jinyu-auth-logo has-img">
		<img src="<?php echo esc_url( $auth_icon_url ); ?>" alt="<?php echo $auth_logo_alt;  /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 输出经 esc_html/esc_attr/wp_kses 处理或为核心传入值/整型，WPCS 追不到集中式委托故误报 */ ?>"
			data-jinyu-fallback-remove data-jinyu-fallback-unset="has-img">
		<i class="fa-solid fa-fire" aria-hidden="true"></i>
		</div>
		<h2><?php bloginfo( 'name' ); ?></h2>
	</header>

	<div class="jinyu-auth-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Log in or register', 'jinyu' ); ?>" data-jinyu-auth-tabs>
		<button type="button" role="tab" id="jinyu-auth-tab-login" aria-controls="jinyu-auth-pane-login" aria-selected="true" class="is-active" data-jinyu-auth-tab="login"><?php esc_html_e( 'Log in', 'jinyu' ); ?></button>
		<button type="button" role="tab" id="jinyu-auth-tab-register" aria-controls="jinyu-auth-pane-register" aria-selected="false" tabindex="-1" data-jinyu-auth-tab="register"><?php esc_html_e( 'Register', 'jinyu' ); ?></button>
		<button type="button" role="tab" id="jinyu-auth-tab-reset" aria-controls="jinyu-auth-pane-reset" aria-selected="false" tabindex="-1" data-jinyu-auth-tab="reset"><?php esc_html_e( 'Forgot password?', 'jinyu' ); ?></button>
	</div>

	<!-- 登录 -->
	<form class="jinyu-auth-form" id="jinyu-auth-pane-login" role="tabpanel" aria-labelledby="jinyu-auth-tab-login" data-jinyu-auth-pane="login" method="post" novalidate>
		<input type="hidden" name="_ajax_nonce" value="<?php echo esc_attr( $nonce ); ?>">
		<label class="jinyu-field">
		<i class="fa-solid fa-user" aria-hidden="true"></i>
		<span class="jinyu-sr-only"><?php esc_html_e( 'Username or email address', 'jinyu' ); ?></span>
		<input type="text" name="log" autocomplete="username" enterkeyhint="next" placeholder="<?php esc_attr_e( 'Username or email address', 'jinyu' ); ?>">
		</label>
		<label class="jinyu-field">
		<i class="fa-solid fa-lock" aria-hidden="true"></i>
		<span class="jinyu-sr-only"><?php esc_html_e( 'Password', 'jinyu' ); ?></span>
		<input type="password" name="pwd" autocomplete="current-password" enterkeyhint="go" placeholder="<?php esc_attr_e( 'Password', 'jinyu' ); ?>">
		<button type="button" class="jinyu-pw-toggle" data-jinyu-pw-toggle aria-label="<?php esc_attr_e( 'Show password', 'jinyu' ); ?>" aria-pressed="false">
			<i class="fa-solid fa-eye" aria-hidden="true"></i>
		</button>
		</label>
		<div class="jinyu-auth-row">
		<label class="jinyu-auth-remember">
			<input type="checkbox" name="rememberme" value="1" checked>
			<span><?php esc_html_e( 'Remember me', 'jinyu' ); ?></span>
		</label>
		<button type="button" class="jinyu-auth-forgot" data-jinyu-auth-goto="reset"><?php esc_html_e( 'Forgot password?', 'jinyu' ); ?></button>
		</div>
		<?php echo jinyu_ext_markup( 'captcha', 'login' );  /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 扩展插槽 HTML 由插件自行转义输出 */ ?>
		<button type="submit" class="jinyu-btn jinyu-btn-primary jinyu-auth-submit"><?php esc_html_e( 'Log in', 'jinyu' ); ?></button>
		<p class="jinyu-auth-tip" data-jinyu-auth-tip aria-live="polite"></p>
	</form>

	<!-- 注册 -->
	<form class="jinyu-auth-form" id="jinyu-auth-pane-register" role="tabpanel" aria-labelledby="jinyu-auth-tab-register" data-jinyu-auth-pane="register" method="post" hidden novalidate>
		<input type="hidden" name="_ajax_nonce" value="<?php echo esc_attr( $nonce ); ?>">
		<label class="jinyu-field">
		<i class="fa-solid fa-user" aria-hidden="true"></i>
		<span class="jinyu-sr-only"><?php esc_html_e( 'Username (3-30 characters)', 'jinyu' ); ?></span>
		<input type="text" name="log" autocomplete="username" enterkeyhint="next" placeholder="<?php esc_attr_e( 'Username (3-30 characters)', 'jinyu' ); ?>">
		</label>
		<label class="jinyu-field">
		<i class="fa-solid fa-envelope" aria-hidden="true"></i>
		<span class="jinyu-sr-only"><?php esc_html_e( 'Email', 'jinyu' ); ?></span>
		<input type="email" name="email" autocomplete="email" enterkeyhint="next" placeholder="<?php esc_attr_e( 'Email', 'jinyu' ); ?>">
		</label>
		<label class="jinyu-field">
		<i class="fa-solid fa-lock" aria-hidden="true"></i>
		<span class="jinyu-sr-only"><?php esc_html_e( 'Password (at least 6 characters)', 'jinyu' ); ?></span>
		<input type="password" name="pwd" autocomplete="new-password" enterkeyhint="next" placeholder="<?php esc_attr_e( 'Password (at least 6 characters)', 'jinyu' ); ?>">
		<button type="button" class="jinyu-pw-toggle" data-jinyu-pw-toggle aria-label="<?php esc_attr_e( 'Show password', 'jinyu' ); ?>" aria-pressed="false">
			<i class="fa-solid fa-eye" aria-hidden="true"></i>
		</button>
		</label>
		<label class="jinyu-field">
		<i class="fa-solid fa-lock" aria-hidden="true"></i>
		<span class="jinyu-sr-only"><?php esc_html_e( 'Confirm password', 'jinyu' ); ?></span>
		<input type="password" name="pwd2" autocomplete="new-password" enterkeyhint="go" placeholder="<?php esc_attr_e( 'Confirm password', 'jinyu' ); ?>">
		<button type="button" class="jinyu-pw-toggle" data-jinyu-pw-toggle aria-label="<?php esc_attr_e( 'Show password', 'jinyu' ); ?>" aria-pressed="false">
			<i class="fa-solid fa-eye" aria-hidden="true"></i>
		</button>
		</label>
		<?php echo jinyu_ext_markup( 'captcha', 'register' );  /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 扩展插槽 HTML 由插件自行转义输出 */ ?>
		<button type="submit" class="jinyu-btn jinyu-btn-primary jinyu-auth-submit"><?php esc_html_e( 'Register', 'jinyu' ); ?></button>
		<p class="jinyu-auth-tip" data-jinyu-auth-tip aria-live="polite"></p>
	</form>

	<!-- 找回密码 -->
	<form class="jinyu-auth-form" id="jinyu-auth-pane-reset" role="tabpanel" aria-labelledby="jinyu-auth-tab-reset" data-jinyu-auth-pane="reset" method="post" hidden novalidate>
		<input type="hidden" name="_ajax_nonce" value="<?php echo esc_attr( $nonce ); ?>">
		<p class="jinyu-auth-hint"><?php esc_html_e( 'Enter your username or email address and a reset link will be sent to your inbox.', 'jinyu' ); ?></p>
		<label class="jinyu-field">
		<i class="fa-solid fa-user" aria-hidden="true"></i>
		<span class="jinyu-sr-only"><?php esc_html_e( 'Username or email address', 'jinyu' ); ?></span>
		<input type="text" name="log" autocomplete="username" enterkeyhint="next" placeholder="<?php esc_attr_e( 'Username or email address', 'jinyu' ); ?>">
		</label>
		<?php echo jinyu_ext_markup( 'captcha', 'reset' );  /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 扩展插槽 HTML 由插件自行转义输出 */ ?>
		<button type="submit" class="jinyu-btn jinyu-btn-primary jinyu-auth-submit"><?php esc_html_e( 'Send reset link', 'jinyu' ); ?></button>
		<p class="jinyu-auth-tip" data-jinyu-auth-tip aria-live="polite"></p>
	</form>

	<?php
	// 第三方登录入口：插件领地，主题只留插槽（jinyu_ext_markup 内部 apply_filters 广播），不认识任何插件符号。
	$jinyu_oauth_html = jinyu_ext_markup( 'oauth_login' );
	if ( '' !== $jinyu_oauth_html ) :
		?>
		<div class="jinyu-auth-divider"><span><?php esc_html_e( 'Quick login', 'jinyu' ); ?></span></div>
		<?php echo $jinyu_oauth_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 扩展插槽 HTML 由插件自行转义输出 ?>
	<?php endif; ?>
	</div>
</div>
