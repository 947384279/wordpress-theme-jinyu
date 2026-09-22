<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
Template Name: 随机文章
*/
get_header();

// 用「随机偏移 + 主键顺序」替代 orderby=rand：避免全表 filesort，每次刷新仍是真随机。
$published   = (int) wp_count_posts('post')->publish;
$rand_offset = $published > 1 ? mt_rand(0, $published - 1) : 0;
$rand = new WP_Query([
    'posts_per_page' => 1,
    'offset'         => $rand_offset,
    'orderby'        => 'ID',
    'no_found_rows'  => true,
    'post_type'      => 'post',
    'post_status'    => 'publish',
]);
$random_post = $rand->have_posts() ? $rand->posts[0] : null;
wp_reset_postdata();

// 再列几篇供选择：取一个随机起点窗口（10 篇），顺序确定、起点随机，依旧是真随机
$list_offset = $published > 10 ? mt_rand(0, $published - 10) : 0;
$list = new WP_Query([
    'posts_per_page' => 10,
    'offset'         => $list_offset,
    'orderby'        => 'ID',
    'no_found_rows'  => true,
    'post_type'      => 'post',
    'post_status'    => 'publish',
]);
?>
<div class="jinyu-container jinyu-main-wrap">
  <main id="jinyu-content" class="jinyu-content jinyu-single-wrap">
    <?php jinyu_breadcrumbs(); ?>
    <article class="jinyu-single">
      <h1 class="jinyu-article-title"><?php the_title(); ?></h1>
      <div class="jinyu-article-content">
        <div class="jinyu-random-box">
          <?php if ($random_post) : ?>
            <a class="jinyu-random-current" href="<?php echo esc_url(get_permalink($random_post)); ?>">
              <span class="jinyu-random-label"><?php esc_html_e('为你随机推荐', 'jinyu'); ?></span>
              <span class="jinyu-random-title"><?php echo esc_html(get_the_title($random_post)); ?></span>
            </a>
          <?php endif; ?>
          <a class="jinyu-btn jinyu-random-btn" href="<?php echo esc_url(get_permalink()); ?>"><?php esc_html_e('换一篇', 'jinyu'); ?></a>
        </div>

        <h3 class="jinyu-random-list-title"><?php esc_html_e('随便逛逛', 'jinyu'); ?></h3>
        <ul class="jinyu-random-list">
          <?php if ($list->have_posts()) : while ($list->have_posts()) : $list->the_post(); ?>
            <li>
              <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
              <span class="jinyu-random-date"><?php echo get_the_date('Y-m-d'); ?></span>
            </li>
          <?php endwhile; endif; wp_reset_postdata(); ?>
        </ul>
      </div>
    </article>
  </main>
  <?php get_sidebar(); ?>
</div>
<?php get_footer(); ?>
