<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
Template Name: 用户中心
*/
get_header();

$uid   = get_current_user_id();
$user  = wp_get_current_user();
$tab   = jinyu_current_user_tab();
$tabs  = jinyu_user_tabs();
$stats = is_user_logged_in() ? jinyu_user_stats($uid) : [];
?>
<div class="jinyu-container jinyu-main-wrap">
  <main id="jinyu-content" class="jinyu-content">
    <?php jinyu_breadcrumbs(); ?>

    <?php if (!is_user_logged_in()) : ?>
      <div class="jinyu-user-gate">
        <i class="fa-solid fa-lock" aria-hidden="true"></i>
        <h1><?php esc_html_e('请先登录', 'jinyu'); ?></h1>
        <p><?php esc_html_e('登录后即可管理你的文章、收藏与资料。', 'jinyu'); ?></p>
        <button type="button" class="jinyu-btn jinyu-btn-primary" data-jinyu-auth-open="login">
          <?php esc_html_e('登录 / 注册', 'jinyu'); ?>
        </button>
      </div>
    <?php else : ?>
      <div class="jinyu-user-center">
        <aside class="jinyu-user-side">
          <div class="jinyu-user-card">
            <div class="jinyu-user-card-ring">
              <img class="jinyu-user-card-avatar"
                   src="<?php
                      $avatar_url = jinyu_user_avatar_url($uid, 120);
                      echo $avatar_url ? esc_url($avatar_url) : esc_attr(jinyu_avatar_default($uid));
                   ?>"
                   data-jinyu-fallback="<?php echo esc_url(jinyu_avatar_default($uid)); ?>"
                   alt="<?php echo esc_attr($user->display_name); ?>">
            </div>
            <h3 class="jinyu-user-card-name"><?php echo esc_html($user->display_name); ?></h3>
            <p class="jinyu-user-card-meta">@<?php echo esc_html($user->user_login); ?></p>
          </div>

          <ul class="jinyu-user-stats">
            <li><b data-stat="posts"><?php echo $stats['posts']; ?></b><span><?php esc_html_e('文章', 'jinyu'); ?></span></li>
            <li><b data-stat="comments"><?php echo $stats['comments']; ?></b><span><?php esc_html_e('评论', 'jinyu'); ?></span></li>
            <li><b data-stat="favs"><?php echo $stats['favs']; ?></b><span><?php esc_html_e('收藏', 'jinyu'); ?></span></li>
            <li><b data-stat="likes"><?php echo $stats['likes']; ?></b><span><?php esc_html_e('获赞', 'jinyu'); ?></span></li>
          </ul>

          <nav class="jinyu-user-nav">
            <?php foreach ($tabs as $slug => $t) :
                $jinyu_notif_extra = 'notifications' === $slug ? ' jinyu-user-notif-link' : ''; ?>
              <a class="jinyu-user-nav-item<?php echo $tab === $slug ? ' is-active' : ''; ?><?php echo esc_attr($jinyu_notif_extra); ?>"
                 href="<?php echo esc_url(add_query_arg('tab', $slug)); ?>">
                <i class="<?php echo esc_attr($t['icon']); ?>" aria-hidden="true"></i>
                <span><?php echo esc_html($t['label']); ?></span>
                <?php if ('notifications' === $slug && function_exists('jinyu_get_unread_count')) :
                    $jinyu_side_unread = (int) jinyu_get_unread_count($uid);
                    if ($jinyu_side_unread > 0) : ?>
                      <span class="jinyu-badge"><?php echo $jinyu_side_unread > 99 ? '99+' : $jinyu_side_unread; ?></span>
                    <?php endif;
                endif; ?>
              </a>
            <?php endforeach; ?>
            <a class="jinyu-user-nav-item jinyu-user-nav-logout" href="<?php echo esc_url(wp_logout_url(home_url())); ?>" data-jinyu-confirm="<?php esc_attr_e('确定退出登录吗？', 'jinyu'); ?>">
              <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
              <span><?php esc_html_e('退出登录', 'jinyu'); ?></span>
            </a>
          </nav>
        </aside>

        <section class="jinyu-user-main" data-jinyu-user-main>
          <?php
          // tab 内容渲染出口与 AJAX 无刷新切换（jinyu_get_tab）共用 pages/template-user-tabs.php
          set_query_var('jinyu_tab', $tab);
          get_template_part('pages/template-user-tabs');
          ?>
        </section>
      </div>
    <?php endif; ?>
  </main>
  <?php if (!is_user_logged_in()) get_sidebar(); ?>
</div>
<?php get_footer(); ?>
