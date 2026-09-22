<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
Template Name: 友情链接
*/
get_header();
?>
<div class="jinyu-container jinyu-main-wrap">
    <main id="jinyu-content" class="jinyu-content jinyu-single-wrap jinyu-wide-wrap">
        <?php jinyu_breadcrumbs(); ?>
        <article class="jinyu-single">
            <h1 class="jinyu-article-title"><?php the_title(); ?></h1>
            <div class="jinyu-article-content"><?php the_content(); ?></div>
            <?php
            $bookmarks = get_bookmarks(['orderby'=>'name']);
            if (!empty($bookmarks)):
            ?>
            <div class="jinyu-links-grid">
                <?php foreach ($bookmarks as $bkm): ?>
                    <a class="jinyu-link-item" href="<?php echo esc_url($bkm->link_url); ?>" target="_blank" rel="noopener">
                        <div class="jinyu-link-ico">🌐</div>
                        <div class="jinyu-link-info">
                            <div class="jinyu-link-name"><?php echo esc_html($bkm->link_name); ?></div>
                            <?php if ($bkm->link_description): ?>
                                <div class="jinyu-link-desc"><?php echo esc_html($bkm->link_description); ?></div>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p>暂无友情链接，请在 WordPress 后台「链接」中添加。</p>
            <?php endif; ?>
        </article>
    </main>
</div>
<?php get_footer(); ?>
