<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
Template Name: 书单/推荐阅读
*/
get_header();
?>
<div class="jinyu-container jinyu-main-wrap">
  <main id="jinyu-content" class="jinyu-content jinyu-single-wrap">
    <?php jinyu_breadcrumbs(); ?>
    <article class="jinyu-single">
      <h1 class="jinyu-article-title"><?php the_title(); ?></h1>
      <div class="jinyu-article-content">
        <div class="jinyu-book-grid">
          <?php
          $q = new WP_Query([
              'post_type'      => 'post',
              'posts_per_page' => 12,
              'meta_key'       => '_thumbnail_id',
              'orderby'        => 'date',
              'order'          => 'DESC',
              'no_found_rows'  => true,
          ]);
          if ($q->have_posts()) :
              while ($q->have_posts()) : $q->the_post();
                  $cover = jinyu_get_post_cover(get_the_ID(), 'medium');
          ?>
            <a class="jinyu-book-card" href="<?php the_permalink(); ?>">
              <div class="jinyu-book-cover">
                <?php $ph_book = jinyu_get_post_cover(get_the_ID(), 'thumbnail'); ?>
                <img class="jinyu-blur-img" src="<?php echo esc_url($cover); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy"<?php echo $ph_book ? ' data-ph="' . esc_url($ph_book) . '"' : ''; ?>>
              </div>
              <div class="jinyu-book-meta">
                <div class="jinyu-book-title"><?php the_title(); ?></div>
                <div class="jinyu-book-author"><?php echo esc_html(get_the_author()); ?> · <?php echo esc_html(get_the_date('Y-m-d')); ?></div>
              </div>
            </a>
          <?php
              endwhile;
              wp_reset_postdata();
          else :
          ?>
            <p><?php esc_html_e('暂无内容', 'jinyu'); ?></p>
          <?php endif; ?>
        </div>
      </div>
    </article>
  </main>
  <?php get_sidebar(); ?>
</div>
<?php get_footer(); ?>
