<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 日期归档（WordPress 按模板层级自动调用）
 */
get_header();
?>
<div class="jinyu-container jinyu-main-wrap">
  <main id="jinyu-content" class="jinyu-content">
    <?php jinyu_breadcrumbs(); ?>
    <div class="jinyu-archive-head">
      <h1 class="jinyu-archive-title">
        <?php
        if (is_day())        printf(__('每日归档：%s', JINYU), get_the_date());
        elseif (is_month())  printf(__('每月归档：%s', JINYU), get_the_date('Y 年 m 月'));
        else                printf(__('每年归档：%s', JINYU), get_the_date('Y 年'));
        ?>
      </h1>
    </div>

    <?php if (have_posts()) : ?>
      <div class="jinyu-post-grid">
        <?php while (have_posts()) : the_post(); ?>
          <?php get_template_part('templates/module', 'post'); ?>
        <?php endwhile; ?>
      </div>
      <?php jinyu_pagination(); ?>
    <?php else : ?>
      <?php get_template_part('templates/content', 'none'); ?>
    <?php endif; ?>
  </main>
  <?php get_sidebar(); ?>
</div>
<?php get_footer(); ?>
