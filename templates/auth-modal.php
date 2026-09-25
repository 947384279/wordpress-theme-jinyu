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
<div class="jinyu-mask jinyu-auth-mask" hidden role="dialog" aria-modal="true" aria-label="<?php esc_attr_e('登录或注册', 'jinyu'); ?>">
  <div class="jinyu-auth">
    <button type="button" class="jinyu-auth-close" data-jinyu-auth-close aria-label="<?php esc_attr_e('关闭', 'jinyu'); ?>">
      <i class="fa-solid fa-xmark" aria-hidden="true"></i>
    </button>

    <?php
    // 弹窗 Logo 只取图标不取整张站点 Logo（Logo 图含站名文字，与下方标题重复）。
    // 回退链：WP 站点图标 → Web 根目录 /favicon.ico → 火苗图标兜底（img 加载失败自动露出火苗）。
    $auth_icon_url = trim((string) get_site_icon_url(192));
    if ($auth_icon_url === '') {
        $auth_icon_url = esc_url(home_url('/favicon.ico'));
    }
    $auth_logo_alt = esc_attr(get_bloginfo('name'));
    ?>
    <header class="jinyu-auth-head">
      <div class="jinyu-auth-logo has-img">
        <img src="<?php echo esc_url($auth_icon_url); ?>" alt="<?php echo $auth_logo_alt; ?>"
             data-jinyu-fallback-remove data-jinyu-fallback-unset="has-img">
        <i class="fa-solid fa-fire" aria-hidden="true"></i>
      </div>
      <h3><?php bloginfo('name'); ?></h3>
    </header>

    <nav class="jinyu-auth-tabs" data-jinyu-auth-tabs>
      <button type="button" class="is-active" data-jinyu-auth-tab="login"><?php esc_html_e('登录', 'jinyu'); ?></button>
      <button type="button" data-jinyu-auth-tab="register"><?php esc_html_e('注册', 'jinyu'); ?></button>
      <button type="button" data-jinyu-auth-tab="reset"><?php esc_html_e('找回密码', 'jinyu'); ?></button>
    </nav>

    <!-- 登录 -->
    <form class="jinyu-auth-form" data-jinyu-auth-pane="login" method="post" novalidate>
      <input type="hidden" name="_ajax_nonce" value="<?php echo esc_attr($nonce); ?>">
      <label class="jinyu-field">
        <i class="fa-solid fa-user" aria-hidden="true"></i>
        <input type="text" name="log" autocomplete="username" enterkeyhint="next" placeholder="<?php esc_attr_e('用户名或邮箱', 'jinyu'); ?>">
      </label>
      <label class="jinyu-field">
        <i class="fa-solid fa-lock" aria-hidden="true"></i>
        <input type="password" name="pwd" autocomplete="current-password" enterkeyhint="go" placeholder="<?php esc_attr_e('密码', 'jinyu'); ?>">
        <button type="button" class="jinyu-pw-toggle" data-jinyu-pw-toggle aria-label="<?php esc_attr_e('显示密码', 'jinyu'); ?>" aria-pressed="false">
          <i class="fa-solid fa-eye" aria-hidden="true"></i>
        </button>
      </label>
      <div class="jinyu-auth-row">
        <label class="jinyu-auth-remember">
          <input type="checkbox" name="rememberme" value="1" checked>
          <span><?php esc_html_e('记住我', 'jinyu'); ?></span>
        </label>
        <button type="button" class="jinyu-auth-forgot" data-jinyu-auth-goto="reset"><?php esc_html_e('忘记密码？', 'jinyu'); ?></button>
      </div>
      <?php echo function_exists('jinyu_captcha_markup') ? jinyu_captcha_markup('login') : ''; ?>
      <button type="submit" class="jinyu-btn jinyu-btn-primary jinyu-auth-submit"><?php esc_html_e('登录', 'jinyu'); ?></button>
      <p class="jinyu-auth-tip" data-jinyu-auth-tip aria-live="polite"></p>
    </form>

    <!-- 注册 -->
    <form class="jinyu-auth-form" data-jinyu-auth-pane="register" method="post" hidden novalidate>
      <input type="hidden" name="_ajax_nonce" value="<?php echo esc_attr($nonce); ?>">
      <label class="jinyu-field">
        <i class="fa-solid fa-user" aria-hidden="true"></i>
        <input type="text" name="log" autocomplete="username" enterkeyhint="next" placeholder="<?php esc_attr_e('用户名（3-30 位）', 'jinyu'); ?>">
      </label>
      <label class="jinyu-field">
        <i class="fa-solid fa-envelope" aria-hidden="true"></i>
        <input type="email" name="email" autocomplete="email" enterkeyhint="next" placeholder="<?php esc_attr_e('邮箱', 'jinyu'); ?>">
      </label>
      <label class="jinyu-field">
        <i class="fa-solid fa-lock" aria-hidden="true"></i>
        <input type="password" name="pwd" autocomplete="new-password" enterkeyhint="next" placeholder="<?php esc_attr_e('密码（至少 6 位）', 'jinyu'); ?>">
        <button type="button" class="jinyu-pw-toggle" data-jinyu-pw-toggle aria-label="<?php esc_attr_e('显示密码', 'jinyu'); ?>" aria-pressed="false">
          <i class="fa-solid fa-eye" aria-hidden="true"></i>
        </button>
      </label>
      <label class="jinyu-field">
        <i class="fa-solid fa-lock" aria-hidden="true"></i>
        <input type="password" name="pwd2" autocomplete="new-password" enterkeyhint="go" placeholder="<?php esc_attr_e('确认密码', 'jinyu'); ?>">
        <button type="button" class="jinyu-pw-toggle" data-jinyu-pw-toggle aria-label="<?php esc_attr_e('显示密码', 'jinyu'); ?>" aria-pressed="false">
          <i class="fa-solid fa-eye" aria-hidden="true"></i>
        </button>
      </label>
      <?php echo function_exists('jinyu_captcha_markup') ? jinyu_captcha_markup('register') : ''; ?>
      <button type="submit" class="jinyu-btn jinyu-btn-primary jinyu-auth-submit"><?php esc_html_e('注册', 'jinyu'); ?></button>
      <p class="jinyu-auth-tip" data-jinyu-auth-tip aria-live="polite"></p>
    </form>

    <!-- 找回密码 -->
    <form class="jinyu-auth-form" data-jinyu-auth-pane="reset" method="post" hidden novalidate>
      <input type="hidden" name="_ajax_nonce" value="<?php echo esc_attr($nonce); ?>">
      <p class="jinyu-auth-hint"><?php esc_html_e('输入用户名或邮箱，重置链接将发送到你的邮箱。', 'jinyu'); ?></p>
      <label class="jinyu-field">
        <i class="fa-solid fa-user" aria-hidden="true"></i>
        <input type="text" name="log" autocomplete="username" enterkeyhint="next" placeholder="<?php esc_attr_e('用户名或邮箱', 'jinyu'); ?>">
      </label>
      <?php echo function_exists('jinyu_captcha_markup') ? jinyu_captcha_markup('reset') : ''; ?>
      <button type="submit" class="jinyu-btn jinyu-btn-primary jinyu-auth-submit"><?php esc_html_e('发送重置链接', 'jinyu'); ?></button>
      <p class="jinyu-auth-tip" data-jinyu-auth-tip aria-live="polite"></p>
    </form>

    <?php if (function_exists('jinyu_oauth_enabled') && jinyu_oauth_enabled()) : ?>
      <div class="jinyu-auth-divider"><span><?php esc_html_e('快速登录', 'jinyu'); ?></span></div>
      <?php echo function_exists('jinyu_oauth_shortcode') ? jinyu_oauth_shortcode() : ''; ?>
    <?php endif; ?>
  </div>
</div>
