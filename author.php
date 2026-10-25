<?php
/**
 * 作者归档页模板
 *
 * @package         WordPress
 * @subpackage      Jinyu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
get_header(); ?>
<div class="jinyu-container jinyu-main-wrap">
	<main id="jinyu-content" class="jinyu-content">
		<?php
		$author = get_queried_object();

		// 作者 ID 必须取查询变量里的数字 ID：get_queried_object() 返回的 WP_User 对象在
		// 某些归档上下文里 ID 属性可能为空（如作者只有昵称归档、无数字 ID 映射），
		// 直接用 $author->ID 传给 jinyu_user_avatar_url(int) 会抛 TypeError 导致整页 500。
		$author_id = (int) get_query_var( 'author' );
		if ( $author_id <= 0 && isset( $author->ID ) ) {
			$author_id = (int) $author->ID;
		}
		// display_name 同样兜底，避免对象为 null 时 mb_substr 收到 null。
		$author_name = isset( $author->display_name ) && '' !== $author->display_name
			? $author->display_name
			: __( 'Author', 'jinyu' );
		$jinyu_deco  = mb_strtoupper( mb_substr( $author_name, 0, 1 ) );
		?>
		<div class="jinyu-term-header jinyu-author-header">
			<span class="bg-letter" aria-hidden="true"><?php echo esc_html( $jinyu_deco ); ?></span>
			<?php jinyu_breadcrumbs(); ?>
			<div class="jinyu-term-inner">
				<img class="jinyu-author-avatar-img"
					src="
					<?php
						$aurl = $author_id > 0 ? jinyu_user_avatar_url( $author_id, 96 ) : '';
						echo $aurl ? esc_url( $aurl ) : esc_attr( jinyu_avatar_default( $author_id ) );
					?>
					"
					data-jinyu-fallback="<?php echo esc_attr( jinyu_avatar_default( $author_id ) ); ?>"
					alt="">
				<div class="jinyu-author-meta">
					<h1><?php echo esc_html( $author_name ); ?></h1>
					<?php echo jinyu_archive_meta_html();  /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 输出经 esc_html/esc_attr/wp_kses 处理或为核心传入值/整型，WPCS 追不到集中式委托故误报 */ ?>
					<?php
					if ( ! empty( $author->description ) ) :
						?>
						<p class="jinyu-term-desc"><?php echo esc_html( $author->description ); ?></p><?php endif; ?>
					<?php
					if ( $author_id > 0 && is_user_logged_in() && get_current_user_id() !== $author_id ) :
						$jinyu_following = function_exists( 'jinyu_is_following' ) ? jinyu_is_following( get_current_user_id(), $author_id ) : false;
						?>
						<button type="button" class="jinyu-follow-btn<?php echo $jinyu_following ? ' is-following' : ''; ?>"
								data-jinyu-follow data-target="user" data-id="<?php echo $author_id; ?>">
							<i class="fa-solid <?php echo $jinyu_following ? 'fa-user-check' : 'fa-user-plus'; ?>" aria-hidden="true"></i>
							<span><?php echo $jinyu_following ? esc_html__( 'Following', 'jinyu' ) : esc_html__( 'Follow', 'jinyu' ); ?></span>
						</button>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php get_template_part( 'templates/archive-loop' ); ?>
	</main>
	<?php get_sidebar(); ?>
</div>
<?php
get_footer();
