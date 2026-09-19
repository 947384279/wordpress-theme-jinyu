<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 登录 / 注册 / 找回密码弹窗
 * 由 footer.php 全局引入；未开启用户中心时不输出。
 */
if (!jinyu_is_checked('user_center_enable')) {
    return;
}

$nonce = wp_create_nonce('jinyu_front');
?>
<div class="jinyu-mask jinyu-auth-mask" hidden role="dialog" aria-modal="true" aria-label="<?php esc_attr_e('登录或注册', JINYU); ?>">
  <div class="jinyu-auth">
    <button type="button" class="jinyu-auth-close" data-jinyu-auth-close aria-label="<?php esc_attr_e('关闭', JINYU); ?>">
      <i class="fa-solid fa-xmark" aria-hidden="true"></i>
    </button>

    <header class="jinyu-auth-head">
      <div class="jinyu-auth-logo"><i class="fa-solid fa-fire" aria-hidden="true"></i></div>
      <h3><?php bloginfo('name'); ?></h3>
    </header>

    <nav class="jinyu-auth-tabs" data-jinyu-auth-tabs>
      <button type="button" class="is-active" data-jinyu-auth-tab="login"><?php esc_html_e('登录', JINYU); ?></button>
      <button type="button" data-jinyu-auth-tab="register"><?php esc_html_e('注册', JINYU); ?></button>
      <button type="button" data-jinyu-auth-tab="reset"><?php esc_html_e('找回密码', JINYU); ?></button>
    </nav>

    <!-- 登录 -->
    <form class="jinyu-auth-form" data-jinyu-auth-pane="login" method="post" novalidate>
      <input type="hidden" name="_ajax_nonce" value="<?php echo esc_attr($nonce); ?>">
      <label class="jinyu-field">
        <i class="fa-solid fa-user" aria-hidden="true"></i>
        <input type="text" name="log" autocomplete="username" placeholder="<?php esc_attr_e('用户名或邮箱', JINYU); ?>">
      </label>
      <label class="jinyu-field">
        <i class="fa-solid fa-lock" aria-hidden="true"></i>
        <input type="password" name="pwd" autocomplete="current-password" placeholder="<?php esc_attr_e('密码', JINYU); ?>">
      </label>
      <label class="jinyu-auth-remember">
        <input type="checkbox" name="rememberme" value="1" checked>
        <span><?php esc_html_e('记住我', JINYU); ?></span>
      </label>
      <?php echo jinyu_captcha_markup('login'); ?>
      <button type="submit" class="jinyu-btn jinyu-btn-primary jinyu-auth-submit"><?php esc_html_e('登录', JINYU); ?></button>
      <p class="jinyu-auth-tip" data-jinyu-auth-tip></p>
    </form>

    <!-- 注册 -->
    <form class="jinyu-auth-form" data-jinyu-auth-pane="register" method="post" hidden novalidate>
      <input type="hidden" name="_ajax_nonce" value="<?php echo esc_attr($nonce); ?>">
      <label class="jinyu-field">
        <i class="fa-solid fa-user" aria-hidden="true"></i>
        <input type="text" name="log" autocomplete="username" placeholder="<?php esc_attr_e('用户名（3-30 位）', JINYU); ?>">
      </label>
      <label class="jinyu-field">
        <i class="fa-solid fa-envelope" aria-hidden="true"></i>
        <input type="email" name="email" autocomplete="email" placeholder="<?php esc_attr_e('邮箱', JINYU); ?>">
      </label>
      <label class="jinyu-field">
        <i class="fa-solid fa-lock" aria-hidden="true"></i>
        <input type="password" name="pwd" autocomplete="new-password" placeholder="<?php esc_attr_e('密码（至少 6 位）', JINYU); ?>">
      </label>
      <label class="jinyu-field">
        <i class="fa-solid fa-lock" aria-hidden="true"></i>
        <input type="password" name="pwd2" autocomplete="new-password" placeholder="<?php esc_attr_e('确认密码', JINYU); ?>">
      </label>
      <?php echo jinyu_captcha_markup('register'); ?>
      <button type="submit" class="jinyu-btn jinyu-btn-primary jinyu-auth-submit"><?php esc_html_e('注册', JINYU); ?></button>
      <p class="jinyu-auth-tip" data-jinyu-auth-tip></p>
    </form>

    <!-- 找回密码 -->
    <form class="jinyu-auth-form" data-jinyu-auth-pane="reset" method="post" hidden novalidate>
      <input type="hidden" name="_ajax_nonce" value="<?php echo esc_attr($nonce); ?>">
      <p class="jinyu-auth-hint"><?php esc_html_e('输入用户名或邮箱，重置链接将发送到你的邮箱。', JINYU); ?></p>
      <label class="jinyu-field">
        <i class="fa-solid fa-user" aria-hidden="true"></i>
        <input type="text" name="log" autocomplete="username" placeholder="<?php esc_attr_e('用户名或邮箱', JINYU); ?>">
      </label>
      <?php echo jinyu_captcha_markup('reset'); ?>
      <button type="submit" class="jinyu-btn jinyu-btn-primary jinyu-auth-submit"><?php esc_html_e('发送重置链接', JINYU); ?></button>
      <p class="jinyu-auth-tip" data-jinyu-auth-tip></p>
    </form>

    <?php if (jinyu_oauth_enabled()) : ?>
      <div class="jinyu-auth-divider"><span><?php esc_html_e('第三方登录', JINYU); ?></span></div>
      <?php echo jinyu_oauth_shortcode(); ?>
    <?php endif; ?>
  </div>
</div>
