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

// 预取各分类最新 20 篇文章的 ID 列表（一次性缓存，渲染时按 ID 水合）
$sitemap_posts_map = jinyu_cache_get('sitemap_posts');
if (!is_array($sitemap_posts_map)) {
    $sitemap_posts_map = [];
    foreach ($cats as $c) {
        $sitemap_posts_map[$c->term_id] = get_posts([
            'cat'              => $c->term_id,
            'posts_per_page'   => 20,
            'fields'           => 'ids',
            'no_found_rows'    => true,
            'ignore_sticky_posts' => true,
        ]);
    }
    jinyu_cache_set('sitemap_posts', $sitemap_posts_map, 12 * HOUR_IN_SECONDS);
}
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
            <h2><?php esc_html_e('文章分类', 'jinyu'); ?></h2>
            <?php if ($cats) : foreach ($cats as $c) : ?>
              <div class="jinyu-sitemap-cat">
                <h3><a href="<?php echo esc_url(get_category_link($c->term_id)); ?>"><?php echo esc_html($c->name); ?></a> <span>(<?php echo (int)$c->count; ?>)</span></h3>
                <ul>
                  <?php
                  // 各分类文章 ID 列表整体缓存 12 小时（save_post/deleted_post 已挂钩 jinyu_cache_flush 自动失效），
                  // 避免爬虫高频抓取 sitemap 时每分类各跑一次查询；命中后按 ID 顺序取对象。
                  $ids   = $sitemap_posts_map[$c->term_id] ?? [];
                  $posts = $ids ? jinyu_hydrate_posts($ids) : [];
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
            <h2><?php esc_html_e('独立页面', 'jinyu'); ?></h2>
            <ul class="jinyu-sitemap-pages">
              <?php foreach ($pages as $pg) : ?>
                <li><a href="<?php echo esc_url(get_permalink($pg)); ?>"><?php echo esc_html($pg->post_title); ?></a></li>
              <?php endforeach; ?>
            </ul>
          </section>
          <?php endif; ?>
        </div>
        <?php else : ?>
        <p class="jinyu-notice"><?php esc_html_e('站点地图功能已关闭。', 'jinyu'); ?></p>
        <?php endif; ?>
      </div>
    </article>
  </main>
  <?php get_sidebar(); ?>
</div>
<?php get_footer(); ?>
