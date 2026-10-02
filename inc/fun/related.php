<?php
/**
 * 相关文章 / 热门文章取数（侧栏卡与小工具的单一数据源）。
 *
 * 这三个函数是纯呈现层的「按维度取文章」逻辑，不涉及任何私有数据模型，
 * 因此留在主题内；副作用（浏览量自增等数据采集）才归配套插件。
 *
 * @package         WordPress
 * @subpackage      Jinyu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'jinyu_get_related_post_ids' ) ) {
	/**
	 * 取相关文章 ID 列表（带结果集缓存）。
	 *
	 * 查询走 tax_query + 随机抽取，每次请求重跑 SQL 很浪费。
	 * 这里把 ID 列表缓存 HOUR_IN_SECONDS，TTL 内所有访客复用同一份；
	 * jinyu_get_related_posts() 与「相关文章」小工具都调本函数，避免两处各跑一遍。
	 *
	 * @param int    $post_id 基准文章 ID，0 取当前全局 $post。
	 * @param int    $num     需要条数。
	 * @param string $type    排序维度：''|tags（默认）|cats|views|random。
	 * @return int[] 文章 ID 列表（不含基准文章）。
	 */
	function jinyu_get_related_post_ids( $post_id = 0, $num = 4, $type = '' ) {
		global $post;

		$pid = $post_id ?: ( isset( $post->ID ) ? $post->ID : 0 );
		if ( ! $pid ) {
			return [];
		}
		$num = max( 1, (int) $num );

		$key = 'related_' . $pid . '_' . $type . '_' . $num;
		$ids = jinyu_cache_get( $key );
		if ( is_array( $ids ) ) {
			return $ids;
		}

		$cats = wp_get_post_categories( $pid );
		$tags = wp_get_post_tags( $pid, [ 'fields' => 'ids' ] );

		$base = [
			'post_type'           => 'post',
			'posts_per_page'      => 200,
			'post__not_in'        => [ $pid ],
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'fields'              => 'ids',
		];

		$args = $base;
		// 'random' 不进任何分支：直接沿用 $base（全站随机，不限定标签/分类）。
		if ( 'views' === $type ) {
			// 协同过滤近似：同标签/分类相关，再按浏览量降序——越热门越靠前。
			if ( $tags || $cats ) {
				$args['tax_query'] = [ 'relation' => 'OR' ];
				if ( $tags ) {
					$args['tax_query'][] = [
						'taxonomy' => 'post_tag',
						'field'    => 'term_id',
						'terms'    => $tags,
					];
				}
				if ( $cats ) {
					$args['tax_query'][] = [
						'taxonomy' => 'category',
						'field'    => 'term_id',
						'terms'    => $cats,
					];
				}
			}
		} elseif ( 'cats' === $type ) {
			if ( $cats ) {
				$args['category__in'] = $cats;
			}
		} elseif ( $tags ) {
			// tags（默认）：优先标签，无标签回退分类。
			$args['tag__in'] = $tags;
		} elseif ( $cats ) {
			$args['category__in'] = $cats;
		}

		$q = new WP_Query( $args );

		// 标签/分类命中为空（文章未被打标签或与其它文章无重合）时自动回退到全站随机，
		// 保证「相关文章」区域在站点不止一篇文章时始终有内容，避免开了开关却看不到区域。
		if ( ! $q->have_posts() && 'random' !== $type ) {
			$q = new WP_Query( $base );
		}

		$pool = $q->have_posts() ? array_map( 'intval', $q->posts ) : [];
		shuffle( $pool );
		$ids = array_values( array_diff( array_slice( $pool, 0, $num ), [ $pid ] ) );

		jinyu_cache_set( $key, $ids, HOUR_IN_SECONDS );
		return $ids;
	}
}

if ( ! function_exists( 'jinyu_get_related_posts' ) ) {
	/**
	 * 取相关文章 WP_Query（供 templates/post-relevant.php 的 while/have_posts 循环使用）。
	 *
	 * 复用 jinyu_get_related_post_ids() 的缓存结果，按 post__in 重组查询。
	 *
	 * @param int    $post_id 基准文章 ID，0 取当前全局 $post。
	 * @param int    $num     需要条数。
	 * @param string $type    排序维度，见 jinyu_get_related_post_ids()。
	 * @return WP_Query
	 */
	function jinyu_get_related_posts( $post_id = 0, $num = 4, $type = '' ) {
		global $post;

		$pid = $post_id ?: ( isset( $post->ID ) ? $post->ID : 0 );
		$ids = $pid ? jinyu_get_related_post_ids( $pid, $num, $type ) : [];

		// post__in 为空数组时 WP_Query 会回退查全表，必须用哨兵 [0]（不存在的 ID）保证空集。
		if ( ! $ids ) {
			return new WP_Query(
				[
					'post__in'      => [ 0 ],
					'no_found_rows' => true,
				]
			);
		}

		return new WP_Query(
			[
				'post_type'           => 'post',
				'post__in'            => $ids,
				'orderby'             => 'post__in',
				'post__not_in'        => [ $pid ],
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			]
		);
	}
}

if ( ! function_exists( 'jinyu_get_hot_posts' ) ) {
	/**
	 * 取「热门文章」列表（侧栏默认卡与「金玉·热门文章」小工具共用同一份数据源）。
	 *
	 * 先按浏览量降序取，取不满 $num 时用最新文章补齐，保证新站/新发布阶段该区域不空。
	 *
	 * ⚠️ 不要改成 meta_query OR( EXISTS + NOT EXISTS ) 的写法：EXISTS 子句实际生成的是
	 * INNER JOIN ON (post_id)，orderby 引用到的那行 meta 可能是任意其它键（_thumbnail_id 等），
	 * 排序结果不可控。两条确定性查询，行为才可预期。
	 *
	 * @param int $num 需要的文章数。
	 * @return WP_Post[]
	 */
	function jinyu_get_hot_posts( $num = 5 ) {
		$num = max( 1, (int) $num );
		$key = 'hot_posts_' . $num;

		// 结果集缓存：热门/补齐两次查询只在 TTL 内跑一次，之后复用 ID 列表。
		$ids = jinyu_cache_get( $key );
		if ( is_array( $ids ) ) {
			return jinyu_hydrate_posts( $ids );
		}

		$hot = new WP_Query(
			[
				'post_type'              => 'post',
				'posts_per_page'         => $num,
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'meta_key'               => 'jinyu_views',
				'orderby'                => [
					'meta_value_num' => 'DESC',
					'date'           => 'DESC',
				],
				// 限定近 1 年，缩小 meta_value_num 排序的候选集（避免对全表已发布文章做 filesort）。
				'date_query'             => [ [ 'after' => gmdate( 'Y-m-d', strtotime( '-365 days' ) ) ] ],
			]
		);
		$posts = $hot->posts;

		if ( count( $posts ) < $num ) {
			$fill  = new WP_Query(
				[
					'post_type'              => 'post',
					'posts_per_page'         => $num - count( $posts ),
					'post__not_in'           => $posts ? wp_list_pluck( $posts, 'ID' ) : [ 0 ],
					'ignore_sticky_posts'    => true,
					'no_found_rows'          => true,
					'update_post_term_cache' => false,
					'orderby'                => 'date',
					'order'                  => 'DESC',
				]
			);
			$posts = array_merge( $posts, $fill->posts );
		}

		$ids = array_map( 'intval', wp_list_pluck( $posts, 'ID' ) );
		jinyu_cache_set( $key, $ids, 10 * MINUTE_IN_SECONDS );

		return jinyu_hydrate_posts( $ids );
	}
}
