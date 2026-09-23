<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
 get_header(); ?>
<div class="jinyu-container jinyu-main-wrap">
    <main id="jinyu-content" class="jinyu-content">
        <?php $jinyu_s = get_search_query(false); $jinyu_deco = $jinyu_s !== '' ? mb_strtoupper(mb_substr($jinyu_s, 0, 1)) : 'S'; ?>
        <div class="jinyu-term-header">
            <span class="bg-letter" aria-hidden="true"><?php echo esc_html($jinyu_deco); ?></span>
            <?php jinyu_breadcrumbs(); ?>
            <div class="jinyu-term-inner">
                <div class="jinyu-term-badge" aria-hidden="true"><?php echo esc_html($jinyu_deco); ?></div>
                <div class="jinyu-term-body">
                    <h1><?php esc_html_e('搜索结果', 'jinyu'); ?>: <?php echo esc_html(get_search_query(false)); ?></h1>
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