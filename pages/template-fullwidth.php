<?php
/**
 * 全宽页面模板：无侧边栏，内容区占满版心宽度。
 *
 * 适配 Elementor 等页面构建器的全宽布局需求，
 * 也可用于需要更宽内容区的普通独立页面。
 *
 * @package Jinyu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div class="jinyu-container jinyu-main-wrap">
	<main id="jinyu-content" class="jinyu-content jinyu-single-wrap jinyu-wide-wrap">
		<?php jinyu_breadcrumbs(); ?>
		<?php while ( have_posts() ) : the_post(); ?>
			<article class="jinyu-single">
				<h1 class="jinyu-article-title"><?php the_title(); ?></h1>
				<div class="jinyu-article-content"><?php the_content(); ?></div>
				<?php wp_link_pages(); ?>
			</article>
			<?php if ( comments_open() || get_comments_number() ) : comments_template(); endif; ?>
		<?php endwhile; ?>
	</main>
</div>
<?php
get_footer();
