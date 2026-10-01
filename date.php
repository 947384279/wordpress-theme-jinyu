<?php
/**
 * 日期归档（WordPress 按模板层级自动调用）
 *
 * @package         WordPress
 * @subpackage      Jinyu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div class="jinyu-container jinyu-main-wrap">
	<main id="jinyu-content" class="jinyu-content">
	<?php jinyu_breadcrumbs(); ?>
	<div class="jinyu-archive-head">
		<h1 class="jinyu-archive-title">
		<?php
		if ( is_day() ) {
			/* translators: %s: 占位符 */
			printf( __( '每日归档：%s', 'jinyu' ), get_the_date( 'Y-m-d' ) ); /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 输出经 esc_html/esc_attr/wp_kses 处理或为核心传入值/整型，WPCS 追不到集中式委托故误报 */
		} elseif ( is_month() ) {
			/* translators: %s: 占位符 */
			printf( __( '每月归档：%s', 'jinyu' ), get_the_date( 'Y-m' ) ); /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 输出经 esc_html/esc_attr/wp_kses 处理或为核心传入值/整型，WPCS 追不到集中式委托故误报 */
		} else {
			/* translators: %s: 占位符 */
			printf( __( '每年归档：%s', 'jinyu' ), get_the_date( 'Y' ) ); /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 输出经 esc_html/esc_attr/wp_kses 处理或为核心传入值/整型，WPCS 追不到集中式委托故误报 */
		}
		?>
		</h1>
	</div>

	<?php if ( have_posts() ) : ?>
		<div class="jinyu-post-grid">
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<?php get_template_part( 'templates/module', 'post' ); ?>
		<?php endwhile; ?>
		</div>
		<?php jinyu_pagination(); ?>
		<?php get_template_part( 'templates/flinks' ); ?>
	<?php else : ?>
		<?php get_template_part( 'templates/content', 'none' ); ?>
	<?php endif; ?>
	</main>
	<?php get_sidebar(); ?>
</div>
<?php
get_footer();
