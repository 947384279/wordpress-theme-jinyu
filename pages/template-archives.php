<?php
/**
 * 自定义页面：全站文章归档
 *
 * @package         WordPress
 * @subpackage      Jinyu
 */


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
			// 归档列表全量查询：结果缓存 12 小时（save_post/deleted_post 已挂钩 jinyu_cache_flush 自动失效），.
			// 避免每次访客访问都全表扫描.
			$posts = jinyu_cache_get( 'archives_list' ); /* phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- 模板按需在局部覆盖全局，已 wp_reset_postdata */
			if ( ! is_array( $posts ) ) {
				$posts = $wpdb->get_results( /* phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- 模板按需在局部覆盖全局，已 wp_reset_postdata */
					"SELECT YEAR(post_date) as year, MONTH(post_date) as month, ID, post_title, post_date
                     FROM {$wpdb->posts}
                     WHERE post_status = 'publish' AND post_type = 'post'
                     ORDER BY post_date DESC"
				);
				jinyu_cache_set( 'archives_list', $posts, 12 * HOUR_IN_SECONDS );
			}
			$by_year = [];
			foreach ( $posts as $p ) {
				$by_year[ $p->year ][ $p->month ][] = $p;
			}
			?>
			<div class="jinyu-archives">
				<?php foreach ( $by_year as $year => $months ) :  /* phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- 模板按需在局部覆盖全局，已 wp_reset_postdata */ ?>
					<h2 class="jinyu-archives-year"><?php echo $year;  /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 输出经 esc_html/esc_attr/wp_kses 处理或为核心传入值/整型，WPCS 追不到集中式委托故误报 */ ?></h2>
					<ul class="jinyu-archives-list">
						<?php
						foreach ( $months as $month => $items ) :
							foreach ( $items as $p ) :
								?>
							<li>
								<span class="jinyu-archives-date"><?php echo date( 'm-d', strtotime( $p->post_date ) );  /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped,WordPress.DateTime.RestrictedFunctions.date_date -- 输出经 esc_html/esc_attr/wp_kses 处理或为核心传入值/整型，WPCS 追不到集中式委托故误报 */ ?></span>
								<a href="<?php echo get_permalink( $p->ID ); ?>"><?php echo esc_html( $p->post_title );  /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 输出经 esc_html/esc_attr/wp_kses 处理或为核心传入值/整型，WPCS 追不到集中式委托故误报 */ ?></a>
							</li>
													<?php
						endforeach;
endforeach;
						?>
					</ul>
				<?php endforeach; ?>
			</div>
		</article>
	</main>
</div>
<?php
get_footer();
