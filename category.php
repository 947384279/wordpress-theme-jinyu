<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
 get_header(); ?>
<div class="jinyu-container jinyu-main-wrap">
    <main id="jinyu-content" class="jinyu-content">
        <?php jinyu_breadcrumbs(); ?>
        <div class="jinyu-term-header">
            <h1><?php single_cat_title(); ?></h1>
            <?php if (category_description()): ?><div class="jinyu-term-desc"><?php echo wp_kses_post(category_description()); ?></div><?php endif; ?>
        </div>
        <?php if (have_posts()): ?>
            <div class="jinyu-post-grid">
                <?php while (have_posts()): the_post(); get_template_part('templates/module','post'); endwhile; ?>
            </div>
            <?php jinyu_pagination(['load_more' => false]); ?>
            <?php get_template_part('templates/flinks'); ?>
        <?php else: ?>
            <?php get_template_part('templates/content','none'); ?>
        <?php endif; ?>
    </main>
    <?php get_sidebar(); ?>
</div>
<?php get_footer(); ?>