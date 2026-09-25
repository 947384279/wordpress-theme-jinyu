<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
get_header(); ?>
<div class="jinyu-container jinyu-main-wrap">
    <main id="jinyu-content" class="jinyu-content">
        <section class="jinyu-404">
            <div class="jinyu-404-hero">
                <div class="jinyu-404-orb jinyu-404-orb-a" aria-hidden="true"></div>
                <div class="jinyu-404-orb jinyu-404-orb-b" aria-hidden="true"></div>
                <div class="jinyu-404-code" role="img" aria-label="404">
                    <span class="jinyu-404-digit" aria-hidden="true">4</span>
                    <span class="jinyu-404-digit jinyu-404-zero" aria-hidden="true">0</span>
                    <span class="jinyu-404-digit" aria-hidden="true">4</span>
                </div>
                <h1 class="jinyu-404-title"><?php esc_html_e('页面走丢了', 'jinyu'); ?></h1>
                <p class="jinyu-404-desc"><?php esc_html_e('你要访问的页面不存在、已被移动或链接已过期，从下面重新出发吧。', 'jinyu'); ?></p>
            </div>

            <?php
            $custom_404 = trim((string) jinyu_get_option('custom_404', ''));
            if ($custom_404) {
                echo '<div class="jinyu-404-custom">' . wp_kses_post($custom_404) . '</div>';
            }
            ?>

            <div class="jinyu-404-search">
                <?php get_search_form(); ?>
            </div>

            <div class="jinyu-404-actions">
                <a class="jinyu-btn jinyu-btn-primary" href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('返回首页', 'jinyu'); ?></a>
                <a class="jinyu-btn" href="<?php echo esc_url(wp_get_referer() ?: home_url('/')); ?>"><?php esc_html_e('返回上一页', 'jinyu'); ?></a>
            </div>

            <div class="jinyu-404-hot">
                <h2 class="jinyu-404-hot-title"><i class="fa-solid fa-fire" aria-hidden="true"></i> <?php esc_html_e('热门文章', 'jinyu'); ?></h2>
                <?php
                $q = new WP_Query([
                    'post_type'      => 'post',
                    'posts_per_page' => 5,
                    'meta_key'       => 'jinyu_views',
                    'orderby'        => 'meta_value_num',
                    'order'          => 'DESC',
                    'no_found_rows'  => true,
                ]);
                if ($q->have_posts()):
                    $rank = 1; ?>
                    <ul class="jinyu-404-hot-list">
                        <?php while ($q->have_posts()): $q->the_post(); ?>
                            <li>
                                <span class="jinyu-404-hot-rank<?php echo $rank <= 3 ? ' is-top' : ''; ?>"><?php echo (int) $rank; ?></span>
                                <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                                <?php if (jinyu_show_views()): ?><span class="jinyu-404-hot-views"><?php echo esc_html(jinyu_get_post_views()); ?></span><?php endif; ?>
                            </li>
                        <?php $rank++; endwhile; wp_reset_postdata(); ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>
    </main>
</div>
<?php get_footer(); ?>
