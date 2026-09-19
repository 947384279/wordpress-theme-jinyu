<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 站点头部
 *
 * @package WordPress
 * @subpackage Jinyu
 */
?><!doctype html>
<html <?php language_attributes(); ?><?php jinyu_html_attrs(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#f5f6f8" media="(prefers-color-scheme: light)">
  <meta name="theme-color" content="#16181c" media="(prefers-color-scheme: dark)">
  <link rel="preconnect" href="https://cdn.qicaiyun.top">
  <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<?php get_template_part('ad/global-top'); ?>

<a href="#jinyu-content" class="jinyu-skip-link"><?php esc_html_e('跳到主内容', JINYU); ?></a>

<div class="jinyu-skeleton" id="jinyu-skeleton" aria-hidden="true">
    <div class="jinyu-skeleton-bar is-title"></div>
    <div class="jinyu-skeleton-bar is-wide"></div>
    <div class="jinyu-skeleton-bar is-mid"></div>
    <div class="jinyu-skeleton-bar is-card"></div>
    <div class="jinyu-skeleton-bar is-wide"></div>
    <div class="jinyu-skeleton-bar is-mid"></div>
</div>
<noscript><style>.jinyu-skeleton{display:none!important}</style></noscript>

<?php if (is_singular('post')) : ?>
<div class="jinyu-read-bar" aria-hidden="true"><div class="jinyu-read-inner"></div></div>
<?php endif; ?>

<header class="jinyu-header">
  <div class="jinyu-container jinyu-header-inner">

    <button type="button" class="jinyu-nav-toggle" data-jinyu-nav-toggle
            data-label-open="<?php esc_attr_e('展开菜单', JINYU); ?>"
            data-label-close="<?php esc_attr_e('关闭菜单', JINYU); ?>"
            aria-label="<?php esc_attr_e('展开菜单', JINYU); ?>" aria-expanded="false" aria-controls="jinyu-primary-nav">
      <span class="jinyu-burger" aria-hidden="true"><span></span><span></span><span></span></span>
    </button>

    <?php
    // 站点品牌名：优先取主题「站点标题」，留空则回退 WordPress 站点标题
    $brand_name = trim((string) jinyu_get_option('web_title', ''));
    if ($brand_name === '') {
      $brand_name = (string) get_bloginfo('name');
    }
    $logo_light = trim((string) jinyu_get_option('web_logo', ''));
    $logo_dark  = trim((string) jinyu_get_option('web_logo_dark', ''));
    $custom_logo_id = (int) get_theme_mod('custom_logo', 0);
    $brand_alt  = esc_attr($brand_name);
    // Logo 图本身已含站名，此时不再叠加文字站名（否则站名重复显示）
    $has_logo_img = ($logo_light !== '' || $custom_logo_id > 0);
    // 仅当亮/暗两张 Logo 都配置时才启用双 Logo 切换
    $logo_dual = ($logo_light !== '' && $logo_dark !== '');
    ?>
    <a href="<?php echo esc_url(home_url('/')); ?>" class="jinyu-logo<?php echo $logo_dual ? ' jinyu-logo--dual' : ''; ?>" rel="home">
      <?php if ($logo_light !== '') : ?>
        <img class="jinyu-logo-img jinyu-logo-light" src="<?php echo esc_url(jinyu_img_to_webp_url($logo_light)); ?>" alt="<?php echo $brand_alt; ?>">
        <?php if ($logo_dual) : ?>
          <img class="jinyu-logo-img jinyu-logo-dark" src="<?php echo esc_url(jinyu_img_to_webp_url($logo_dark)); ?>" alt="<?php echo $brand_alt; ?>">
        <?php endif; ?>
      <?php elseif ($custom_logo_id > 0) : ?>
        <?php echo wp_get_attachment_image($custom_logo_id, 'full', false, ['class' => 'jinyu-logo-img', 'alt' => $brand_name]); ?>
      <?php else : ?>
        <span class="jinyu-logo-icon"><i class="fa-solid fa-fire" aria-hidden="true"></i></span>
      <?php endif; ?>
      <?php if (!$has_logo_img) : ?>
      <span class="jinyu-logo-text-wrap">
        <span class="jinyu-logo-text"><?php echo esc_html($brand_name); ?></span>
        <?php if (get_bloginfo('description')) : ?>
          <span class="jinyu-logo-sub"><?php bloginfo('description'); ?></span>
        <?php endif; ?>
      </span>
      <?php endif; ?>
    </a>

    <?php
    /* 导航面板：桌面为头部内联菜单；≤1240 变为紧贴头部下沿的全屏浮层（见 common.less）。
       面板必须留在 header 内以复用同一份菜单 DOM，因此 ≤1240 断点移除了 .jinyu-header 的
       backdrop-filter —— 否则它成为固定定位的包含块，浮层会被压缩成头部高度。
       工具区（搜索/主题/登录）是 header 的独立子元素，≤1240 常驻标题栏右侧，不进浮层。 */
    ?>
    <div class="jinyu-nav-panel" data-jinyu-nav-panel>
      <?php
      wp_nav_menu([
          'theme_location' => 'primary',
          'menu_id'        => 'jinyu-primary-nav',
          'menu_class'     => 'jinyu-nav',
          'container'      => false,
          'fallback_cb'    => 'jinyu_default_nav',
      ]);
      ?>
    </div><!-- /.jinyu-nav-panel -->

    <?php
    /* 工具区（搜索 / 主题 / 登录）：桌面在标题栏右侧与菜单并排；
       ≤1240 不再塞进浮层底部，而是常驻标题栏右侧（用户要求移动端工具随手可及）。
       浮层 .jinyu-nav-panel 此时只承载菜单 DOM，结构更清晰。 */
    ?>
    <div class="jinyu-header-tools">
      <?php if (jinyu_is_checked('header_social_enable')) : ?>
      <div class="jinyu-header-social">
        <?php foreach (jinyu_header_social() as $s) : ?>
          <a href="<?php echo esc_url($s['url']); ?>" target="_blank" rel="noopener nofollow"
             title="<?php echo esc_attr($s['title']); ?>" aria-label="<?php echo esc_attr($s['title']); ?>">
            <i class="<?php echo esc_attr($s['icon']); ?>" aria-hidden="true"></i>
          </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <button type="button" class="jinyu-icon-btn" data-jinyu-search-toggle
              aria-label="<?php esc_attr_e('搜索', JINYU); ?>">
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
      </button>

      <button type="button" class="jinyu-icon-btn" data-jinyu-theme-toggle
              aria-label="<?php esc_attr_e('切换明暗模式', JINYU); ?>" aria-pressed="false">
        <i class="fa-solid fa-moon" aria-hidden="true"></i>
      </button>

      <?php if (is_user_logged_in()) : ?>
        <div class="jinyu-user-menu" data-jinyu-user-menu>
          <button type="button" class="jinyu-user-btn" data-jinyu-user-toggle
                  title="<?php esc_attr_e('用户菜单', JINYU); ?>"
                  aria-expanded="false" aria-label="<?php echo esc_attr(wp_get_current_user()->display_name); ?>">
            <img class="jinyu-user-avatar"
                 src="<?php
                    $uid = get_current_user_id();
                    $avatar_url = jinyu_user_avatar_url($uid, 64);
                    echo $avatar_url ? esc_url($avatar_url) : esc_attr(jinyu_avatar_default($uid));
                 ?>"
                 onerror="this.onerror=null;this.src='<?php echo esc_attr(jinyu_avatar_default(get_current_user_id())); ?>';"
                 alt="">
          </button>
          <div class="jinyu-user-drop" data-jinyu-user-drop hidden>
            <?php if (jinyu_is_checked('user_center_enable')) : ?>
              <a href="<?php echo esc_url(jinyu_user_page_url()); ?>"><i class="fa-solid fa-gauge-high" aria-hidden="true"></i><?php esc_html_e('用户中心', JINYU); ?></a>
            <?php endif; ?>
            <?php if (jinyu_is_checked('user_can_submit')) : ?>
              <a href="<?php echo esc_url(jinyu_user_page_url('submit')); ?>"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i><?php esc_html_e('投稿', JINYU); ?></a>
            <?php endif; ?>
            <a href="<?php echo esc_url(jinyu_user_page_url('profile')); ?>"><i class="fa-solid fa-user-gear" aria-hidden="true"></i><?php esc_html_e('账号设置', JINYU); ?></a>
            <a href="<?php echo esc_url(jinyu_user_page_url('notifications')); ?>" class="jinyu-user-notif-link">
              <i class="fa-solid fa-bell" aria-hidden="true"></i><?php esc_html_e('消息中心', JINYU); ?>
              <?php $jinyu_unread = jinyu_get_unread_count(get_current_user_id()); ?>
              <?php if ($jinyu_unread > 0) : ?><span class="jinyu-badge"><?php echo $jinyu_unread > 99 ? '99+' : (int)$jinyu_unread; ?></span><?php endif; ?>
            </a>
            <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i><?php esc_html_e('退出登录', JINYU); ?></a>
          </div>
        </div>
      <?php elseif (jinyu_is_checked('user_center_enable')) : ?>
        <button type="button" class="jinyu-login-btn" data-jinyu-auth-open="login"><?php esc_html_e('登录', JINYU); ?></button>
      <?php endif; ?>
    </div><!-- /.jinyu-header-tools -->

  </div>
</header>

<?php $notice = trim((string)jinyu_get_option('top_notice', '')); ?>
<?php if ($notice) : ?>
  <div class="jinyu-top-notice">
    <div class="jinyu-container">
      <div class="jinyu-top-notice-card">
        <i class="fa-solid fa-bullhorn" aria-hidden="true"></i>
        <span><?php echo esc_html($notice); ?></span>
      </div>
    </div>
  </div>
<?php endif; ?>

<div class="jinyu-mask jinyu-search-mask" hidden role="dialog" aria-modal="true" aria-label="<?php esc_attr_e('站内搜索', JINYU); ?>">
  <div class="jinyu-search-panel">
    <?php get_search_form(); ?>
    <p class="jinyu-search-tip"><?php esc_html_e('按 Esc 关闭', JINYU); ?></p>
  </div>
</div>

