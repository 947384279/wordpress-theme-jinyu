<?php
/**
 * 自定义页面：用户中心
 *
 * @package         WordPress
 * @subpackage      Jinyu
 */


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
$jinyu_tab = get_query_var( 'jinyu_tab' );
$tab       = is_string( $jinyu_tab ) && '' !== $jinyu_tab && array_key_exists( $jinyu_tab, jinyu_user_tabs() ) /* phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- 模板按需在局部覆盖全局，已 wp_reset_postdata */
	? $jinyu_tab
	: jinyu_current_user_tab();
$uid       = get_current_user_id();
$user      = wp_get_current_user();
$nonce     = wp_create_nonce( 'jinyu_front' );
?>
<?php if ( 'dashboard' === $tab ) : ?>
	<h1 class="jinyu-user-title"><?php esc_html_e( 'Overview', 'jinyu' ); ?></h1>
	<?php
	// 时段问候：早 / 午 / 晚 / 深夜，比固定「欢迎回来」更有温度.
	$jinyu_hour        = (int) current_time( 'G' );
	$jinyu_greet       = $jinyu_hour < 6
	? __( 'It\'s late at night', 'jinyu' )
	: ( $jinyu_hour < 12
		? __( 'Good morning', 'jinyu' )
		: ( $jinyu_hour < 18 ? __( 'Good afternoon', 'jinyu' ) : __( 'Good evening', 'jinyu' ) ) );
	$jinyu_unread_dash = (int) jinyu_ext_value( 'unread_count', 0, $uid );
	?>
	<div class="jinyu-user-welcome">
	<div>
		<?php /* translators: %s: 占位符 */ ?>
		<h3 class="jinyu-user-hello"><?php printf( esc_html__( '%1$s，%2$s', 'jinyu' ), esc_html( $jinyu_greet ), esc_html( $user->display_name ) ); ?></h3>
		<p class="jinyu-user-hello-sub">
		<?php
		if ( $jinyu_unread_dash > 0 ) {
			/* translators: %s: 占位符 */
			printf( esc_html__( 'You have %1$s unread messages. Open "Messages" in the sidebar to read them.', 'jinyu' ), number_format_i18n( $jinyu_unread_dash ) ); /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 输出经 esc_html/esc_attr/wp_kses 处理或为核心传入值/整型，WPCS 追不到集中式委托故误报 */
		} else {
			esc_html_e( 'Welcome back. Keep your creative journey going.', 'jinyu' );
		}
		?>
		</p>
	</div>
	<span class="jinyu-user-hello-pill"><?php echo esc_html( jinyu_user_role_label( $uid ) ); ?></span>
	</div>

	<?php $dash_stats = function_exists( 'jinyu_user_stats' ) ? jinyu_user_stats( $uid ) : []; ?>
	<div class="jinyu-user-cards">
	<a class="jinyu-user-card-stat" href="<?php echo esc_url( jinyu_user_page_url( 'posts' ) ); ?>">
		<b><?php echo (int) ( $dash_stats['posts'] ?? 0 ); ?></b><span><?php esc_html_e( 'Posts', 'jinyu' ); ?></span>
	</a>
	<a class="jinyu-user-card-stat" href="
	<?php
	echo esc_url(
		add_query_arg(
			[
				'tab' => 'interact',
				'sub' => 'comments',
			],
			jinyu_user_page_url()
		)
	);
	?>
											">
		<b><?php echo (int) ( $dash_stats['comments'] ?? 0 ); ?></b><span><?php esc_html_e( 'Comments', 'jinyu' ); ?></span>
	</a>
	<a class="jinyu-user-card-stat" href="
	<?php
	echo esc_url(
		add_query_arg(
			[
				'tab' => 'interact',
				'sub' => 'favs',
			],
			jinyu_user_page_url()
		)
	);
	?>
											">
		<b><?php echo (int) ( $dash_stats['favs'] ?? 0 ); ?></b><span><?php esc_html_e( 'Favorite', 'jinyu' ); ?></span>
	</a>
	<div class="jinyu-user-card-stat">
		<b><?php echo (int) ( $dash_stats['likes'] ?? 0 ); ?></b><span><?php esc_html_e( 'Likes received', 'jinyu' ); ?></span>
	</div>
	</div>

	<div class="jinyu-user-actions">
	<a class="jinyu-btn jinyu-btn-primary" href="<?php echo esc_url( jinyu_user_page_url( 'profile' ) ); ?>">
		<i class="fa-solid fa-user-gear" aria-hidden="true"></i><?php esc_html_e( 'Edit profile', 'jinyu' ); ?>
	</a>
	<?php if ( jinyu_user_can_submit() ) : ?>
		<a class="jinyu-btn" href="
		<?php
		echo esc_url(
			add_query_arg(
				[
					'tab'     => 'posts',
					'compose' => '1',
				],
				jinyu_user_page_url()
			)
		);
		?>
									">
		<i class="fa-solid fa-pen-to-square" aria-hidden="true"></i><?php esc_html_e( 'Submit a post', 'jinyu' ); ?>
		</a>
	<?php endif; ?>
	<?php if ( current_user_can( 'edit_posts' ) ) : ?>
		<a class="jinyu-btn" href="<?php echo esc_url( admin_url( 'post-new.php' ) ); ?>">
		<i class="fa-solid fa-plus" aria-hidden="true"></i><?php esc_html_e( 'Write a post', 'jinyu' ); ?>
		</a>
	<?php endif; ?>
	</div>

	<h3 class="jinyu-user-subtitle"><?php esc_html_e( 'Recent posts', 'jinyu' ); ?></h3>
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
	elseif ( jinyu_user_can_submit() ) :
			jinyu_user_empty(
				'fa-regular fa-file-lines',
				__( 'No posts yet. Go write your first one.', 'jinyu' ),
				__( 'Submit a post', 'jinyu' ),
				add_query_arg(
					[
						'tab'     => 'posts',
						'compose' => '1',
					],
					jinyu_user_page_url()
				)
			);
		elseif ( current_user_can( 'edit_posts' ) ) :
			jinyu_user_empty( 'fa-regular fa-file-lines', __( 'No posts yet. Go write your first one.', 'jinyu' ), __( 'Write a post', 'jinyu' ), admin_url( 'post-new.php' ) );
		else :
			echo '<p class="jinyu-empty">' . esc_html__( 'No posts yet', 'jinyu' ) . '</p>';
	endif;
		?>

	<h3 class="jinyu-user-subtitle"><?php esc_html_e( 'Recent Comments', 'jinyu' ); ?></h3>
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
	<p class="jinyu-empty"><?php esc_html_e( 'No comments yet', 'jinyu' ); ?></p>
	<?php endif; ?>

<?php elseif ( 'posts' === $tab ) : ?>
	<?php $jinyu_compose = isset( $_GET['compose'] ) && jinyu_user_can_submit(); ?>
	<div class="jinyu-user-title-row">
	<?php if ( $jinyu_compose ) : ?>
		<h1 class="jinyu-user-title"><?php esc_html_e( 'Submit Post', 'jinyu' ); ?></h1>
		<a class="jinyu-btn jinyu-btn-ghost" href="<?php echo esc_url( jinyu_user_page_url( 'posts' ) ); ?>">
		<i class="fa-solid fa-arrow-left" aria-hidden="true"></i><?php esc_html_e( 'Back to list', 'jinyu' ); ?>
		</a>
	<?php else : ?>
		<h1 class="jinyu-user-title"><?php esc_html_e( 'My Posts', 'jinyu' ); ?></h1>
		<?php if ( jinyu_user_can_submit() ) : ?>
		<a class="jinyu-btn jinyu-btn-primary" href="
			<?php
			echo esc_url(
				add_query_arg(
					[
						'tab'     => 'posts',
						'compose' => '1',
					],
					jinyu_user_page_url()
				)
			);
			?>
														">
			<i class="fa-solid fa-pen-to-square" aria-hidden="true"></i><?php esc_html_e( 'Submit Post', 'jinyu' ); ?>
		</a>
		<?php endif; ?>
	<?php endif; ?>
	</div>

	<?php if ( $jinyu_compose ) : ?>
	<form class="jinyu-submit-form" data-jinyu-submit-post method="post" enctype="multipart/form-data">
		<input type="hidden" name="_ajax_nonce" value="<?php echo esc_attr( $nonce ); ?>">
		<label class="jinyu-field">
		<input type="text" name="post_title" maxlength="100"
				placeholder="<?php esc_attr_e( 'Title (4-100 characters)', 'jinyu' ); ?>">
		</label>
		<span class="jinyu-char-count" data-jinyu-count="post_title" data-min="4" data-max="100" aria-live="polite"></span>
		<label class="jinyu-field">
		<?php
		wp_dropdown_categories(
			[
				'show_option_none' => __( 'Choose a category', 'jinyu' ),
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
			<span class="jinyu-cover-txt"><?php esc_html_e( 'Drag an image here or', 'jinyu' ); ?> <em><?php esc_html_e( 'click to upload', 'jinyu' ); ?></em></span>
			<span class="jinyu-cover-sub"><?php esc_html_e( 'Optional · JPG / PNG / WebP / GIF · up to 5MB', 'jinyu' ); ?></span>
			<span class="jinyu-cover-sub"><?php esc_html_e( 'Landscape 1200×675 recommended; images are compressed automatically after upload', 'jinyu' ); ?></span>
			</div>
			<img class="jinyu-cover-preview" hidden alt="">
			<div class="jinyu-cover-mask" hidden>
			<button type="button" class="jinyu-cover-repick"><?php esc_html_e( 'Replace', 'jinyu' ); ?></button>
			<button type="button" class="jinyu-cover-clear"><?php esc_html_e( 'Remove', 'jinyu' ); ?></button>
			</div>
		</div>
		</div>
		<textarea name="post_content" rows="10" aria-label="<?php esc_attr_e( 'Body content', 'jinyu' ); ?>"
				placeholder="<?php esc_attr_e( 'Body content (at least 20 characters)', 'jinyu' ); ?>"></textarea>
		<span class="jinyu-char-count" data-jinyu-count="post_content" data-min="20" aria-live="polite"></span>
		<input type="text" class="jinyu-field" name="post_tags"
			placeholder="<?php esc_attr_e( 'Tags, separated by commas', 'jinyu' ); ?>">
		<button type="submit" class="jinyu-btn jinyu-btn-primary"><?php esc_html_e( 'Submit post', 'jinyu' ); ?></button>
		<p class="jinyu-auth-tip" data-jinyu-submit-tip aria-live="polite"></p>
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
	elseif ( jinyu_user_can_submit() ) :
			jinyu_user_empty(
				'fa-regular fa-file-lines',
				__( 'No posts yet. Go write your first one.', 'jinyu' ),
				__( 'Submit a post', 'jinyu' ),
				add_query_arg(
					[
						'tab'     => 'posts',
						'compose' => '1',
					],
					jinyu_user_page_url()
				)
			);
		elseif ( current_user_can( 'edit_posts' ) ) :
			jinyu_user_empty( 'fa-regular fa-file-lines', __( 'No posts yet. Go write your first one.', 'jinyu' ), __( 'Write a post', 'jinyu' ), admin_url( 'post-new.php' ) );
		else :
			echo '<p class="jinyu-empty">' . esc_html__( 'No posts yet', 'jinyu' ) . '</p>';
	endif;
		?>
	<?php endif; ?>

<?php elseif ( 'interact' === $tab ) : ?>
	<?php
	$jinyu_sub = jinyu_current_interact_sub();
	?>
	<h1 class="jinyu-user-title"><?php esc_html_e( 'Interactions', 'jinyu' ); ?></h1>
	<nav class="jinyu-user-subnav">
	<?php foreach ( jinyu_user_interact_tabs() as $jinyu_its => $jinyu_itab ) : ?>
		<a class="jinyu-user-sub-item<?php echo $jinyu_sub === $jinyu_its ? ' is-active' : ''; ?>"
		href="
		<?php
		echo esc_url(
			add_query_arg(
				[
					'tab'   => 'interact',
					'sub'   => $jinyu_its,
					'paged' => false,
				],
				jinyu_user_page_url()
			)
		);
		?>
				">
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
			<?php
	else :
			jinyu_user_empty( 'fa-regular fa-comments', __( 'No comments yet. Join the discussion under a post.', 'jinyu' ), __( 'Browse the homepage', 'jinyu' ), home_url( '/' ) );
	endif;
	?>

	<?php elseif ( 'favs' === $jinyu_sub ) : ?>
		<?php
		$favs = jinyu_get_user_favs( $uid );
		$q    = $favs ? new WP_Query(
			[
				'post__in'            => $favs,
				// 按收藏先后排序（post__in 即用户收藏顺序），而非文章日期.
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
			<?php
			while ( $q->have_posts() ) :
				$q->the_post();
				jinyu_fav_cell();
		endwhile;
			?>
		</div>
			<?php
			wp_reset_postdata();
			jinyu_user_pagination( (int) $q->max_num_pages );
	else :
		jinyu_user_empty( 'fa-regular fa-bookmark', __( 'No favorites yet', 'jinyu' ), __( 'Browse the homepage', 'jinyu' ), home_url( '/' ) );
	endif;
	?>

	<?php else : ?>
		<?php
		// 关注数据防御性归一：扩展插槽可能返回对象数组或纯 ID 数组；统一解析为对象，解析失败则丢弃，避免 "read property of int" 致命错误白屏
		$jinyu_follow_users_raw = (array) jinyu_ext_value( 'following_users', [], $uid );
		$jinyu_follow_users = [];
		foreach ( $jinyu_follow_users_raw as $fu_raw ) {
			if ( is_object( $fu_raw ) ) {
				$jinyu_follow_users[] = $fu_raw;
			} elseif ( is_numeric( $fu_raw ) ) {
				$u = get_user_by( 'ID', (int) $fu_raw );
				if ( $u ) {
					$jinyu_follow_users[] = $u;
				}
			}
		}
		$jinyu_follow_terms_raw = (array) jinyu_ext_value( 'following_terms', [], $uid );
		$jinyu_follow_terms = [];
		foreach ( $jinyu_follow_terms_raw as $ft_raw ) {
			if ( is_object( $ft_raw ) ) {
				$jinyu_follow_terms[] = $ft_raw;
			} elseif ( is_numeric( $ft_raw ) ) {
				$t = get_term( (int) $ft_raw );
				if ( $t && ! is_wp_error( $t ) ) {
					$jinyu_follow_terms[] = $t;
				}
			}
		}
		?>
	<h3 class="jinyu-user-subtitle"><?php esc_html_e( 'Following', 'jinyu' ); ?></h3>
		<?php if ( $jinyu_follow_users ) : ?>
		<ul class="jinyu-follow-list">
			<?php
			foreach ( $jinyu_follow_users as $fu ) :
				$fu_id = (int) $fu->ID;
				?>
			<li class="jinyu-follow-item">
			<a class="jinyu-follow-avatar" href="<?php echo esc_url( get_author_posts_url( $fu_id ) ); ?>">
				<img src="
				<?php
				$fu_avatar = jinyu_user_avatar_url( $fu_id, 64 );
				echo $fu_avatar ? esc_url( $fu_avatar ) : esc_attr( jinyu_avatar_default( $fu_id ) );
				?>
				" data-jinyu-fallback="<?php echo esc_attr( jinyu_avatar_default( $fu_id ) ); ?>" alt="">
			</a>
			<a class="jinyu-follow-name" href="<?php echo esc_url( get_author_posts_url( $fu_id ) ); ?>"><?php echo esc_html( $fu->display_name ); ?></a>
			<button type="button" class="jinyu-btn jinyu-btn-ghost jinyu-follow-toggle is-following"
					data-jinyu-follow data-target="user" data-id="<?php echo $fu_id;  /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 输出经 esc_html/esc_attr/wp_kses 处理或为核心传入值/整型，WPCS 追不到集中式委托故误报 */ ?>">
				<?php esc_html_e( 'Unfollow', 'jinyu' ); ?>
			</button>
			</li>
			<?php endforeach; ?>
		</ul>
			<?php
	else :
		jinyu_user_empty( 'fa-regular fa-user-group', __( 'Not following anyone yet', 'jinyu' ) );
	endif;
	?>

	<h3 class="jinyu-user-subtitle"><?php esc_html_e( 'Saved series', 'jinyu' ); ?></h3>
		<?php if ( $jinyu_follow_terms ) : ?>
		<ul class="jinyu-follow-list jinyu-follow-terms">
			<?php
			foreach ( $jinyu_follow_terms as $ft ) :
				$ft_id = (int) $ft->term_id;
				?>
			<li class="jinyu-follow-item">
			<a class="jinyu-follow-name" href="<?php echo esc_url( get_term_link( $ft ) ); ?>">
				<i class="fa-solid fa-layer-group" aria-hidden="true"></i><?php echo esc_html( $ft->name ); ?>
			</a>
			<button type="button" class="jinyu-btn jinyu-btn-ghost jinyu-follow-toggle is-following"
					data-jinyu-follow data-target="term" data-id="<?php echo $ft_id;  /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 输出经 esc_html/esc_attr/wp_kses 处理或为核心传入值/整型，WPCS 追不到集中式委托故误报 */ ?>">
				<?php esc_html_e( 'Remove from favorites', 'jinyu' ); ?>
			</button>
			</li>
			<?php endforeach; ?>
		</ul>
			<?php
	else :
		jinyu_user_empty( 'fa-regular fa-layer-group', __( 'No saved series yet', 'jinyu' ) );
	endif;
	?>
	<?php endif; ?>

<?php elseif ( 'notifications' === $tab ) : ?>
	<h1 class="jinyu-user-title">
	<?php esc_html_e( 'Messages', 'jinyu' ); ?>
	<button type="button" class="jinyu-btn jinyu-btn-ghost jinyu-notif-readall" data-jinyu-notif-readall>
		<?php esc_html_e( 'Mark all as read', 'jinyu' ); ?>
	</button>
	</h1>
	<ul class="jinyu-notif-list" data-jinyu-notif-list>
	<li class="jinyu-empty"><?php esc_html_e( 'Loading…', 'jinyu' ); ?></li>
	</ul>
	<div class="jinyu-notif-more-wrap">
	<button type="button" class="jinyu-btn" data-jinyu-notif-more hidden>
		<?php esc_html_e( 'Load more', 'jinyu' ); ?>
	</button>
	</div>

<?php elseif ( 'profile' === $tab ) : ?>
	<h1 class="jinyu-user-title"><?php esc_html_e( 'Profile Settings', 'jinyu' ); ?></h1>
	<form class="jinyu-profile-form" data-jinyu-profile method="post">
	<input type="hidden" name="_ajax_nonce" value="<?php echo esc_attr( $nonce ); ?>">
	<div class="jinyu-avatar-edit">
		<img class="jinyu-avatar-preview"
			src="
			<?php
			$avatar_url = jinyu_user_avatar_url( $uid, 96 );
			echo $avatar_url ? esc_url( $avatar_url ) : esc_attr( jinyu_avatar_default( $uid ) );
			?>
			"
			data-jinyu-fallback="<?php echo esc_attr( jinyu_avatar_default( $uid ) ); ?>"
			alt="avatar">
		<div class="jinyu-avatar-side">
		<label class="jinyu-btn jinyu-btn-ghost jinyu-avatar-pick">
			<?php esc_html_e( 'Upload avatar', 'jinyu' ); ?>
			<input type="file" name="avatar" accept="image/*" data-jinyu-avatar hidden>
		</label>
		<p class="jinyu-field-hint"><?php esc_html_e( 'JPG / PNG / WebP / GIF up to 2MB, or paste an image URL in the field below', 'jinyu' ); ?></p>
		</div>
	</div>
	<label class="jinyu-field jinyu-avatar-url">
		<input type="text" name="avatar_url" placeholder="<?php esc_attr_e( 'or enter an avatar image URL', 'jinyu' ); ?>">
	</label>
	<label class="jinyu-field">
		<input type="text" name="display_name" value="<?php echo esc_attr( $user->display_name ); ?>"
			placeholder="<?php esc_attr_e( 'Nickname', 'jinyu' ); ?>">
	</label>
	<label class="jinyu-field">
		<span class="jinyu-field-label"><?php esc_html_e( 'Email', 'jinyu' ); ?></span>
		<input type="email" name="user_email" value="<?php echo esc_attr( $user->user_email ); ?>" autocomplete="email">
	</label>
	<p class="jinyu-field-hint"><?php esc_html_e( 'Email changes require verification: after submitting, a confirmation link is sent to the new address, and the change takes effect once you click it.', 'jinyu' ); ?></p>
	<label class="jinyu-field">
		<input type="url" name="user_url" value="<?php echo esc_attr( $user->user_url ); ?>"
			placeholder="<?php esc_attr_e( 'Website', 'jinyu' ); ?>">
	</label>
	<textarea name="description" rows="3" aria-label="<?php esc_attr_e( 'Bio', 'jinyu' ); ?>"
				placeholder="<?php esc_attr_e( 'Bio', 'jinyu' ); ?>"><?php echo esc_textarea( $user->description ); ?></textarea>
	<button type="submit" class="jinyu-btn jinyu-btn-primary"><?php esc_html_e( 'Save profile', 'jinyu' ); ?></button>
	<p class="jinyu-auth-tip" data-jinyu-profile-tip aria-live="polite"></p>
	</form>

	<h3 class="jinyu-user-subtitle"><?php esc_html_e( 'Change password', 'jinyu' ); ?></h3>
	<form class="jinyu-profile-form" data-jinyu-password method="post">
	<input type="hidden" name="_ajax_nonce" value="<?php echo esc_attr( $nonce ); ?>">
	<label class="jinyu-field">
		<input type="password" name="old_pwd" autocomplete="current-password"
			placeholder="<?php esc_attr_e( 'Current password', 'jinyu' ); ?>">
	</label>
	<label class="jinyu-field">
		<input type="password" name="new_pwd" autocomplete="new-password"
			placeholder="<?php esc_attr_e( 'New password (at least 6 characters)', 'jinyu' ); ?>">
	</label>
	<label class="jinyu-field">
		<input type="password" name="new_pwd2" autocomplete="new-password"
			placeholder="<?php esc_attr_e( 'Confirm new password', 'jinyu' ); ?>">
	</label>
	<button type="submit" class="jinyu-btn jinyu-btn-primary"><?php esc_html_e( 'Update password', 'jinyu' ); ?></button>
	<p class="jinyu-auth-tip" data-jinyu-password-tip aria-live="polite"></p>
	</form>

	<?php
	// 第三方账号绑定整块属插件领地（平台列表 / 绑定状态 / 解绑 nonce 全是插件私有数据模型），
	// 主题只留一个插槽并传入「当前用户 + 回跳地址」，不认识任何插件符号。无人应答则整块不渲染。
	$jinyu_bind_html = jinyu_ext_markup(
		'oauth_bindings',
		$uid,
		function_exists( 'jinyu_user_page_url' ) ? jinyu_user_page_url( 'profile' ) : home_url()
	);
	?>
	<?php if ( '' !== $jinyu_bind_html ) : ?>
		<?php echo $jinyu_bind_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 扩展插槽 HTML 由插件自行转义输出 ?>
	<?php endif; ?>
	<?php
endif;
