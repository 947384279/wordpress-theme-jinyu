<?php
/**
 * 自定义页面：每日运气
 *
 * @package         WordPress
 * @subpackage      Jinyu
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
Template Name: I'm Feeling Lucky
*/
get_header();
?>
<div class="jinyu-container jinyu-main-wrap">
	<main id="jinyu-content" class="jinyu-content jinyu-single-wrap">
	<?php jinyu_breadcrumbs(); ?>
	<article class="jinyu-single">
		<h1 class="jinyu-article-title"><?php the_title(); ?></h1>
		<div class="jinyu-article-content">
		<div class="jinyu-luck-box">
			<p class="jinyu-luck-tip"><?php esc_html_e( 'Click the button to jump to a random post on this site.', 'jinyu' ); ?></p>
			<button type="button" class="jinyu-luck-btn" id="jinyu-luck-btn">
			<i class="fa-solid fa-shuffle" aria-hidden="true"></i> <?php esc_html_e( 'I\'m Feeling Lucky', 'jinyu' ); ?>
			</button>
		</div>
		</div>
	</article>
	</main>
	<?php get_sidebar(); ?>
</div>
<?php
get_footer();
