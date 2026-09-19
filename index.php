<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 首页 / 文章归档主循环
 *
 * @package WordPress
 * @subpackage Jinyu
 */
get_header();

// 首屏 Banner：取第一篇置顶文章（仅首页第一页展示）
// ⚠️ 必须与 functions.php 的 pre_get_posts 去重逻辑同源，故走共享函数
$banner = is_paged() ? null : jinyu_home_banner_post();
?>
<div class="jinyu-container jinyu-main-wrap">
  <main id="jinyu-content" class="jinyu-content">

    <?php
    // 首页此前没有任何 <h1>（banner 是 h2、卡片是 h3），
    // 文档大纲缺顶级标题对 SEO 与屏幕阅读器都不利。用屏幕阅读器专属 h1 补上，
    // 站名已在页头品牌区可见，故此处不重复视觉呈现。
    ?>
    <h1 class="jinyu-sr-only"><?php echo esc_html(get_bloginfo('name')); ?></h1>

    <?php
    // 首页轮播（Swiper）
    if (jinyu_is_checked('home_carousel')) :
        $slides = jinyu_get_carousel_slides();
        if (!empty($slides)) :
            // 自动播放由「自动播放间隔」驱动：0 = 不自动播放（原代码误引用了未声明的 home_carousel_autoplay，导致轮播永不自动播放）
            $delay    = (int) jinyu_get_option('home_carousel_delay', 3000);
            $autoplay = $delay > 0 ? $delay : 0;
            $loop     = jinyu_is_checked('home_carousel_loop') ? 1 : 0;
            $effect   = (string) jinyu_get_option('home_carousel_effect', '');
            $mouse    = jinyu_is_checked('home_carousel_mousewheel') ? 1 : 0;
            $hideCap  = jinyu_is_checked('home_carousel_hide_title');
    ?>
      <div class="jinyu-carousel swiper" data-jinyu-carousel
           data-autoplay="<?php echo esc_attr($autoplay); ?>"
           data-loop="<?php echo esc_attr($loop); ?>"
           data-effect="<?php echo esc_attr($effect); ?>"
           data-mousewheel="<?php echo esc_attr($mouse); ?>">
        <div class="swiper-wrapper">
          <?php foreach ($slides as $si => $s) :
              // 仅首屏第一张 eager 提权，其余懒加载，避免多图并发抢占带宽
              $slide_loading = ($si === 0) ? 'eager' : 'lazy';
              $slide_prio    = ($si === 0) ? ' fetchpriority="high"' : '';
          ?>
            <div class="swiper-slide">
              <a class="jinyu-carousel-slide" href="<?php echo esc_url($s['url'] ?: '#'); ?>">
                <img class="jinyu-blur-img" src="<?php echo esc_url($s['image']); ?>" alt="<?php echo esc_attr($s['title']); ?>"<?php if (!empty($s['srcset'])) : ?> srcset="<?php echo esc_attr($s['srcset']); ?>" sizes="100vw"<?php endif; ?> loading="<?php echo esc_attr($slide_loading); ?>" decoding="async"<?php echo $slide_prio; ?>>
                <?php if (!$hideCap) : ?><div class="jinyu-carousel-cap"><span><?php echo esc_html($s['title']); ?></span></div><?php endif; ?>
              </a>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="swiper-pagination"></div>
        <div class="swiper-button-prev"></div>
        <div class="swiper-button-next"></div>
      </div>
    <?php
        endif;
    endif;
    ?>

    <?php if ($banner) : ?>
      <?php
      $cover    = jinyu_get_post_cover($banner->ID, 'large');
      $banner_cats = get_the_category($banner->ID);
      ?>
      <a class="jinyu-banner" href="<?php echo esc_url(get_permalink($banner)); ?>">
        <?php if ($cover) : ?>
          <?php $ph_banner = jinyu_get_post_cover($banner->ID, 'thumbnail'); ?>
          <?php $banner_srcset = jinyu_get_post_cover_srcset($banner->ID); ?>
          <img class="jinyu-blur-img" src="<?php echo esc_url($cover); ?>" alt="<?php echo esc_attr($banner->post_title); ?>"<?php if ($banner_srcset) : ?> srcset="<?php echo esc_attr($banner_srcset); ?>" sizes="100vw"<?php endif; ?> loading="eager" decoding="async" fetchpriority="high"<?php echo $ph_banner ? ' data-ph="' . esc_url($ph_banner) . '"' : ''; ?>>
        <?php else : ?>
          <div class="jinyu-post-cover-ph"><i class="fa-solid fa-fire" aria-hidden="true"></i></div>
        <?php endif; ?>

        <div class="jinyu-banner-overlay">
          <span class="jinyu-banner-cat">
            <?php echo $banner_cats ? esc_html($banner_cats[0]->name) : esc_html__('推荐', JINYU); ?>
          </span>
          <h2 class="jinyu-banner-title"><?php echo esc_html($banner->post_title); ?></h2>
          <?php
          // 同 module-post.php：摘要为空时不输出空 <p>，避免遮罩层多出一块空白
          $banner_excerpt = wp_trim_words(wp_strip_all_tags($banner->post_excerpt ?: $banner->post_content), 40, '…');
          if ($banner_excerpt) :
          ?>
            <p class="jinyu-banner-excerpt"><?php echo esc_html($banner_excerpt); ?></p>
          <?php endif; ?>
        </div>
      </a>
    <?php endif; ?>

    <?php
    // ── 首页模块（仅首页第一页显示）──
    if (!is_paged()) :

        // 四宫格（后台拖拽配置）
        if (jinyu_is_checked('cms_show_four_grid')) :
            $jinyu_grid = jinyu_cms_four_grid_items(4);
            if ($jinyu_grid) :
    ?>
      <div class="jinyu-cms-grid">
        <?php foreach ($jinyu_grid as $jinyu_g) : ?>
          <a class="jinyu-cms-grid-item" href="<?php echo esc_url($jinyu_g['link'] ?: '#'); ?>"<?php echo $jinyu_g['blank'] ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
            <?php $ph_g = ''; $aid_g = attachment_url_to_postid($jinyu_g['img']); if ($aid_g) { $tg = wp_get_attachment_image_src($aid_g, 'thumbnail'); if (!empty($tg[0])) $ph_g = $tg[0]; } ?>
            <img class="jinyu-blur-img" src="<?php echo esc_url(jinyu_img_to_webp_url($jinyu_g['img'])); ?>" alt="<?php echo esc_attr($jinyu_g['title']); ?>" loading="lazy" decoding="async"<?php echo $ph_g ? ' data-ph="' . esc_url($ph_g) . '"' : ''; ?>>
            <span class="jinyu-cms-grid-cap"><?php echo esc_html($jinyu_g['title']); ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php
            endif;
        endif;

        // 两栏布局（按分类）
        if (jinyu_is_checked('cms_show_2box')) :
            $jinyu_box_ids = array_filter(array_map('trim', explode(',', (string) jinyu_get_option('cms_show_2box_id', ''))));
            $jinyu_box_num = max(1, (int) jinyu_get_option('cms_show_2box_num', 6));
            if ($jinyu_box_ids) :
    ?>
      <div class="jinyu-cms-2box">
        <?php foreach ($jinyu_box_ids as $jinyu_cid) :
            $jinyu_cat = get_term((int) $jinyu_cid, 'category');
            if (!$jinyu_cat || is_wp_error($jinyu_cat)) continue;
            // 板块文章 ID 列表缓存 15 分钟：多板块不再每次请求各跑一遍分类查询
            $jinyu_box_key   = 'box_' . $jinyu_cid . '_' . $jinyu_box_num;
            $jinyu_box_ids_a = jinyu_cache_get($jinyu_box_key);
            if (!is_array($jinyu_box_ids_a)) {
                $jinyu_box_tmp = new WP_Query(['cat' => (int) $jinyu_cid, 'posts_per_page' => $jinyu_box_num, 'no_found_rows' => true, 'ignore_sticky_posts' => true, 'fields' => 'ids']);
                $jinyu_box_ids_a = $jinyu_box_tmp->have_posts() ? array_map('intval', $jinyu_box_tmp->posts) : [];
                jinyu_cache_set($jinyu_box_key, $jinyu_box_ids_a, 15 * MINUTE_IN_SECONDS);
            }
            // 该分类无文章：跳过整个板块，避免 post__in=>[] 回退查全表
            if (empty($jinyu_box_ids_a)) continue;
            // 板块文章对象也缓存 15 分钟：ID 列表命中后不再每请求各跑一次 post__in 查询
            $jinyu_box_pk    = 'boxp_' . $jinyu_cid . '_' . $jinyu_box_num;
            $jinyu_box_posts = jinyu_cache_get($jinyu_box_pk);
            if (!is_array($jinyu_box_posts)) {
                $jinyu_box_q = new WP_Query(['post__in' => $jinyu_box_ids_a, 'orderby' => 'post__in', 'no_found_rows' => true, 'ignore_sticky_posts' => true]);
                $jinyu_box_posts = $jinyu_box_q->have_posts() ? $jinyu_box_q->posts : [];
                jinyu_cache_set($jinyu_box_pk, $jinyu_box_posts, 15 * MINUTE_IN_SECONDS);
                wp_reset_postdata();
            }
        ?>
          <section class="jinyu-cms-box">
            <h3 class="jinyu-cms-box-title"><a href="<?php echo esc_url(get_category_link($jinyu_cat)); ?>"><?php echo esc_html($jinyu_cat->name); ?></a></h3>
            <?php if ($jinyu_box_posts) : ?>
              <ul class="jinyu-cms-box-list">
                <?php foreach ($jinyu_box_posts as $jinyu_box_p) : setup_postdata($jinyu_box_p); ?>
                  <li><a href="<?php echo esc_url(get_permalink()); ?>"><?php echo esc_html(get_the_title()); ?></a></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; wp_reset_postdata(); ?>
          </section>
        <?php endforeach; ?>
      </div>
    <?php
            endif;
        endif;

    endif;
    ?>

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
