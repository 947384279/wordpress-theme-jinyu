<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * 用户中心 tab 内容渲染出口。
 * 两处共用：pages/template-user.php 模板主体、AJAX jinyu_get_tab 无刷新切换。
 * 自足计算依赖变量，不依赖外部作用域；调用方通过 query_var jinyu_tab 显式指定页签。
 *
 * 信息架构（5 tab）：
 * - 概览 / 我的文章（?compose=1 切撰写态） / 互动（&sub=comments|favs|follow 子页签） / 消息 / 资料设置
 * - 旧 tab（comments/favs/follow/submit）由 inc/fun/user.php 的 template_redirect 301 到新地址
 */
$jinyu_tab = get_query_var('jinyu_tab');
$tab   = is_string( $jinyu_tab ) && '' !== $jinyu_tab && array_key_exists( $jinyu_tab, jinyu_user_tabs() )
	? $jinyu_tab
	: jinyu_current_user_tab();
$uid   = get_current_user_id();
$user  = wp_get_current_user();
$nonce = wp_create_nonce( 'jinyu_front' );
?>
<?php if ( $tab === 'dashboard' ) : ?>
  <h1 class="jinyu-user-title"><?php esc_html_e( '概览', 'jinyu' ); ?></h1>
  <?php
  // 时段问候：早 / 午 / 晚 / 深夜，比固定「欢迎回来」更有温度
  $jinyu_hour  = (int) current_time( 'G' );
  $jinyu_greet = $jinyu_hour < 6
    ? __( '夜深了', 'jinyu' )
    : ( $jinyu_hour < 12
      ? __( '早上好', 'jinyu' )
      : ( $jinyu_hour < 18 ? __( '下午好', 'jinyu' ) : __( '晚上好', 'jinyu' ) ) );
  $jinyu_unread_dash = function_exists( 'jinyu_get_unread_count' ) ? (int) jinyu_get_unread_count( $uid ) : 0;
  ?>
  <div class="jinyu-user-welcome">
    <div>
      <h3 class="jinyu-user-hello"><?php printf( esc_html__( '%1$s，%2$s', 'jinyu' ), esc_html( $jinyu_greet ), esc_html( $user->display_name ) ); ?></h3>
      <p class="jinyu-user-hello-sub">
        <?php
        if ( $jinyu_unread_dash > 0 ) {
          printf( esc_html__( '你有 %1$s 条未读消息，点击侧栏「消息」查看。', 'jinyu' ), number_format_i18n( $jinyu_unread_dash ) );
        } else {
          esc_html_e( '欢迎回来，继续你的创作之旅。', 'jinyu' );
        }
        ?>
      </p>
    </div>
    <span class="jinyu-user-hello-pill"><?php echo esc_html( jinyu_user_role_label( $uid ) ); ?></span>
  </div>

  <?php $dash_stats = function_exists( 'jinyu_user_stats' ) ? jinyu_user_stats( $uid ) : []; ?>
  <div class="jinyu-user-cards">
    <a class="jinyu-user-card-stat" href="<?php echo esc_url( jinyu_user_page_url( 'posts' ) ); ?>">
      <b><?php echo (int) ( $dash_stats['posts'] ?? 0 ); ?></b><span><?php esc_html_e( '文章', 'jinyu' ); ?></span>
    </a>
    <a class="jinyu-user-card-stat" href="<?php echo esc_url( add_query_arg( [ 'tab' => 'interact', 'sub' => 'comments' ], jinyu_user_page_url() ) ); ?>">
      <b><?php echo (int) ( $dash_stats['comments'] ?? 0 ); ?></b><span><?php esc_html_e( '评论', 'jinyu' ); ?></span>
    </a>
    <a class="jinyu-user-card-stat" href="<?php echo esc_url( add_query_arg( [ 'tab' => 'interact', 'sub' => 'favs' ], jinyu_user_page_url() ) ); ?>">
      <b><?php echo (int) ( $dash_stats['favs'] ?? 0 ); ?></b><span><?php esc_html_e( '收藏', 'jinyu' ); ?></span>
    </a>
    <div class="jinyu-user-card-stat">
      <b><?php echo (int) ( $dash_stats['likes'] ?? 0 ); ?></b><span><?php esc_html_e( '获赞', 'jinyu' ); ?></span>
    </div>
  </div>

  <div class="jinyu-user-actions">
    <a class="jinyu-btn jinyu-btn-primary" href="<?php echo esc_url( jinyu_user_page_url( 'profile' ) ); ?>">
      <i class="fa-solid fa-user-gear" aria-hidden="true"></i><?php esc_html_e( '编辑资料', 'jinyu' ); ?>
    </a>
    <?php if ( jinyu_user_can_submit() ) : ?>
      <a class="jinyu-btn" href="<?php echo esc_url( add_query_arg( [ 'tab' => 'posts', 'compose' => '1' ], jinyu_user_page_url() ) ); ?>">
        <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i><?php esc_html_e( '去投稿', 'jinyu' ); ?>
      </a>
    <?php endif; ?>
    <?php if ( current_user_can( 'edit_posts' ) ) : ?>
      <a class="jinyu-btn" href="<?php echo esc_url( admin_url( 'post-new.php' ) ); ?>">
        <i class="fa-solid fa-plus" aria-hidden="true"></i><?php esc_html_e( '写文章', 'jinyu' ); ?>
      </a>
    <?php endif; ?>
  </div>

  <h3 class="jinyu-user-subtitle"><?php esc_html_e( '最近文章', 'jinyu' ); ?></h3>
  <?php
  $recent_posts = jinyu_user_posts( $uid, 1, 5 );
  if ( $recent_posts->have_posts() ) :
    echo '<ul class="jinyu-user-post-list">';
    while ( $recent_posts->have_posts() ) :
      $recent_posts->the_post();
      jinyu_user_post_row();
    endwhile;
    echo '</ul>';
    wp_reset_postdata();
  else :
    if ( jinyu_user_can_submit() ) {
      jinyu_user_empty( 'fa-regular fa-file-lines', __( '还没有发布文章，去写下第一篇吧', 'jinyu' ), __( '去投稿', 'jinyu' ), add_query_arg( [ 'tab' => 'posts', 'compose' => '1' ], jinyu_user_page_url() ) );
    } elseif ( current_user_can( 'edit_posts' ) ) {
      jinyu_user_empty( 'fa-regular fa-file-lines', __( '还没有发布文章，去写下第一篇吧', 'jinyu' ), __( '写文章', 'jinyu' ), admin_url( 'post-new.php' ) );
    } else {
      echo '<p class="jinyu-empty">' . esc_html__( '还没有发布文章', 'jinyu' ) . '</p>';
    }
  endif;
  ?>

  <h3 class="jinyu-user-subtitle"><?php esc_html_e( '最新评论', 'jinyu' ); ?></h3>
  <?php $recent = jinyu_user_comments( $uid, 5 ); ?>
  <?php if ( $recent ) : ?>
    <ul class="jinyu-comment-list jinyu-user-comments">
      <?php foreach ( $recent as $c ) : ?>
        <li class="jinyu-comment-item">
          <p class="jinyu-comment-content"><?php echo wp_kses_post( get_comment_excerpt( $c->comment_ID ) ); ?></p>
          <p class="jinyu-comment-meta">
            <?php echo esc_html( get_comment_date( '', $c->comment_ID ) ); ?> ·
            <a href="<?php echo esc_url( get_comment_link( $c->comment_ID ) ); ?>">
              <?php echo esc_html( get_the_title( $c->comment_post_ID ) ); ?>
            </a>
          </p>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php else : ?>
    <p class="jinyu-empty"><?php esc_html_e( '还没有评论', 'jinyu' ); ?></p>
  <?php endif; ?>

<?php elseif ( $tab === 'posts' ) : ?>
  <?php $jinyu_compose = isset( $_GET['compose'] ) && jinyu_user_can_submit(); ?>
  <div class="jinyu-user-title-row">
    <?php if ( $jinyu_compose ) : ?>
      <h1 class="jinyu-user-title"><?php esc_html_e( '投稿', 'jinyu' ); ?></h1>
      <a class="jinyu-btn jinyu-btn-ghost" href="<?php echo esc_url( jinyu_user_page_url( 'posts' ) ); ?>">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i><?php esc_html_e( '返回列表', 'jinyu' ); ?>
      </a>
    <?php else : ?>
      <h1 class="jinyu-user-title"><?php esc_html_e( '我的文章', 'jinyu' ); ?></h1>
      <?php if ( jinyu_user_can_submit() ) : ?>
        <a class="jinyu-btn jinyu-btn-primary" href="<?php echo esc_url( add_query_arg( [ 'tab' => 'posts', 'compose' => '1' ], jinyu_user_page_url() ) ); ?>">
          <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i><?php esc_html_e( '投稿', 'jinyu' ); ?>
        </a>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <?php if ( $jinyu_compose ) : ?>
    <form class="jinyu-submit-form" data-jinyu-submit-post method="post" enctype="multipart/form-data">
      <input type="hidden" name="_ajax_nonce" value="<?php echo esc_attr( $nonce ); ?>">
      <label class="jinyu-field">
        <input type="text" name="post_title" maxlength="100"
               placeholder="<?php esc_attr_e( '标题（4-100 字）', 'jinyu' ); ?>">
      </label>
      <span class="jinyu-char-count" data-jinyu-count="post_title" data-min="4" data-max="100" aria-live="polite"></span>
      <label class="jinyu-field">
        <?php
        wp_dropdown_categories(
          [
            'show_option_none' => __( '选择分类', 'jinyu' ),
            'name'             => 'post_category',
            'orderby'          => 'name',
            'hierarchical'     => true,
          ]
        );
        ?>
      </label>
      <div class="jinyu-cover-edit">
        <div class="jinyu-cover-drop jinyu-cover-pick" data-jinyu-cover-drop>
          <input type="file" name="post_cover" accept="image/jpeg,image/png,image/webp,image/gif" hidden>
          <div class="jinyu-cover-empty" data-jinyu-cover-empty>
            <span class="jinyu-cover-ico" aria-hidden="true"><i class="fa-regular fa-image"></i></span>
            <span class="jinyu-cover-txt"><?php esc_html_e( '拖拽图片到此处，或', 'jinyu' ); ?> <em><?php esc_html_e( '点击上传', 'jinyu' ); ?></em></span>
            <span class="jinyu-cover-sub"><?php esc_html_e( '可选 · JPG / PNG / WebP / GIF · ≤5MB', 'jinyu' ); ?></span>
            <span class="jinyu-cover-sub"><?php esc_html_e( '建议横图 1200×675，上传后自动压缩', 'jinyu' ); ?></span>
          </div>
          <img class="jinyu-cover-preview" hidden alt="">
          <div class="jinyu-cover-mask" hidden>
            <button type="button" class="jinyu-cover-repick"><?php esc_html_e( '更换', 'jinyu' ); ?></button>
            <button type="button" class="jinyu-cover-clear"><?php esc_html_e( '移除', 'jinyu' ); ?></button>
          </div>
        </div>
      </div>
      <textarea name="post_content" rows="10"
                placeholder="<?php esc_attr_e( '正文内容（至少 20 字）', 'jinyu' ); ?>"></textarea>
      <span class="jinyu-char-count" data-jinyu-count="post_content" data-min="20" aria-live="polite"></span>
      <input type="text" class="jinyu-field" name="post_tags"
             placeholder="<?php esc_attr_e( '标签，逗号分隔', 'jinyu' ); ?>">
      <button type="submit" class="jinyu-btn jinyu-btn-primary"><?php esc_html_e( '提交投稿', 'jinyu' ); ?></button>
      <p class="jinyu-auth-tip" data-jinyu-submit-tip></p>
    </form>
  <?php else : ?>
    <?php
    $q = jinyu_user_posts( $uid, jinyu_user_paged() );
    if ( $q->have_posts() ) :
      echo '<ul class="jinyu-user-post-list">';
      while ( $q->have_posts() ) :
        $q->the_post();
        jinyu_user_post_row();
      endwhile;
      echo '</ul>';
      wp_reset_postdata();
      jinyu_user_pagination( (int) $q->max_num_pages );
    else :
      if ( jinyu_user_can_submit() ) {
        jinyu_user_empty( 'fa-regular fa-file-lines', __( '还没有发布文章，去写下第一篇吧', 'jinyu' ), __( '去投稿', 'jinyu' ), add_query_arg( [ 'tab' => 'posts', 'compose' => '1' ], jinyu_user_page_url() ) );
      } elseif ( current_user_can( 'edit_posts' ) ) {
        jinyu_user_empty( 'fa-regular fa-file-lines', __( '还没有发布文章，去写下第一篇吧', 'jinyu' ), __( '写文章', 'jinyu' ), admin_url( 'post-new.php' ) );
      } else {
        echo '<p class="jinyu-empty">' . esc_html__( '还没有发布文章', 'jinyu' ) . '</p>';
      }
    endif;
    ?>
  <?php endif; ?>

<?php elseif ( $tab === 'interact' ) : ?>
  <?php
  $jinyu_sub = jinyu_current_interact_sub();
  ?>
  <h1 class="jinyu-user-title"><?php esc_html_e( '互动', 'jinyu' ); ?></h1>
  <nav class="jinyu-user-subnav">
    <?php foreach ( jinyu_user_interact_tabs() as $jinyu_its => $jinyu_itab ) : ?>
      <a class="jinyu-user-sub-item<?php echo $jinyu_sub === $jinyu_its ? ' is-active' : ''; ?>"
         href="<?php echo esc_url( add_query_arg( [ 'tab' => 'interact', 'sub' => $jinyu_its, 'paged' => false ], jinyu_user_page_url() ) ); ?>">
        <i class="<?php echo esc_attr( $jinyu_itab['icon'] ); ?>" aria-hidden="true"></i><span><?php echo esc_html( $jinyu_itab['label'] ); ?></span>
      </a>
    <?php endforeach; ?>
  </nav>

  <?php if ( 'comments' === $jinyu_sub ) : ?>
    <?php
    $jinyu_cmt_per  = 10;
    $jinyu_cmt_page = jinyu_user_paged();
    $cls            = jinyu_user_comments( $uid, $jinyu_cmt_per, $jinyu_cmt_page );
    if ( $cls ) :
      ?>
      <ul class="jinyu-comment-list jinyu-user-comments">
        <?php foreach ( $cls as $c ) : ?>
          <li class="jinyu-comment-item">
            <p class="jinyu-comment-content"><?php echo wp_kses_post( get_comment_excerpt( $c->comment_ID ) ); ?></p>
            <p class="jinyu-comment-meta">
              <?php echo esc_html( get_comment_date( '', $c->comment_ID ) ); ?> ·
              <a href="<?php echo esc_url( get_comment_link( $c->comment_ID ) ); ?>">
                <?php echo esc_html( get_the_title( $c->comment_post_ID ) ); ?>
              </a>
            </p>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php jinyu_user_pagination( (int) ceil( jinyu_user_comments_count( $uid ) / $jinyu_cmt_per ) ); ?>
    <?php else :
      jinyu_user_empty( 'fa-regular fa-comments', __( '还没有评论，去文章下聊聊吧', 'jinyu' ), __( '去首页逛逛', 'jinyu' ), home_url( '/' ) );
    endif; ?>

  <?php elseif ( 'favs' === $jinyu_sub ) : ?>
    <?php
    $favs = jinyu_get_user_favs( $uid );
    $q    = $favs ? new WP_Query(
      [
        'post__in'            => $favs,
        // 按收藏先后排序（post__in 即用户收藏顺序），而非文章日期
        'orderby'             => 'post__in',
        'post_type'           => 'post',
        'post_status'         => 'publish',
        'posts_per_page'      => 20,
        'paged'               => jinyu_user_paged(),
        'ignore_sticky_posts' => true,
      ]
    ) : null;
    ?>
    <?php if ( $q && $q->have_posts() ) : ?>
      <div class="jinyu-grid jinyu-grid-cols-2" data-jinyu-fav-list>
        <?php while ( $q->have_posts() ) :
          $q->the_post();
          jinyu_fav_cell();
        endwhile; ?>
      </div>
      <?php
      wp_reset_postdata();
      jinyu_user_pagination( (int) $q->max_num_pages );
    else :
      jinyu_user_empty( 'fa-regular fa-bookmark', __( '还没有收藏任何文章', 'jinyu' ), __( '去首页逛逛', 'jinyu' ), home_url( '/' ) );
    endif; ?>

  <?php else : ?>
    <?php
    $jinyu_follow_users = function_exists( 'jinyu_get_following_users' ) ? jinyu_get_following_users( $uid ) : [];
    $jinyu_follow_terms = function_exists( 'jinyu_get_following_terms' ) ? jinyu_get_following_terms( $uid ) : [];
    ?>
    <h3 class="jinyu-user-subtitle"><?php esc_html_e( '关注的用户', 'jinyu' ); ?></h3>
    <?php if ( $jinyu_follow_users ) : ?>
      <ul class="jinyu-follow-list">
        <?php foreach ( $jinyu_follow_users as $fu ) :
          $fu_id = (int) $fu->ID; ?>
          <li class="jinyu-follow-item">
            <a class="jinyu-follow-avatar" href="<?php echo esc_url( get_author_posts_url( $fu_id ) ); ?>">
              <img src="<?php
                $fu_avatar = jinyu_user_avatar_url( $fu_id, 64 );
                echo $fu_avatar ? esc_url( $fu_avatar ) : esc_attr( jinyu_avatar_default( $fu_id ) );
              ?>" data-jinyu-fallback="<?php echo esc_url( jinyu_avatar_default( $fu_id ) ); ?>" alt="">
            </a>
            <a class="jinyu-follow-name" href="<?php echo esc_url( get_author_posts_url( $fu_id ) ); ?>"><?php echo esc_html( $fu->display_name ); ?></a>
            <button type="button" class="jinyu-btn jinyu-btn-ghost jinyu-follow-toggle is-following"
                    data-jinyu-follow data-target="user" data-id="<?php echo $fu_id; ?>">
              <?php esc_html_e( '取消关注', 'jinyu' ); ?>
            </button>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else :
      jinyu_user_empty( 'fa-regular fa-user-group', __( '还没有关注任何用户', 'jinyu' ) );
    endif; ?>

    <h3 class="jinyu-user-subtitle"><?php esc_html_e( '收藏的系列', 'jinyu' ); ?></h3>
    <?php if ( $jinyu_follow_terms ) : ?>
      <ul class="jinyu-follow-list jinyu-follow-terms">
        <?php foreach ( $jinyu_follow_terms as $ft ) :
          $ft_id = (int) $ft->term_id; ?>
          <li class="jinyu-follow-item">
            <a class="jinyu-follow-name" href="<?php echo esc_url( get_term_link( $ft ) ); ?>">
              <i class="fa-solid fa-layer-group" aria-hidden="true"></i><?php echo esc_html( $ft->name ); ?>
            </a>
            <button type="button" class="jinyu-btn jinyu-btn-ghost jinyu-follow-toggle is-following"
                    data-jinyu-follow data-target="term" data-id="<?php echo $ft_id; ?>">
              <?php esc_html_e( '取消收藏', 'jinyu' ); ?>
            </button>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else :
      jinyu_user_empty( 'fa-regular fa-layer-group', __( '还没有收藏任何系列', 'jinyu' ) );
    endif; ?>
  <?php endif; ?>

<?php elseif ( $tab === 'notifications' ) : ?>
  <h1 class="jinyu-user-title">
    <?php esc_html_e( '消息', 'jinyu' ); ?>
    <button type="button" class="jinyu-btn jinyu-btn-ghost jinyu-notif-readall" data-jinyu-notif-readall>
      <?php esc_html_e( '全部已读', 'jinyu' ); ?>
    </button>
  </h1>
  <ul class="jinyu-notif-list" data-jinyu-notif-list>
    <li class="jinyu-empty"><?php esc_html_e( '加载中…', 'jinyu' ); ?></li>
  </ul>
  <div class="jinyu-notif-more-wrap">
    <button type="button" class="jinyu-btn" data-jinyu-notif-more hidden>
      <?php esc_html_e( '加载更多', 'jinyu' ); ?>
    </button>
  </div>

<?php elseif ( $tab === 'profile' ) : ?>
  <h1 class="jinyu-user-title"><?php esc_html_e( '资料设置', 'jinyu' ); ?></h1>
  <form class="jinyu-profile-form" data-jinyu-profile method="post">
    <input type="hidden" name="_ajax_nonce" value="<?php echo esc_attr( $nonce ); ?>">
    <div class="jinyu-avatar-edit">
      <img class="jinyu-avatar-preview"
           src="<?php
            $avatar_url = jinyu_user_avatar_url( $uid, 96 );
            echo $avatar_url ? esc_url( $avatar_url ) : esc_attr( jinyu_avatar_default( $uid ) );
          ?>"
           data-jinyu-fallback="<?php echo esc_url( jinyu_avatar_default( $uid ) ); ?>"
           alt="avatar">
      <div class="jinyu-avatar-side">
        <label class="jinyu-btn jinyu-btn-ghost jinyu-avatar-pick">
          <?php esc_html_e( '上传头像', 'jinyu' ); ?>
          <input type="file" name="avatar" accept="image/*" data-jinyu-avatar hidden>
        </label>
        <p class="jinyu-field-hint"><?php esc_html_e( '支持 JPG / PNG / WebP / GIF，≤2MB，或在下栏填写图片 URL', 'jinyu' ); ?></p>
      </div>
    </div>
    <label class="jinyu-field jinyu-avatar-url">
      <input type="text" name="avatar_url" placeholder="<?php esc_attr_e( '或填写头像图片 URL', 'jinyu' ); ?>">
    </label>
    <label class="jinyu-field">
      <input type="text" name="display_name" value="<?php echo esc_attr( $user->display_name ); ?>"
             placeholder="<?php esc_attr_e( '昵称', 'jinyu' ); ?>">
    </label>
    <label class="jinyu-field">
      <span class="jinyu-field-label"><?php esc_html_e( '邮箱', 'jinyu' ); ?></span>
      <input type="email" name="user_email" value="<?php echo esc_attr( $user->user_email ); ?>" autocomplete="email">
    </label>
    <p class="jinyu-field-hint"><?php esc_html_e( '修改邮箱需验证：提交后系统会向新邮箱发送确认链接，点击后方可生效。', 'jinyu' ); ?></p>
    <label class="jinyu-field">
      <input type="url" name="user_url" value="<?php echo esc_attr( $user->user_url ); ?>"
             placeholder="<?php esc_attr_e( '个人网站', 'jinyu' ); ?>">
    </label>
    <textarea name="description" rows="3"
              placeholder="<?php esc_attr_e( '个人简介', 'jinyu' ); ?>"><?php echo esc_textarea( $user->description ); ?></textarea>
    <button type="submit" class="jinyu-btn jinyu-btn-primary"><?php esc_html_e( '保存资料', 'jinyu' ); ?></button>
    <p class="jinyu-auth-tip" data-jinyu-profile-tip></p>
  </form>

  <h3 class="jinyu-user-subtitle"><?php esc_html_e( '修改密码', 'jinyu' ); ?></h3>
  <form class="jinyu-profile-form" data-jinyu-password method="post">
    <input type="hidden" name="_ajax_nonce" value="<?php echo esc_attr( $nonce ); ?>">
    <label class="jinyu-field">
      <input type="password" name="old_pwd" autocomplete="current-password"
             placeholder="<?php esc_attr_e( '当前密码', 'jinyu' ); ?>">
    </label>
    <label class="jinyu-field">
      <input type="password" name="new_pwd" autocomplete="new-password"
             placeholder="<?php esc_attr_e( '新密码（至少 6 位）', 'jinyu' ); ?>">
    </label>
    <label class="jinyu-field">
      <input type="password" name="new_pwd2" autocomplete="new-password"
             placeholder="<?php esc_attr_e( '确认新密码', 'jinyu' ); ?>">
    </label>
    <button type="submit" class="jinyu-btn jinyu-btn-primary"><?php esc_html_e( '更新密码', 'jinyu' ); ?></button>
    <p class="jinyu-auth-tip" data-jinyu-password-tip></p>
  </form>

  <?php if ( function_exists( 'jinyu_oauth_enabled' ) && jinyu_oauth_enabled() ) : ?>
    <?php $bindings = jinyu_oauth_bindings( $uid ); ?>
    <?php $uc_url = function_exists( 'jinyu_user_page_url' ) ? jinyu_user_page_url( 'profile' ) : home_url(); ?>
    <h3 class="jinyu-user-subtitle"><?php esc_html_e( '第三方账号绑定', 'jinyu' ); ?></h3>
    <ul class="jinyu-bind-list">
      <?php foreach ( jinyu_oauth_platforms() as $p => $info ) : ?>
        <li>
          <span class="jinyu-bind-ico jinyu-bind-ico-<?php echo esc_attr( $p ); ?>" aria-hidden="true"><?php echo 0 === strpos( (string) $info['icon'], '<svg' ) ? $info['icon'] : esc_html( $info['icon'] ); ?></span>
          <?php echo esc_html( $info['label'] ); ?>
          <?php if ( ! empty( $bindings[ $p ] ) ) : ?>
            <button type="button" class="jinyu-bind-off"
                    data-jinyu-unbind="<?php echo esc_attr( $p ); ?>"
                    data-nonce="<?php echo esc_attr( wp_create_nonce( 'jinyu_sl_unbind' ) ); ?>"
                    data-bind-url="<?php echo esc_url( jinyu_oauth_bind_url( $p, $uc_url ) ); ?>"
                    data-bind-label="<?php echo esc_attr( sprintf( __( '确定解除与「%s」的绑定吗？解绑后将无法再使用该平台一键登录。', 'jinyu' ), $info['label'] ) ); ?>">
              <?php esc_html_e( '解除绑定', 'jinyu' ); ?>
            </button>
          <?php else : ?>
            <a class="jinyu-bind-go" href="<?php echo esc_url( jinyu_oauth_bind_url( $p, $uc_url ) ); ?>"><?php esc_html_e( '去绑定', 'jinyu' ); ?></a>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
    <p class="jinyu-field-hint"><?php esc_html_e( '绑定后可使用该平台一键登录，并关联到当前账号。', 'jinyu' ); ?></p>
  <?php endif; ?>
<?php endif; ?>
