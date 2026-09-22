<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
 get_header(); ?>
<div class="jinyu-container jinyu-main-wrap">
    <main id="jinyu-content" class="jinyu-content">
        <?php jinyu_breadcrumbs(); ?>
        <?php $author = get_queried_object(); ?>
        <div class="jinyu-term-header jinyu-author-header">
            <img class="jinyu-author-avatar-img"
                 src="<?php
                    $aid  = $author->ID;
                    $aurl = jinyu_user_avatar_url($aid, 96);
                    echo $aurl ? esc_url($aurl) : esc_attr(jinyu_avatar_default($aid));
                 ?>"
                 onerror="this.onerror=null;this.src='<?php echo esc_attr(jinyu_avatar_default($author->ID)); ?>';"
                 alt="">
            <div class="jinyu-author-meta">
                <h1><?php echo esc_html($author->display_name); ?></h1>
                <?php if ($author->description): ?><p class="jinyu-term-desc"><?php echo esc_html($author->description); ?></p><?php endif; ?>
                <?php if (is_user_logged_in() && get_current_user_id() !== (int)$author->ID) :
                    $jinyu_following = function_exists('jinyu_is_following') ? jinyu_is_following(get_current_user_id(), (int)$author->ID) : false; ?>
                    <button type="button" class="jinyu-follow-btn<?php echo $jinyu_following ? ' is-following' : ''; ?>"
                            data-jinyu-follow data-target="user" data-id="<?php echo (int)$author->ID; ?>">
                        <i class="fa-solid <?php echo $jinyu_following ? 'fa-user-check' : 'fa-user-plus'; ?>" aria-hidden="true"></i>
                        <span><?php echo $jinyu_following ? esc_html__('已关注', 'jinyu') : esc_html__('关注', 'jinyu'); ?></span>
                    </button>
                <?php endif; ?>
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