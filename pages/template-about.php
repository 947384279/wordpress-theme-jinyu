<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
Template Name: 关于
*/
get_header();
?>
<div class="jinyu-container jinyu-main-wrap">
    <main id="jinyu-content" class="jinyu-content jinyu-single-wrap">
        <?php jinyu_breadcrumbs(); ?>
        <article class="jinyu-single jinyu-about">
            <?php while (have_posts()): the_post(); ?>
                <div class="jinyu-about-header">
                    <img src="<?php echo esc_url(get_avatar_url(get_the_author_meta('user_email'), ['size'=>120])); ?>" class="jinyu-about-avatar">
                    <div>
                        <h1 class="jinyu-article-title" style="margin-bottom:4px;"><?php the_author(); ?></h1>
                        <?php if (get_the_author_meta('description')): ?>
                            <div class="jinyu-term-desc"><?php echo wp_kses_post(get_the_author_meta('description')); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="jinyu-article-content" style="margin-top:20px;"><?php the_content(); ?></div>
            <?php endwhile; ?>
        </article>
    </main>
</div>
<?php get_footer(); ?>
