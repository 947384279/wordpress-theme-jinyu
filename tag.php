<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
 get_header(); ?>
<div class="jinyu-container jinyu-main-wrap">
    <main id="jinyu-content" class="jinyu-content">
        <div class="jinyu-term-header">
            <span class="bg-letter" aria-hidden="true">#</span>
            <?php jinyu_breadcrumbs(); ?>
            <div class="jinyu-term-inner">
                <div class="jinyu-term-badge" aria-hidden="true">#</div>
                <div class="jinyu-term-body">
                    <h1>#<?php single_tag_title(); ?></h1>
                    <?php echo jinyu_archive_meta_html(); ?>
                </div>
            </div>
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