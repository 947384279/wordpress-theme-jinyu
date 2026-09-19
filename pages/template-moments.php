<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
Template Name: 说说/动态
*/
get_header();
?>
<div class="jinyu-container jinyu-main-wrap">
  <main id="jinyu-content" class="jinyu-content jinyu-single-wrap">
    <?php jinyu_breadcrumbs(); ?>
    <article class="jinyu-single">
      <h1 class="jinyu-article-title"><?php the_title(); ?></h1>
      <div class="jinyu-article-content">
        <div class="jinyu-moments">
          <?php
          // 优先使用「时光圈」自定义文章类型；无数据时回退到「状态」格式文章，兼容旧内容
          $q = new WP_Query([
              'post_type'      => 'moments',
              'posts_per_page' => 50,
              'no_found_rows'  => true,
          ]);
          if (!$q->have_posts()) {
              $q = new WP_Query([
                  'post_type'      => 'post',
                  'posts_per_page' => 50,
                  'no_found_rows'  => true,
                  'tax_query'      => [
                      ['taxonomy' => 'post_format', 'field' => 'slug', 'terms' => 'post-format-status'],
                  ],
              ]);
          }
          if ($q->have_posts()) :
              while ($q->have_posts()) : $q->the_post();
          ?>
            <div class="jinyu-moment">
              <div class="jinyu-moment-dot"></div>
              <div class="jinyu-moment-body">
                <?php if (has_post_thumbnail()) : ?>
                  <div class="jinyu-moment-thumb"><?php the_post_thumbnail('medium'); ?></div>
                <?php endif; ?>
                <div class="jinyu-moment-text"><?php echo wpautop(get_the_content()); ?></div>
                <div class="jinyu-moment-meta">
                  <a href="<?php the_permalink(); ?>"><?php echo esc_html(get_the_date('Y-m-d H:i')); ?></a>
                  <?php if (comments_open() || (int) get_comments_number() > 0) : ?>
                    · <a href="<?php echo esc_url(get_comments_link()); ?>"><?php echo sprintf(__('%s 条评论', JINYU), (int) get_comments_number()); ?></a>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php
              endwhile;
              wp_reset_postdata();
          else :
          ?>
            <p><?php esc_html_e('暂无动态，请在后台「时光圈」中发表。', JINYU); ?></p>
          <?php endif; ?>
        </div>
      </div>
    </article>
  </main>
  <?php get_sidebar(); ?>
</div>
<?php get_footer(); ?>
