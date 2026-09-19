<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 文章卡片（首页 / 归档 / 加载更多 共用）
 */
$pid = get_the_ID();
$cats = get_the_category();
$cover = jinyu_get_post_cover($pid);
$cat_class = $cats ? ' jinyu-cat-' . ($cats[0]->term_id % 12) : '';
// 首篇（LCP 候选）封面图提前加载并提权，避免 lazy 拖慢首屏最大内容绘制
global $wp_query;
$is_first_cover = (!empty($wp_query) && isset($wp_query->current_post) && $wp_query->current_post === 0);
$cover_loading  = $is_first_cover ? 'eager' : 'lazy';
$cover_fetch    = $is_first_cover ? ' fetchpriority="high"' : '';
// 统一渲染为纵向网格卡（封面在上），首页两栏网格布局；
// 无封面时由 .jinyu-post-cover-ph 占位渐变填充封面区
$card_class = '';
?>
<article class="jinyu-post-card<?php echo esc_attr($card_class); ?>">

    <div class="jinyu-post-cover">
        <?php if (is_sticky()) : ?>
            <span class="jinyu-post-sticky"><?php esc_html_e('置顶', JINYU); ?></span>
        <?php endif; ?>

        <a class="jinyu-post-cover-link" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
            <?php if ($cover) : ?>
                <?php $ph_card = jinyu_get_post_cover($pid, 'thumbnail', false); ?>
                <?php $cover_srcset = jinyu_get_post_cover_srcset($pid); ?>
                <img class="jinyu-post-cover-img jinyu-blur-img" src="<?php echo esc_url($cover); ?>" alt="" width="640" height="360"<?php if ($cover_srcset) : ?> srcset="<?php echo esc_attr($cover_srcset); ?>" sizes="(max-width: 768px) 92vw, 420px"<?php endif; ?> loading="<?php echo $cover_loading; ?>" decoding="async"<?php echo $cover_fetch; ?><?php echo $ph_card ? ' data-ph="' . esc_url($ph_card) . '"' : ''; ?>>
            <?php else : ?>
                <div class="jinyu-post-cover-ph"><i class="fa-solid fa-fire" aria-hidden="true"></i></div>
            <?php endif; ?>
        </a>
    </div>

    <div class="jinyu-post-info">
        <?php if ($cats) : ?>
            <a class="jinyu-post-cat<?php echo esc_attr($cat_class); ?>" href="<?php echo esc_url(get_category_link($cats[0]->term_id)); ?>">
                <i class="fa-regular fa-folder-open" aria-hidden="true"></i><?php echo esc_html($cats[0]->name); ?>
            </a>
        <?php endif; ?>
        <h3 class="jinyu-post-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>

        <?php
        // 摘要为空是正常情况（正文只有图片/短代码时 strip_tags 后无文本）——
        // 此时不输出空 <p>，避免死节点与卡片高度无意义参差
        $jinyu_excerpt = wp_trim_words(get_the_excerpt(), 40, '…');
        if ($jinyu_excerpt) :
        ?>
            <p class="jinyu-post-excerpt"><?php echo esc_html($jinyu_excerpt); ?></p>
        <?php endif; ?>

        <div class="jinyu-post-meta">
            <?php if (jinyu_show_views()) : ?>
            <span><i class="fa-regular fa-eye" aria-hidden="true"></i><?php echo esc_html(jinyu_get_post_views($pid)); ?></span>
            <?php endif; ?>
            <?php if (comments_open() || get_comments_number()) : ?>
                <span><i class="fa-regular fa-comment" aria-hidden="true"></i><?php echo esc_html(get_comments_number()); ?></span>
            <?php endif; ?>
            <span><i class="fa-regular fa-calendar" aria-hidden="true"></i><?php echo esc_html(get_the_date('Y-m-d')); ?></span>
        </div>
    </div>

</article>
