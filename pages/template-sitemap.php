<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
Template Name: 站点地图
*/
get_header();

$cats = get_categories(['hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC']);
$pages = get_pages(['sort_column' => 'post_title']);
?>
<div class="jinyu-container jinyu-main-wrap">
  <main id="jinyu-content" class="jinyu-content jinyu-single-wrap">
    <?php jinyu_breadcrumbs(); ?>
    <article class="jinyu-single">
      <h1 class="jinyu-article-title"><?php the_title(); ?></h1>
      <div class="jinyu-article-content">
        <?php if (jinyu_is_checked('sitemap_enable')) : ?>
        <div class="jinyu-sitemap">
          <section class="jinyu-sitemap-sec">
            <h2><?php esc_html_e('文章分类', JINYU); ?></h2>
            <?php if ($cats) : foreach ($cats as $c) : ?>
              <div class="jinyu-sitemap-cat">
                <h3><a href="<?php echo esc_url(get_category_link($c->term_id)); ?>"><?php echo esc_html($c->name); ?></a> <span>(<?php echo (int)$c->count; ?>)</span></h3>
                <ul>
                  <?php
                  $posts = get_posts(['cat' => $c->term_id, 'posts_per_page' => 20, 'no_found_rows' => true]);
                  foreach ($posts as $p) :
                  ?>
                    <li><a href="<?php echo esc_url(get_permalink($p)); ?>"><?php echo esc_html($p->post_title); ?></a></li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php endforeach; endif; ?>
          </section>

          <?php if ($pages) : ?>
          <section class="jinyu-sitemap-sec">
            <h2><?php esc_html_e('独立页面', JINYU); ?></h2>
            <ul class="jinyu-sitemap-pages">
              <?php foreach ($pages as $pg) : ?>
                <li><a href="<?php echo esc_url(get_permalink($pg)); ?>"><?php echo esc_html($pg->post_title); ?></a></li>
              <?php endforeach; ?>
            </ul>
          </section>
          <?php endif; ?>
        </div>
        <?php else : ?>
        <p class="jinyu-notice"><?php esc_html_e('站点地图功能已关闭。', JINYU); ?></p>
        <?php endif; ?>
      </div>
    </article>
  </main>
  <?php get_sidebar(); ?>
</div>
<?php get_footer(); ?>
