<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
 get_header(); ?>
<div class="jinyu-container jinyu-main-wrap">
    <main id="jinyu-content" class="jinyu-content">
        <?php jinyu_breadcrumbs(); ?>
        <?php while (have_posts()): the_post(); ?>
            <article class="jinyu-single">
                <h1 class="jinyu-article-title"><?php the_title(); ?></h1>
                <div class="jinyu-article-content"><?php the_content(); ?></div>
                <?php wp_link_pages(); ?>
            </article>
            <?php if (comments_open() || get_comments_number()): comments_template(); endif; ?>
        <?php endwhile; ?>
    </main>
    <?php get_sidebar(); ?>
</div>
<?php get_footer(); ?>