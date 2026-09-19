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
$nonce = wp_create_nonce('jinyu_front');
?>
<div class="jinyu-container jinyu-main-wrap">
  <main id="jinyu-content" class="jinyu-content">
    <?php jinyu_breadcrumbs(); ?>

    <?php if (!is_user_logged_in()) : ?>
      <div class="jinyu-user-gate">
        <i class="fa-solid fa-lock" aria-hidden="true"></i>
        <h2><?php esc_html_e('请先登录', JINYU); ?></h2>
        <p><?php esc_html_e('登录后即可管理你的文章、收藏与资料。', JINYU); ?></p>
        <button type="button" class="jinyu-btn jinyu-btn-primary" data-jinyu-auth-open="login">
          <?php esc_html_e('登录 / 注册', JINYU); ?>
        </button>
      </div>
    <?php else : ?>
      <div class="jinyu-user-center">
        <aside class="jinyu-user-side">
          <div class="jinyu-user-card">
            <img class="jinyu-user-card-avatar"
                 src="<?php
                    $avatar_url = jinyu_user_avatar_url($uid, 120);
                    echo $avatar_url ? esc_url($avatar_url) : esc_attr(jinyu_avatar_default($uid));
                 ?>"
                 onerror="this.onerror=null;this.src='<?php echo esc_attr(jinyu_avatar_default($uid)); ?>';"
                 alt="<?php echo esc_attr($user->display_name); ?>">
            <h3 class="jinyu-user-card-name"><?php echo esc_html($user->display_name); ?></h3>
            <p class="jinyu-user-card-meta">@<?php echo esc_html($user->user_login); ?></p>
          </div>

          <ul class="jinyu-user-stats">
            <li><b><?php echo $stats['posts']; ?></b><span><?php esc_html_e('文章', JINYU); ?></span></li>
            <li><b><?php echo $stats['comments']; ?></b><span><?php esc_html_e('评论', JINYU); ?></span></li>
            <li><b><?php echo $stats['favs']; ?></b><span><?php esc_html_e('收藏', JINYU); ?></span></li>
            <li><b><?php echo $stats['likes']; ?></b><span><?php esc_html_e('获赞', JINYU); ?></span></li>
          </ul>

          <nav class="jinyu-user-nav">
            <?php foreach ($tabs as $slug => $t) : ?>
              <a class="jinyu-user-nav-item<?php echo $tab === $slug ? ' is-active' : ''; ?>"
                 href="<?php echo esc_url(add_query_arg('tab', $slug)); ?>">
                <i class="<?php echo esc_attr($t['icon']); ?>" aria-hidden="true"></i>
                <span><?php echo esc_html($t['label']); ?></span>
              </a>
            <?php endforeach; ?>
            <a class="jinyu-user-nav-item jinyu-user-nav-logout" href="<?php echo esc_url(wp_logout_url(home_url())); ?>">
              <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
              <span><?php esc_html_e('退出登录', JINYU); ?></span>
            </a>
          </nav>
        </aside>

        <section class="jinyu-user-main">
          <?php if ($tab === 'dashboard') : ?>
            <h2 class="jinyu-user-title"><?php esc_html_e('概览', JINYU); ?></h2>
            <div class="jinyu-user-welcome">
              <?php printf(esc_html__('你好，%s，欢迎回来。', JINYU), esc_html($user->display_name)); ?>
            </div>

            <div class="jinyu-user-summary">
              <div class="jinyu-user-summary-row">
                <span class="jinyu-user-summary-label"><?php esc_html_e('邮箱', JINYU); ?></span>
                <span class="jinyu-user-summary-value"><?php echo esc_html($user->user_email); ?></span>
              </div>
              <div class="jinyu-user-summary-row">
                <span class="jinyu-user-summary-label"><?php esc_html_e('身份', JINYU); ?></span>
                <span class="jinyu-user-summary-value"><?php echo esc_html(jinyu_user_role_label($uid)); ?></span>
              </div>
            </div>

            <div class="jinyu-user-actions">
              <a class="jinyu-btn jinyu-btn-primary" href="<?php echo esc_url(jinyu_user_page_url('profile')); ?>">
                <i class="fa-solid fa-user-gear" aria-hidden="true"></i><?php esc_html_e('编辑资料', JINYU); ?>
              </a>
              <?php if (jinyu_user_can_submit()) : ?>
                <a class="jinyu-btn" href="<?php echo esc_url(jinyu_user_page_url('submit')); ?>">
                  <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i><?php esc_html_e('去投稿', JINYU); ?>
                </a>
              <?php endif; ?>
              <?php if (current_user_can('edit_posts')) : ?>
                <a class="jinyu-btn" href="<?php echo esc_url(admin_url('post-new.php')); ?>">
                  <i class="fa-solid fa-plus" aria-hidden="true"></i><?php esc_html_e('写文章', JINYU); ?>
                </a>
              <?php endif; ?>
            </div>

            <h3 class="jinyu-user-subtitle"><?php esc_html_e('最近文章', JINYU); ?></h3>
            <?php
            $recent_posts = jinyu_user_posts($uid, 1, 5);
            if ($recent_posts->have_posts()) :
              echo '<ul class="jinyu-user-post-list">';
              while ($recent_posts->have_posts()) : $recent_posts->the_post(); ?>
                <li class="jinyu-user-post-item">
                  <a class="jinyu-user-post-title" href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                  <span class="jinyu-user-post-status jinyu-status-<?php echo esc_attr(get_post_status()); ?>">
                    <?php echo esc_html(jinyu_post_status_label(get_post_status())); ?>
                  </span>
                  <span class="jinyu-user-post-date"><?php echo esc_html(get_the_date()); ?></span>
                </li>
              <?php endwhile;
              echo '</ul>';
              wp_reset_postdata();
            else : ?>
              <p class="jinyu-empty"><?php esc_html_e('还没有发布文章', JINYU); ?></p>
            <?php endif; ?>

            <h3 class="jinyu-user-subtitle"><?php esc_html_e('最新评论', JINYU); ?></h3>
            <?php $recent = jinyu_user_comments($uid, 5); ?>
            <?php if ($recent) : ?>
              <ul class="jinyu-comment-list jinyu-user-comments">
                <?php foreach ($recent as $c) : ?>
                  <li class="jinyu-comment-item">
                    <p class="jinyu-comment-content"><?php echo wp_kses_post(get_comment_excerpt($c->comment_ID)); ?></p>
                    <p class="jinyu-comment-meta">
                      <?php echo esc_html(get_comment_date('', $c->comment_ID)); ?> ·
                      <a href="<?php echo esc_url(get_comment_link($c->comment_ID)); ?>">
                        <?php echo esc_html(get_the_title($c->comment_post_ID)); ?>
                      </a>
                    </p>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else : ?>
              <p class="jinyu-empty"><?php esc_html_e('还没有评论', JINYU); ?></p>
            <?php endif; ?>

          <?php elseif ($tab === 'posts') : ?>
            <h2 class="jinyu-user-title"><?php esc_html_e('我的文章', JINYU); ?></h2>
            <?php
            $q = jinyu_user_posts($uid, max(1, get_query_var('paged')));
            if ($q->have_posts()) :
              echo '<ul class="jinyu-user-post-list">';
              while ($q->have_posts()) : $q->the_post(); ?>
                <li class="jinyu-user-post-item">
                  <a class="jinyu-user-post-title" href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                  <span class="jinyu-user-post-status jinyu-status-<?php echo esc_attr(get_post_status()); ?>">
                    <?php echo esc_html(jinyu_post_status_label(get_post_status())); ?>
                  </span>
                  <span class="jinyu-user-post-date"><?php echo esc_html(get_the_date()); ?></span>
                  <?php if (get_post_status() !== 'publish') : ?>
                  <button type="button" class="jinyu-btn jinyu-btn-ghost jinyu-post-del"
                          data-post-id="<?php the_ID(); ?>">
                    <?php esc_html_e('撤回', JINYU); ?>
                  </button>
                  <?php endif; ?>
                </li>
              <?php endwhile;
              echo '</ul>';
              wp_reset_postdata();
            else : ?>
              <p class="jinyu-empty"><?php esc_html_e('还没有发布文章', JINYU); ?></p>
            <?php endif; ?>

          <?php elseif ($tab === 'comments') : ?>
            <h2 class="jinyu-user-title"><?php esc_html_e('我的评论', JINYU); ?></h2>
            <?php $cls = jinyu_user_comments($uid, 30); ?>
            <?php if ($cls) : ?>
              <ul class="jinyu-comment-list jinyu-user-comments">
                <?php foreach ($cls as $c) : ?>
                  <li class="jinyu-comment-item">
                    <p class="jinyu-comment-content"><?php echo wp_kses_post(get_comment_excerpt($c->comment_ID)); ?></p>
                    <p class="jinyu-comment-meta">
                      <?php echo esc_html(get_comment_date('', $c->comment_ID)); ?> ·
                      <a href="<?php echo esc_url(get_comment_link($c->comment_ID)); ?>">
                        <?php echo esc_html(get_the_title($c->comment_post_ID)); ?>
                      </a>
                    </p>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else : ?>
              <p class="jinyu-empty"><?php esc_html_e('还没有评论', JINYU); ?></p>
            <?php endif; ?>

          <?php elseif ($tab === 'favs') : ?>
            <h2 class="jinyu-user-title"><?php esc_html_e('我的收藏', JINYU); ?></h2>
            <?php $favs = jinyu_get_user_favs($uid); ?>
            <?php
            $q = $favs ? new WP_Query([
              'post__in'            => $favs,
              'post_type'           => 'post',
              'post_status'         => 'publish',
              'posts_per_page'      => count($favs),
              'ignore_sticky_posts' => true,
            ]) : null;
            ?>
            <?php if ($q && $q->have_posts()) : ?>
              <div class="jinyu-grid jinyu-grid-cols-2">
                <?php while ($q->have_posts()) : $q->the_post();
                  get_template_part('templates/module', 'post');
                endwhile; ?>
              </div>
              <?php wp_reset_postdata(); ?>
            <?php else : ?>
              <p class="jinyu-empty"><?php esc_html_e('还没有收藏任何文章', JINYU); ?></p>
            <?php endif; ?>

          <?php elseif ($tab === 'submit') : ?>
            <h2 class="jinyu-user-title"><?php esc_html_e('投稿', JINYU); ?></h2>
            <?php if (jinyu_user_can_submit()) : ?>
              <form class="jinyu-submit-form" data-jinyu-submit-post method="post">
                <input type="hidden" name="_ajax_nonce" value="<?php echo esc_attr($nonce); ?>">
                <label class="jinyu-field">
                  <input type="text" name="post_title" maxlength="100"
                         placeholder="<?php esc_attr_e('标题（4-100 字）', JINYU); ?>">
                </label>
                <label class="jinyu-field">
                  <?php wp_dropdown_categories([
                    'show_option_none' => __('选择分类', JINYU),
                    'name'             => 'post_category',
                    'orderby'          => 'name',
                    'hierarchical'     => true,
                  ]); ?>
                </label>
                <textarea name="post_content" rows="10"
                          placeholder="<?php esc_attr_e('正文内容（至少 20 字）', JINYU); ?>"></textarea>
                <input type="text" class="jinyu-field" name="post_tags"
                       placeholder="<?php esc_attr_e('标签，逗号分隔', JINYU); ?>">
                <button type="submit" class="jinyu-btn jinyu-btn-primary"><?php esc_html_e('提交投稿', JINYU); ?></button>
                <p class="jinyu-auth-tip" data-jinyu-submit-tip></p>
              </form>
            <?php else : ?>
              <p class="jinyu-empty"><?php esc_html_e('当前未开放投稿', JINYU); ?></p>
            <?php endif; ?>

          <?php elseif ($tab === 'follow') : ?>
            <h2 class="jinyu-user-title"><?php esc_html_e('我的关注', JINYU); ?></h2>
            <?php
            $jinyu_follow_users = jinyu_get_following_users($uid);
            $jinyu_follow_terms = jinyu_get_following_terms($uid);
            ?>
            <h3 class="jinyu-user-subtitle"><?php esc_html_e('关注的用户', JINYU); ?></h3>
            <?php if ($jinyu_follow_users) : ?>
              <ul class="jinyu-follow-list">
                <?php foreach ($jinyu_follow_users as $fu) :
                  $fu_id = (int)$fu->ID; ?>
                  <li class="jinyu-follow-item">
                    <a class="jinyu-follow-avatar" href="<?php echo esc_url(get_author_posts_url($fu_id)); ?>">
                      <img src="<?php
                        $fu_avatar = jinyu_user_avatar_url($fu_id, 64);
                        echo $fu_avatar ? esc_url($fu_avatar) : esc_attr(jinyu_avatar_default($fu_id));
                      ?>" onerror="this.onerror=null;this.src='<?php echo esc_attr(jinyu_avatar_default($fu_id)); ?>';" alt="">
                    </a>
                    <a class="jinyu-follow-name" href="<?php echo esc_url(get_author_posts_url($fu_id)); ?>"><?php echo esc_html($fu->display_name); ?></a>
                    <button type="button" class="jinyu-btn jinyu-btn-ghost jinyu-follow-toggle is-following"
                            data-jinyu-follow data-target="user" data-id="<?php echo $fu_id; ?>">
                      <?php esc_html_e('取消关注', JINYU); ?>
                    </button>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else : ?>
              <p class="jinyu-empty"><?php esc_html_e('还没有关注任何用户', JINYU); ?></p>
            <?php endif; ?>

            <h3 class="jinyu-user-subtitle"><?php esc_html_e('收藏的系列', JINYU); ?></h3>
            <?php if ($jinyu_follow_terms) : ?>
              <ul class="jinyu-follow-list jinyu-follow-terms">
                <?php foreach ($jinyu_follow_terms as $ft) :
                  $ft_id = (int)$ft->term_id; ?>
                  <li class="jinyu-follow-item">
                    <a class="jinyu-follow-name" href="<?php echo esc_url(get_term_link($ft)); ?>">
                      <i class="fa-solid fa-layer-group" aria-hidden="true"></i><?php echo esc_html($ft->name); ?>
                    </a>
                    <button type="button" class="jinyu-btn jinyu-btn-ghost jinyu-follow-toggle is-following"
                            data-jinyu-follow data-target="term" data-id="<?php echo $ft_id; ?>">
                      <?php esc_html_e('取消收藏', JINYU); ?>
                    </button>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else : ?>
              <p class="jinyu-empty"><?php esc_html_e('还没有收藏任何系列', JINYU); ?></p>
            <?php endif; ?>

          <?php elseif ($tab === 'notifications') : ?>
            <h2 class="jinyu-user-title">
              <?php esc_html_e('消息', JINYU); ?>
              <button type="button" class="jinyu-btn jinyu-btn-ghost jinyu-notif-readall" data-jinyu-notif-readall>
                <?php esc_html_e('全部已读', JINYU); ?>
              </button>
            </h2>
            <ul class="jinyu-notif-list" data-jinyu-notif-list>
              <li class="jinyu-empty"><?php esc_html_e('加载中…', JINYU); ?></li>
            </ul>

          <?php elseif ($tab === 'profile') : ?>
            <h2 class="jinyu-user-title"><?php esc_html_e('资料设置', JINYU); ?></h2>
            <form class="jinyu-profile-form" data-jinyu-profile method="post">
              <input type="hidden" name="_ajax_nonce" value="<?php echo esc_attr($nonce); ?>">
              <div class="jinyu-avatar-edit">
                <img class="jinyu-avatar-preview"
                     src="<?php
                        $avatar_url = jinyu_user_avatar_url($uid, 96);
                        echo $avatar_url ? esc_url($avatar_url) : esc_attr(jinyu_avatar_default($uid));
                     ?>"
                     onerror="this.onerror=null;this.src='<?php echo esc_attr(jinyu_avatar_default($uid)); ?>';"
                     alt="avatar">
                <div class="jinyu-avatar-side">
                  <label class="jinyu-btn jinyu-btn-ghost jinyu-avatar-pick">
                    <?php esc_html_e('上传头像', JINYU); ?>
                    <input type="file" name="avatar" accept="image/*" data-jinyu-avatar hidden>
                  </label>
                  <p class="jinyu-field-hint"><?php esc_html_e('支持 JPG / PNG / WebP / GIF，≤2MB，或在下栏填写图片 URL', JINYU); ?></p>
                </div>
              </div>
              <label class="jinyu-field jinyu-avatar-url">
                <input type="text" name="avatar_url" placeholder="<?php esc_attr_e('或填写头像图片 URL', JINYU); ?>">
              </label>
              <label class="jinyu-field">
                <input type="text" name="display_name" value="<?php echo esc_attr($user->display_name); ?>"
                       placeholder="<?php esc_attr_e('昵称', JINYU); ?>">
              </label>
              <label class="jinyu-field">
                <span class="jinyu-field-label"><?php esc_html_e('邮箱', JINYU); ?></span>
                <input type="email" name="user_email" value="<?php echo esc_attr($user->user_email); ?>" autocomplete="email">
              </label>
              <p class="jinyu-field-hint"><?php esc_html_e('修改邮箱需验证：提交后系统会向新邮箱发送确认链接，点击后方可生效。', JINYU); ?></p>
              <label class="jinyu-field">
                <input type="url" name="user_url" value="<?php echo esc_attr($user->user_url); ?>"
                       placeholder="<?php esc_attr_e('个人网站', JINYU); ?>">
              </label>
              <textarea name="description" rows="3"
                        placeholder="<?php esc_attr_e('个人简介', JINYU); ?>"><?php echo esc_textarea($user->description); ?></textarea>
              <button type="submit" class="jinyu-btn jinyu-btn-primary"><?php esc_html_e('保存资料', JINYU); ?></button>
              <p class="jinyu-auth-tip" data-jinyu-profile-tip></p>
            </form>

            <h3 class="jinyu-user-subtitle"><?php esc_html_e('修改密码', JINYU); ?></h3>
            <form class="jinyu-profile-form" data-jinyu-password method="post">
              <input type="hidden" name="_ajax_nonce" value="<?php echo esc_attr($nonce); ?>">
              <label class="jinyu-field">
                <input type="password" name="old_pwd" autocomplete="current-password"
                       placeholder="<?php esc_attr_e('当前密码', JINYU); ?>">
              </label>
              <label class="jinyu-field">
                <input type="password" name="new_pwd" autocomplete="new-password"
                       placeholder="<?php esc_attr_e('新密码（至少 6 位）', JINYU); ?>">
              </label>
              <label class="jinyu-field">
                <input type="password" name="new_pwd2" autocomplete="new-password"
                       placeholder="<?php esc_attr_e('确认新密码', JINYU); ?>">
              </label>
              <button type="submit" class="jinyu-btn jinyu-btn-primary"><?php esc_html_e('更新密码', JINYU); ?></button>
              <p class="jinyu-auth-tip" data-jinyu-password-tip></p>
            </form>

            <?php $bindings = jinyu_oauth_bindings($uid); ?>
            <?php if (jinyu_oauth_enabled() && array_filter($bindings)) : ?>
              <h3 class="jinyu-user-subtitle"><?php esc_html_e('已绑定账号', JINYU); ?></h3>
              <ul class="jinyu-bind-list">
                <?php foreach (jinyu_oauth_platforms() as $p => $info) : ?>
                  <?php if (!empty($bindings[$p])) : ?>
                    <li><i class="<?php echo esc_attr($info['icon']); ?>" aria-hidden="true"></i>
                      <?php echo esc_html($info['label']); ?>
                      <span class="jinyu-bind-on"><?php esc_html_e('已绑定', JINYU); ?></span></li>
                  <?php endif; ?>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          <?php endif; ?>
        </section>
      </div>
    <?php endif; ?>
  </main>
  <?php if (!is_user_logged_in()) get_sidebar(); ?>
</div>
<?php get_footer(); ?>
