<?php
/**
 * 首页 / 文章归档主循环
 *
 * @package WordPress
 * @subpackage Jinyu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

// 置顶文章统一进网格（见 templates/module-post.php 的 .jinyu-post-card.is-sticky 卡片高亮），不再独占首屏大图 Banner.

// 轮播实例自增序号，用于生成 DOM id 供 aria-controls 引用.
$jinyu_car_seq = 0;
?>
<div class="jinyu-container jinyu-main-wrap">
	<main id="jinyu-content" class="jinyu-content">

	<?php
	// 首页此前没有任何 <h1>（banner 是 h2、卡片是 h3），.
	// 文档大纲缺顶级标题对 SEO 与屏幕阅读器都不利。用屏幕阅读器专属 h1 补上，.
	// 站名已在页头品牌区可见，故此处不重复视觉呈现.
	?>
	<h1 class="jinyu-sr-only"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h1>

	<?php
	// 首页轮播（主题自研 JinyuCarousel，零第三方依赖）.
	if ( jinyu_is_checked( 'home_carousel' ) ) :
		$slides = jinyu_get_carousel_slides();
		if ( ! empty( $slides ) ) :
			// 自动播放由「自动播放间隔」驱动：0 = 不自动播放（原代码误引用了未声明的 home_carousel_autoplay，导致轮播永不自动播放）.
			$delay    = (int) jinyu_get_option( 'home_carousel_delay', 3000 );
			$autoplay = $delay > 0 ? $delay : 0;
			$loop     = jinyu_is_checked( 'home_carousel_loop' ) ? 1 : 0;
			$effect   = (string) jinyu_get_option( 'home_carousel_effect', '' );
			$mouse    = jinyu_is_checked( 'home_carousel_mousewheel' ) ? 1 : 0;
			$hideCap  = jinyu_is_checked( 'home_carousel_hide_title' );
			$car_id   = 'jinyu-carousel-' . ( isset( $jinyu_car_seq ) ? $jinyu_car_seq++ : 0 );
			?>
		<div class="jinyu-carousel" id="<?php echo esc_attr( $car_id ); ?>" data-jinyu-carousel
			role="region" aria-roledescription="<?php esc_attr_e( 'carousel', 'jinyu' ); ?>" aria-label="<?php echo esc_attr__( 'Featured posts slider', 'jinyu' ); ?>"
			data-autoplay="<?php echo esc_attr( $autoplay ); ?>"
			data-loop="<?php echo esc_attr( $loop ); ?>"
			data-effect="<?php echo esc_attr( $effect ); ?>"
			data-mousewheel="<?php echo esc_attr( $mouse ); ?>">
		<div class="jinyu-carousel-track">
			<?php
			foreach ( $slides as $si => $s ) : /* phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- 模板按需在局部覆盖全局，已 wp_reset_postdata */
				/*
				* 全部 eager，不用 loading="lazy"。
				* track 接管后是 overflow:hidden 的位移容器，浏览器对内部懒加载图片的
				* "接近视口"判定永远不成立——切到第 2 张起就会是一块灰底。
				* 只有首张给 fetchpriority 抢带宽，其余按文档顺序排队，不影响 LCP。
				*/
				$slide_prio = ( $si === 0 ) ? ' fetchpriority="high"' : '';
				?>
			<div class="jinyu-carousel-slide" role="group" aria-roledescription="<?php esc_attr_e( 'slide', 'jinyu' ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Slide %1$d of %2$d', 'jinyu' ), $si + 1, count( $slides ) ) ); /* phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment */ ?>">
				<a class="jinyu-carousel-link" href="<?php echo esc_url( $s['url'] ?: '#' ); ?>">
				<?php
				// 轮播图通常是首页 LCP 元素，必须带 width/height：否则图片到达时撑开容器产生 CLS。
				// 取不到尺寸（自定义 URL 且图头探测失败）时省略属性，行为与原来一致。
				$slide_size = jinyu_image_size( $s['image'] );
				?>
				<img class="jinyu-blur-img" src="<?php echo esc_url( $s['image'] ); ?>" alt=""
				<?php echo $slide_size[0] ? ' width="' . (int) $slide_size[0] . '" height="' . (int) $slide_size[1] . '"' : ''; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 已 intval 强转 */ ?>
				<?php
				if ( ! empty( $s['srcset'] ) ) :
					?>
					srcset="<?php echo esc_attr( $s['srcset'] ); ?>" sizes="100vw"<?php endif; ?> loading="eager" decoding="async"<?php echo $slide_prio;  /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 输出经 esc_html/esc_attr/wp_kses 处理或为核心传入值/整型，WPCS 追不到集中式委托故误报 */ ?>>
				<?php
				if ( ! $hideCap ) :
					?>
					<div class="jinyu-carousel-cap"><span><?php echo esc_html( $s['title'] ); ?></span></div><?php endif; ?>
				</a>
			</div>
			<?php endforeach; ?>
		</div>

		<button type="button" class="jinyu-carousel-arrow jinyu-carousel-arrow-prev" aria-controls="<?php echo esc_attr( $car_id ); ?>" aria-label="<?php echo esc_attr__( '上一张', 'jinyu' ); ?>">
			<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M15 5.5 8.5 12 15 18.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
		</button>
		<button type="button" class="jinyu-carousel-arrow jinyu-carousel-arrow-next" aria-controls="<?php echo esc_attr( $car_id ); ?>" aria-label="<?php echo esc_attr__( '下一张', 'jinyu' ); ?>">
			<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M9 5.5 15.5 12 9 18.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
		</button>

			<?php if ( count( $slides ) > 1 ) : ?>
			<div class="jinyu-carousel-dots">
				<?php foreach ( $slides as $di => $ds ) : ?>
			<button type="button" class="jinyu-carousel-dot<?php echo $di === 0 ? ' is-active' : ''; ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Go to slide %d', 'jinyu' ), $di + 1 ) ); /* phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment */ ?>"<?php echo $di === 0 ? ' aria-current="true"' : ''; ?>></button>
			<?php endforeach; ?>
			</div>
			<p class="jinyu-sr-only" aria-live="polite" data-jinyu-carousel-status></p>
		<?php endif; ?>
		</div>
			<?php
		endif;
	endif;
	?>

	<?php /* 置顶文章统一进网格（.is-sticky 卡片高亮），不再独占首屏大图 Banner */ ?>

	<?php
	// ── 首页模块（仅首页第一页显示）──.
	if ( ! is_paged() ) :

		// 四宫格（后台拖拽配置）.
		if ( jinyu_is_checked( 'home_show_four_grid' ) ) :
			$jinyu_grid = jinyu_cms_four_grid_items( 4 );
			if ( $jinyu_grid ) :
				?>
		<div class="jinyu-cms-grid">
				<?php foreach ( $jinyu_grid as $jinyu_g ) : ?>
			<a class="jinyu-cms-grid-item" href="<?php echo esc_url( $jinyu_g['link'] ?: '#' ); ?>"<?php echo $jinyu_g['blank'] ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
					<?php
					$ph_g  = '';
					$dim_g = array( 0, 0 );
					$aid_g = jinyu_url_to_postid( $jinyu_g['img'] );
					if ( $aid_g ) {
						$tg = wp_get_attachment_image_src( $aid_g, 'thumbnail' );
						if ( $tg && ! empty( $tg[0] ) ) {
							$ph_g = jinyu_lqip_url( $tg[0] );
						}
						$fg = wp_get_attachment_image_src( $aid_g, 'full' );
						if ( $fg && ! empty( $fg[1] ) && ! empty( $fg[2] ) ) {
							$dim_g = array( (int) $fg[1], (int) $fg[2] );
						}
					}
					// 附件元数据没给（如自定义 URL）时回退到通用 helper；仍取不到就省略尺寸属性。
					if ( ! $dim_g[0] ) {
						$dim_g = jinyu_image_size( $jinyu_g['img'] );
					}
					?>
			<img class="jinyu-blur-img" src="<?php echo esc_url( jinyu_img_to_webp_url( $jinyu_g['img'] ) ); ?>" alt=""
					<?php echo $dim_g[0] ? ' width="' . (int) $dim_g[0] . '" height="' . (int) $dim_g[1] . '"' : ''; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 已 intval 强转 */ ?>
			loading="lazy" decoding="async"<?php echo $ph_g ? ' data-ph="' . esc_url( $ph_g ) . '"' : ''; ?>>
			<span class="jinyu-cms-grid-cap"><?php echo esc_html( $jinyu_g['title'] ); ?></span>
			</a>
		<?php endforeach; ?>
		</div>
				<?php
			endif;
		endif;

		// 两栏布局（按分类）.
		if ( jinyu_is_checked( 'home_show_2box' ) ) :
			$jinyu_box_ids = array_filter( array_map( 'trim', explode( ',', (string) jinyu_get_option( 'home_show_2box_id', '' ) ) ) );
			$jinyu_box_num = max( 1, (int) jinyu_get_option( 'home_show_2box_num', 6 ) );
			if ( $jinyu_box_ids ) :
				?>
		<div class="jinyu-cms-2box">
				<?php
				foreach ( $jinyu_box_ids as $jinyu_cid ) :
					$jinyu_cat = get_term( (int) $jinyu_cid, 'category' );
					if ( ! $jinyu_cat || is_wp_error( $jinyu_cat ) ) {
						continue;
					}
					// 板块文章 ID 列表缓存 15 分钟：多板块不再每次请求各跑一遍分类查询.
					$jinyu_box_key   = 'box_' . $jinyu_cid . '_' . $jinyu_box_num;
					$jinyu_box_ids_a = jinyu_cache_get( $jinyu_box_key );
					if ( ! is_array( $jinyu_box_ids_a ) ) {
						$jinyu_box_tmp   = new WP_Query(
							[
								'cat'                 => (int) $jinyu_cid,
								'posts_per_page'      => $jinyu_box_num,
								'no_found_rows'       => true,
								'ignore_sticky_posts' => true,
								'fields'              => 'ids',
							]
						);
						$jinyu_box_ids_a = $jinyu_box_tmp->have_posts() ? array_map( 'intval', $jinyu_box_tmp->posts ) : [];
						jinyu_cache_set( $jinyu_box_key, $jinyu_box_ids_a, 15 * MINUTE_IN_SECONDS );
					}
					// 该分类无文章：跳过整个板块，避免 post__in=>[] 回退查全表.
					if ( empty( $jinyu_box_ids_a ) ) {
						continue;
					}
					// 板块文章对象也缓存 15 分钟：ID 列表命中后不再每请求各跑一次 post__in 查询.
					$jinyu_box_pk    = 'boxp_' . $jinyu_cid . '_' . $jinyu_box_num;
					$jinyu_box_posts = jinyu_cache_get( $jinyu_box_pk );
					if ( ! is_array( $jinyu_box_posts ) ) {
						$jinyu_box_q     = new WP_Query(
							[
								'post__in'            => $jinyu_box_ids_a,
								'orderby'             => 'post__in',
								'no_found_rows'       => true,
								'ignore_sticky_posts' => true,
							]
						);
						$jinyu_box_posts = $jinyu_box_q->have_posts() ? $jinyu_box_q->posts : [];
						jinyu_cache_set( $jinyu_box_pk, $jinyu_box_posts, 15 * MINUTE_IN_SECONDS );
						wp_reset_postdata();
					}
					?>
			<section class="jinyu-cms-box">
			<h3 class="jinyu-cms-box-title"><a href="<?php echo esc_url( get_category_link( $jinyu_cat ) ); ?>"><?php echo esc_html( $jinyu_cat->name ); ?></a></h3>
					<?php if ( $jinyu_box_posts ) : ?>
				<ul class="jinyu-cms-box-list">
						<?php
						foreach ( $jinyu_box_posts as $jinyu_box_p ) :
							setup_postdata( $jinyu_box_p );
							?>
					<li><a href="<?php echo esc_url( get_permalink() ); ?>"><?php echo esc_html( get_the_title() ); ?></a></li>
				<?php endforeach; ?>
				</ul>
						<?php
			endif;
					wp_reset_postdata();
					?>
			</section>
				<?php endforeach; ?>
		</div>
				<?php
			endif;
		endif;

	endif;
	?>

	<?php if ( have_posts() ) : ?>

		<div class="jinyu-post-grid">
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<?php get_template_part( 'templates/module', 'post' ); ?>
		<?php endwhile; ?>
		</div>

		<?php jinyu_pagination(); ?>
		<?php get_template_part( 'templates/flinks' ); ?>

	<?php else : ?>

		<?php get_template_part( 'templates/content', 'none' ); ?>

	<?php endif; ?>

	</main>

	<?php get_sidebar(); ?>
</div>
<?php
get_footer();
