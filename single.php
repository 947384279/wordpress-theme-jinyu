<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
 get_header(); ?>
<div class="jinyu-container jinyu-main-wrap">
  <main id="jinyu-content" class="jinyu-content jinyu-single-wrap">
    <?php while (have_posts()): the_post(); ?>
      <article class="jinyu-single">
        <?php if (jinyu_is_checked('breadcrumb_enable')) jinyu_breadcrumbs(); ?>
        <h1 class="jinyu-article-title"><?php the_title(); ?></h1>
        <?php $jy_subtitle = get_post_meta(get_the_ID(), '_jinyu_subtitle', true); ?>
        <?php if ($jy_subtitle): ?><p class="jinyu-article-subtitle"><?php echo esc_html($jy_subtitle); ?></p><?php endif; ?>
        <div class="jinyu-article-meta">
          <span><i class="fa-regular fa-user"></i><?php the_author(); ?></span>
          <span><i class="fa-regular fa-calendar"></i><?php echo get_the_date('Y-m-d'); ?></span>
          <?php if (get_the_time('Y-m-d') !== get_the_modified_time('Y-m-d')): ?><span><i class="fa-regular fa-pen-to-square"></i><?php esc_html_e('更新于', 'jinyu'); ?> <?php the_modified_time('Y-m-d'); ?></span><?php endif; ?>
          <span><i class="fa-regular fa-clock"></i><?php echo jinyu_read_time(); ?></span>
          <span><i class="fa-regular fa-file-lines"></i><?php echo jinyu_post_word_count(); ?></span>
          <?php if (jinyu_show_views()): ?><span><i class="fa-regular fa-eye"></i><?php echo jinyu_get_post_views(); ?></span><?php endif; ?>
          <?php if (comments_open() || get_comments_number()): ?><span><i class="fa-regular fa-comment"></i><?php echo get_comments_number(); ?></span><?php endif; ?>
          <?php $jy_source = get_post_meta(get_the_ID(), '_jinyu_source', true); ?>
          <?php if ($jy_source): ?><span><i class="fa-regular fa-building"></i><?php echo esc_html($jy_source); ?></span><?php endif; ?>
        </div>

        <?php $jy_series = function_exists('jinyu_get_series_posts') ? jinyu_get_series_posts(get_the_ID()) : false; ?>
        <?php if ($jy_series): ?>
        <div class="jinyu-series-bar">
          <span class="jinyu-series-name"><i class="fa-solid fa-layer-group" aria-hidden="true"></i> <?php echo esc_html($jy_series['term']->name); ?></span>
          <span class="jinyu-series-nav">
            <?php if ($jy_series['prev']): ?><a class="jinyu-series-prev" href="<?php echo esc_url(get_permalink($jy_series['prev'])); ?>"><i class="fa-solid fa-angle-left" aria-hidden="true"></i> <?php esc_html_e('上一篇', 'jinyu'); ?></a><?php endif; ?>
            <span class="jinyu-series-count"><?php printf(esc_html__('第 %1$d / %2$d 篇', 'jinyu'), $jy_series['index'] + 1, $jy_series['total']); ?></span>
            <?php if ($jy_series['next']): ?><a class="jinyu-series-next" href="<?php echo esc_url(get_permalink($jy_series['next'])); ?>"><?php esc_html_e('下一篇', 'jinyu'); ?> <i class="fa-solid fa-angle-right" aria-hidden="true"></i></a><?php endif; ?>
          </span>
        </div>
        <?php endif; ?>

        <div class="jinyu-reader-tools" aria-label="<?php esc_attr_e('阅读字号调节', 'jinyu'); ?>">
          <span class="jinyu-reader-tools-label"><i class="fa-solid fa-text-height" aria-hidden="true"></i></span>
          <button type="button" class="jinyu-fs-btn" data-jinyu-fs="dec" aria-label="<?php esc_attr_e('缩小字号', 'jinyu'); ?>">A⁻</button>
          <button type="button" class="jinyu-fs-btn" data-jinyu-fs="inc" aria-label="<?php esc_attr_e('放大字号', 'jinyu'); ?>">A⁺</button>
          <button type="button" class="jinyu-fs-btn jinyu-fs-reset" data-jinyu-fs="reset" aria-label="<?php esc_attr_e('重置字号', 'jinyu'); ?>"><?php esc_html_e('默认', 'jinyu'); ?></button>
          <span class="jinyu-fs-state" data-jinyu-fs-state aria-live="polite">100%</span>
        </div>

        <?php $jy_ext = get_post_meta(get_the_ID(), '_jinyu_external_url', true); ?>
        <?php if ($jy_ext): ?><div class="jinyu-article-external"><a class="jinyu-btn" href="<?php echo esc_url($jy_ext); ?>" target="_blank" rel="noopener"><?php esc_html_e('查看原文', 'jinyu'); ?> <i class="fa-solid fa-arrow-up-right-from-square"></i></a></div><?php endif; ?>
        <?php $cover = jinyu_get_post_cover(); if ($cover && jinyu_is_checked('show_cover') && !get_post_meta(get_the_ID(), '_jinyu_hide_cover', true)): ?>
          <?php $ph_feat = jinyu_lqip_url( jinyu_get_post_cover(get_the_ID(), 'thumbnail') ); ?>
          <img class="jinyu-featured jinyu-blur-img" src="<?php echo esc_url($cover); ?>" alt="<?php the_title_attribute(); ?>" loading="eager"<?php echo $ph_feat ? ' data-ph="' . esc_url($ph_feat) . '"' : ''; ?>>
        <?php endif; ?>
        <div class="jinyu-article-content" data-jinyu-author="<?php echo esc_attr(get_the_author()); ?>" data-jinyu-site="<?php echo esc_attr(get_bloginfo('name')); ?>" data-jinyu-read-min="<?php echo (int)jinyu_read_minutes(); ?>"><?php the_content(); ?></div>
        <div class="jinyu-article-tags">
            <?php foreach ((get_the_category() ?: []) as $jy_c): ?>
                <a class="jinyu-cat-<?php echo $jy_c->term_id % 12; ?>" href="<?php echo esc_url(get_category_link($jy_c)); ?>">
                    <i class="fa-solid fa-folder"></i><?php echo esc_html($jy_c->name); ?>
                </a>
            <?php endforeach; ?>
            <?php foreach ((get_the_tags() ?: []) as $jy_t): ?>
                <a class="jinyu-cat-<?php echo $jy_t->term_id % 12; ?>" href="<?php echo esc_url(get_tag_link($jy_t)); ?>">
                    <i class="fa-solid fa-hashtag"></i><?php echo esc_html($jy_t->name); ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php wp_link_pages(); ?>
        <?php get_template_part('templates/post', 'actions'); ?>
        <?php get_template_part('templates/post', 'nav'); ?>
        <?php do_action('jinyu_single_after_content'); ?>

        <div class="jinyu-vote" data-jinyu-vote data-jinyu-post="<?php echo get_the_ID(); ?>">
          <span class="jinyu-vote-q"><?php esc_html_e('这篇有帮助吗？', 'jinyu'); ?></span>
          <div class="jinyu-vote-btns">
            <button type="button" class="jinyu-vote-btn jinyu-vote-yes" data-jinyu-vote-yes aria-label="<?php esc_attr_e('有用', 'jinyu'); ?>">
              <i class="fa-solid fa-thumbs-up" aria-hidden="true"></i><span class="jinyu-vote-label"><?php esc_html_e('有用', 'jinyu'); ?></span><span class="jinyu-vote-count" data-jinyu-vote-yes-n><?php echo (int)get_post_meta(get_the_ID(), '_jinyu_helpful_yes', true); ?></span>
            </button>
            <button type="button" class="jinyu-vote-btn jinyu-vote-no" data-jinyu-vote-no aria-label="<?php esc_attr_e('没用', 'jinyu'); ?>">
              <i class="fa-solid fa-thumbs-down" aria-hidden="true"></i><span class="jinyu-vote-label"><?php esc_html_e('没用', 'jinyu'); ?></span><span class="jinyu-vote-count" data-jinyu-vote-no-n><?php echo (int)get_post_meta(get_the_ID(), '_jinyu_helpful_no', true); ?></span>
            </button>
          </div>
        </div>

        <?php get_template_part('templates/post', 'author'); ?>
        <?php get_template_part('templates/post', 'relevant'); ?>

        <div class="jinyu-coview" data-jinyu-coview data-jinyu-post="<?php echo get_the_ID(); ?>"></div>
      </article>
      <?php if (comments_open() || get_comments_number()): comments_template(); endif; ?>
    <?php endwhile; ?>

    <?php
    // 文章底部小工具区（外观 › 小工具 › 文章底部）：没放东西时一个节点都不输出
    if (is_active_sidebar('sidebar-single-bottom')) : ?>
    <div class="jinyu-single-widgets">
      <?php dynamic_sidebar('sidebar-single-bottom'); ?>
    </div>
    <?php endif; ?>
  </main>
  <?php get_sidebar(); ?>
</div>
<?php get_footer(); ?>