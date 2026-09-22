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
// 置顶卡片加 .is-sticky：配合 .jinyu-post-card.is-sticky 的描边高亮与丝带角标，
// 让置顶文章在网格里一眼可辨（原仅封面右上角一个小角标，过于隐蔽）
$card_class = is_sticky() ? ' is-sticky' : '';
?>
<article id="post-<?php the_ID(); ?>" <?php post_class('jinyu-post-card' . $card_class); ?>>

    <div class="jinyu-post-cover">
        <?php if (is_sticky()) : ?>
            <span class="jinyu-post-sticky"><i class="fa-solid fa-thumbtack" aria-hidden="true"></i><?php esc_html_e('置顶', 'jinyu'); ?></span>
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
        <h3 class="jinyu-post-title"><?php if (is_sticky()) : ?><span class="jinyu-post-pintag"><i class="fa-solid fa-thumbtack" aria-hidden="true"></i><?php esc_html_e('置顶', 'jinyu'); ?></span><?php endif; ?><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>

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
            <?php if (jinyu_is_checked('like_enable')) : ?>
            <span><i class="fa-regular fa-heart" aria-hidden="true"></i><span class="jinyu-like-count" data-post-id="<?php echo esc_attr($pid); ?>"><?php echo esc_html(jinyu_get_post_likes($pid)); ?></span></span>
            <?php endif; ?>
            <?php if (comments_open() || get_comments_number()) : ?>
                <span><i class="fa-regular fa-comment" aria-hidden="true"></i><?php echo esc_html(get_comments_number()); ?></span>
            <?php endif; ?>
            <?php
            // 系列归属：未归入系列的文章不输出。列表页同系列共用 term 级缓存，不会逐篇重复查库
            $jinyu_series     = jinyu_is_checked('card_series_enable') ? (function_exists('jinyu_series_badge') ? jinyu_series_badge((int) $pid) : false) : false;
            $jinyu_series_url = $jinyu_series ? get_term_link($jinyu_series['term']) : '';
            if ($jinyu_series && !is_wp_error($jinyu_series_url)) :
            ?>
            <a class="jinyu-post-meta-series" href="<?php echo esc_url($jinyu_series_url); ?>"><i class="fa-solid fa-layer-group" aria-hidden="true"></i><span class="jinyu-post-meta-series-name"><?php echo esc_html($jinyu_series['term']->name); ?></span><span class="jinyu-post-meta-series-index"><?php printf(esc_html__('第 %1$d/%2$d 篇', 'jinyu'), $jinyu_series['index'] + 1, $jinyu_series['total']); ?></span></a>
            <?php endif; ?>
            <span><i class="fa-regular fa-calendar" aria-hidden="true"></i><?php echo esc_html(get_the_date('Y-m-d')); ?></span>
            <?php
            // 最近更新：仅修改明显晚于发布时才提示（阈值见 jinyu_post_updated）
            $jinyu_updated = jinyu_is_checked('card_updated_enable') ? jinyu_post_updated((int) $pid) : '';
            if ($jinyu_updated) :
            ?>
            <span class="jinyu-post-meta-updated"><i class="fa-regular fa-pen-to-square" aria-hidden="true"></i><?php echo esc_html($jinyu_updated); ?></span>
            <?php endif; ?>
        </div>
    </div>

</article>
