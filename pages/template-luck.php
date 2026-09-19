<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
Template Name: 手气不错
*/
get_header();
?>
<div class="jinyu-container jinyu-main-wrap">
  <main id="jinyu-content" class="jinyu-content jinyu-single-wrap">
    <?php jinyu_breadcrumbs(); ?>
    <article class="jinyu-single">
      <h1 class="jinyu-article-title"><?php the_title(); ?></h1>
      <div class="jinyu-article-content">
        <div class="jinyu-luck-box">
          <p class="jinyu-luck-tip"><?php esc_html_e('点击按钮，随机跳转到一篇站内文章。', JINYU); ?></p>
          <button type="button" class="jinyu-luck-btn" id="jinyu-luck-btn">
            <i class="fa-solid fa-shuffle" aria-hidden="true"></i> <?php esc_html_e('手气不错', JINYU); ?>
          </button>
        </div>
      </div>
    </article>
  </main>
  <?php get_sidebar(); ?>
</div>
<?php get_footer(); ?>
