<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
 get_header(); ?>
<div class="jinyu-container jinyu-main-wrap">
    <main id="jinyu-content" class="jinyu-content">
        <?php jinyu_breadcrumbs(); ?>
        <div class="jinyu-term-header">
            <h1><?php single_term_title(); ?></h1>
            <div class="jinyu-term-desc"><?php esc_html_e('文章系列', 'jinyu'); ?></div>
            <?php if (is_user_logged_in()) :
                $jinyu_term_id = (int)get_queried_object_id();
                $jinyu_following_t = function_exists('jinyu_is_following_term') ? jinyu_is_following_term(get_current_user_id(), $jinyu_term_id) : false; ?>
                <button type="button" class="jinyu-follow-btn<?php echo $jinyu_following_t ? ' is-following' : ''; ?>"
                        data-jinyu-follow data-target="term" data-id="<?php echo $jinyu_term_id; ?>">
                    <i class="fa-solid fa-star" aria-hidden="true"></i>
                    <span><?php echo $jinyu_following_t ? esc_html__('已收藏系列', 'jinyu') : esc_html__('收藏系列', 'jinyu'); ?></span>
                </button>
            <?php endif; ?>
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
