<?php
/**
 * 设置项分组：轮播
 *
 * @package         WordPress
 * @subpackage      Jinyu
 */



namespace Jinyu\Theme\setting\options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Jinyu_OptionCarousel extends Jinyu_BaseOptionItem {

	/**
	 * Get_fields()
	 *
	 * @return array 返回值
	 */
	public function get_fields(): array {
		return [
			'key'    => 'home_modules',
			'title'  => __( 'Homepage Modules', 'jinyu' ),
			'icon'   => 'fa-solid fa-layer-group',
			'desc'   => __( 'Homepage top sections: slider, four-grid, two-column categories, and categories excluded from the homepage loop.', 'jinyu' ),
			'fields' => [
				/* ─── 首页幻灯片 ─── */
				[
					'id'    => 'home_carousel',
					'title' => ( __( 'Enable homepage slider', 'jinyu' ) ),
					'type'  => 'switch',
					'sdt'   => false,
				],
				[
					'id'        => 'home_carousel_mousewheel',
					'title'     => ( __( 'Switch with mouse wheel', 'jinyu' ) ),
					'type'      => 'switch',
					'sdt'       => true,
					'showRefId' => 'home_carousel',
				],
				[
					'id'        => 'home_carousel_hide_title',
					'title'     => ( __( 'Hide title', 'jinyu' ) ),
					'type'      => 'switch',
					'sdt'       => false,
					'showRefId' => 'home_carousel',
				],
				[
					'id'        => 'home_carousel_loop',
					'title'     => ( __( 'Loop', 'jinyu' ) ),
					'type'      => 'switch',
					'sdt'       => true,
					'showRefId' => 'home_carousel',
				],
				[
					'id'        => 'home_carousel_delay',
					'title'     => ( __( 'Autoplay interval (ms)', 'jinyu' ) ),
					'type'      => 'number',
					'sdt'       => 3000,
					'min'       => 0,
					'desc'      => __( '0 disables autoplay', 'jinyu' ),
					'showRefId' => 'home_carousel',
				],
				[
					'id'        => 'home_carousel_count',
					'title'     => ( __( 'Number of auto slides', 'jinyu' ) ),
					'type'      => 'number',
					'sdt'       => 5,
					'min'       => 1,
					'max'       => 20,
					'desc'      => __( 'When no slides are set manually, take this many of the latest posts with cover images', 'jinyu' ),
					'showRefId' => 'home_carousel',
				],
				// 主题自研轮播只支持位移 / 淡入淡出两种过渡，其余 swiper 3D 效果已随依赖移除.
				[
					'id'        => 'home_carousel_effect',
					'title'     => ( __( 'Transition effect', 'jinyu' ) ),
					'type'      => 'select',
					'sdt'       => '',
					'options'   => [
						[
							'label' => __( 'Slide (default)', 'jinyu' ),
							'value' => '',
						],
						[
							'label' => ( __( 'Fade', 'jinyu' ) ),
							'value' => 'fade',
						],
					],
					'showRefId' => 'home_carousel',
				],
				[
					'id'        => 'home_carousel_manual',
					'title'     => ( __( 'Custom slides (optional)', 'jinyu' ) ),
					'type'      => 'textarea',
					'sdt'       => '',
					'desc'      => ( __( 'One per line, format: title|image URL|link. Leave empty to use the latest posts automatically.', 'jinyu' ) ),
					'showRefId' => 'home_carousel',
				],

				/*
				─── 首页版块（四宫格 / 两栏）── */
				// 首页主循环（最新文章）始终展示，home_exclude_cats 用于从主循环排除指定分类（在 functions.php pre_get_posts 生效）.
				[
					'id'    => 'home_exclude_cats',
					'title' => __( 'Categories excluded from latest posts', 'jinyu' ),
					'type'  => 'category-multi',
					'sdt'   => '',
					'desc'  => __( 'Checked categories will not appear in the homepage loop (latest posts)', 'jinyu' ),
				],

				[
					'id'    => 'home_show_four_grid',
					'title' => ( __( 'Show homepage four-grid', 'jinyu' ) ),
					'type'  => 'switch',
					'sdt'   => false,
					'desc'  => ( __( 'Shown on the first page of the homepage only, below the slider', 'jinyu' ) ),
				],
				[
					'id'           => 'home_four_grid_list',
					'title'        => __( 'Homepage four-grid list', 'jinyu' ),
					'type'         => 'dynamic-list',
					'sdt'          => [],
					'draggable'    => true,
					'max'          => 4,
					'showRefId'    => 'home_show_four_grid',
					'dynamicModel' => [
						[
							'id'    => 'title',
							'label' => __( 'Title', 'jinyu' ),
							'sdt'   => '',
							'desc'  => __( 'Used as the image alt text', 'jinyu' ),
						],
						[
							'id'    => 'img',
							'label' => __( 'Image', 'jinyu' ),
							'type'  => 'img',
							'sdt'   => '',
							'desc'  => __( 'The four images should share the same aspect ratio', 'jinyu' ),
						],
						[
							'id'    => 'link',
							'label' => __( 'Target link', 'jinyu' ),
							'sdt'   => '',
						],
						[
							'id'    => 'blank',
							'label' => __( 'Open in new tab', 'jinyu' ),
							'type'  => 'switch',
							'sdt'   => false,
						],
						[
							'id'    => 'hide',
							'label' => __( 'Hidden', 'jinyu' ),
							'type'  => 'switch',
							'sdt'   => false,
							'desc'  => __( 'Once hidden, it will not be displayed', 'jinyu' ),
						],
					],
					'desc'         => __( 'Shows up to the first 4 items that are not hidden and have images set; drag to reorder', 'jinyu' ),
				],
				[
					'id'    => 'home_show_2box',
					'title' => ( __( 'Show two-column categories', 'jinyu' ) ),
					'type'  => 'switch',
					'sdt'   => true,
				],
				[
					'id'    => 'home_show_2box_id',
					'title' => ( __( 'Two-column categories: categories', 'jinyu' ) ),
					'type'  => 'category-multi',
					'sdt'   => '',
					'desc'  => ( __( 'Check the categories to display (split into two columns)', 'jinyu' ) ),
				],
				[
					'id'    => 'home_show_2box_num',
					'title' => ( __( 'Two-column categories: items per column', 'jinyu' ) ),
					'type'  => 'number',
					'sdt'   => 6,
					'min'   => 1,
				],
			],
		];
	}
}
