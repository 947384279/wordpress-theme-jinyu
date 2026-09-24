<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
Template Name: 文章归档
*/
get_header();
?>
<div class="jinyu-container jinyu-main-wrap">
    <main id="jinyu-content" class="jinyu-content jinyu-single-wrap jinyu-wide-wrap">
        <?php jinyu_breadcrumbs(); ?>
        <article class="jinyu-single">
            <h1 class="jinyu-article-title"><?php the_title(); ?></h1>
            <?php
            global $wpdb;
            // 归档列表全量查询：结果缓存 12 小时（save_post/deleted_post 已挂钩 jinyu_cache_flush 自动失效），
            // 避免每次访客访问都全表扫描。
            $posts = jinyu_cache_get('archives_list');
            if (!is_array($posts)) {
                $posts = $wpdb->get_results(
                    "SELECT YEAR(post_date) as year, MONTH(post_date) as month, ID, post_title, post_date
                     FROM {$wpdb->posts}
                     WHERE post_status = 'publish' AND post_type = 'post'
                     ORDER BY post_date DESC"
                );
                jinyu_cache_set('archives_list', $posts, 12 * HOUR_IN_SECONDS);
            }
            $by_year = [];
            foreach ($posts as $p) {
                $by_year[$p->year][$p->month][] = $p;
            }
            ?>
            <div class="jinyu-archives">
                <?php foreach ($by_year as $year => $months): ?>
                    <h2 class="jinyu-archives-year"><?php echo $year; ?></h2>
                    <ul class="jinyu-archives-list">
                        <?php foreach ($months as $month => $items): foreach ($items as $p): ?>
                            <li>
                                <span class="jinyu-archives-date"><?php echo date('m-d', strtotime($p->post_date)); ?></span>
                                <a href="<?php echo get_permalink($p->ID); ?>"><?php echo esc_html($p->post_title); ?></a>
                            </li>
                        <?php endforeach; endforeach; ?>
                    </ul>
                <?php endforeach; ?>
            </div>
        </article>
    </main>
</div>
<?php get_footer(); ?>
