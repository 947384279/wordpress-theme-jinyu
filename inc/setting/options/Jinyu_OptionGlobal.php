<?php
/**
 * 设置项分组：全局
 *
 * @package         WordPress
 * @subpackage      Jinyu
 */



namespace Jinyu\Theme\setting\options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Jinyu_OptionGlobal extends Jinyu_BaseOptionItem {

	/**
	 * Get_fields()
	 *
	 * @return array 返回值
	 */
	public function get_fields(): array {
		return [
			'key'    => 'global',
			'title'  => __( 'Global Settings', 'jinyu' ),
			'desc'   => __( 'Site-wide behavior: maintenance mode, login page appearance, social accounts, cookie notice, etc.', 'jinyu' ),
			'icon'   => 'fa-solid fa-layer-group',
			'fields' => [
				[
					'type'  => 'subhead',
					'title' => __( 'Post Lists', 'jinyu' ),
				],
				[
					'id'      => 'post_style',
					'title'   => __( 'Post list style', 'jinyu' ),
					'type'    => 'select',
					'sdt'     => 'card',
					'options' => [
						[
							'label' => __( 'Standard list', 'jinyu' ),
							'value' => 'list',
						],
						[
							'label' => ( __( 'Card style', 'jinyu' ) ),
							'value' => 'card',
						],
						[
							'label' => ( __( 'Full-width large image', 'jinyu' ) ),
							'value' => 'big',
						],
						[
							'label' => ( __( 'Magazine mixed', 'jinyu' ) ),
							'value' => 'cms',
						],
					],
				],
				[
					'id'    => 'blog_show_load_more',
					'title' => __( 'Show "Load more" in blog mode', 'jinyu' ),
					'type'  => 'switch',
					'sdt'   => false,
				],

				[
					'type'  => 'subhead',
					'title' => __( 'Compatibility & Widgets', 'jinyu' ),
				],
				/* ─── 兼容性 / 杂项开关 ─── */
				[
					'id'    => 'use_widgets_block',
					'title' => __( 'Use block widgets', 'jinyu' ),
					'type'  => 'switch',
					'sdt'   => false,
					'desc'  => __( 'When off, classic widgets are forced (default); when on, the WordPress block widget editor is used', 'jinyu' ),
				],
				[
					'id'    => 'disable_gutenberg_editor',
					'title' => __( 'Disable the Gutenberg editor', 'jinyu' ),
					'type'  => 'switch',
					'sdt'   => false,
					'desc'  => __( 'Use the classic editor for posts / pages in the admin and disable the block (Gutenberg) editor. Only affects the writing screen, not published content', 'jinyu' ),
				],
				[
					'id'    => 'hide_post_views',
					'title' => __( 'Hide post view counts', 'jinyu' ),
					'type'  => 'switch',
					'sdt'   => false,
				],

				[
					'type'  => 'subhead',
					'title' => __( 'Maintenance Mode', 'jinyu' ),
				],
				[
					'id'    => 'maintenance_mode',
					'title' => __( 'Maintenance Mode', 'jinyu' ),
					'type'  => 'switch',
					'sdt'   => false,
					'desc'  => __( 'When enabled, non-admin visitors see the "Site under maintenance" page (HTTP 503)', 'jinyu' ),
				],
				[
					'id'    => 'maintenance_text',
					'title' => __( 'Maintenance message text', 'jinyu' ),
					'type'  => 'textarea',
					'sdt'   => __( 'The site is under maintenance. We will be back soon. We apologize for the inconvenience.', 'jinyu' ),
					'rows'  => 3,
					'desc'  => __( 'Leave empty to use the default text', 'jinyu' ),
				],

				[
					'type'  => 'subhead',
					'title' => __( 'Cookie Compliance', 'jinyu' ),
				],
				/* ─── Cookie 合规 ─── */
				[
					'id'    => 'cookie_consent',
					'title' => __( 'Cookie compliance notice bar', 'jinyu' ),
					'type'  => 'switch',
					'sdt'   => false,
					'desc'  => __( 'Show visitors a cookie / privacy notice; hidden for 30 days after clicking "Agree"', 'jinyu' ),
				],
				[
					'id'    => 'cookie_consent_text',
					'title' => __( 'Notice text', 'jinyu' ),
					'type'  => 'textarea',
					'sdt'   => __( 'We use cookies to improve your browsing experience. By continuing to browse, you agree to our use of cookies.', 'jinyu' ),
					'rows'  => 2,
					'desc'  => __( 'Leave empty to use the default text', 'jinyu' ),
				],

				[
					'type'  => 'subhead',
					'title' => __( 'Layout & Display', 'jinyu' ),
				],
				/* ─── 其他布局与杂项 ─── */
				[
					'id'      => 'post_card_cols',
					'title'   => __( 'Post list columns', 'jinyu' ),
					'type'    => 'select',
					'sdt'     => '2',
					'desc'    => __( 'Applies to the site-wide post list grid; 2 columns recommended with a sidebar', 'jinyu' ),
					'options' => [
						[
							'label' => __( '2 columns', 'jinyu' ),
							'value' => '2',
						],
						[
							'label' => __( '3 columns', 'jinyu' ),
							'value' => '3',
						],
						[
							'label' => __( '4 columns', 'jinyu' ),
							'value' => '4',
						],
					],
				],
				[
					'id'      => 'sidebar_pos',
					'title'   => __( 'Sidebar position', 'jinyu' ),
					'type'    => 'select',
					'sdt'     => 'right',
					'options' => [
						[
							'label' => __( 'Right', 'jinyu' ),
							'value' => 'right',
						],
						[
							'label' => ( __( 'Left', 'jinyu' ) ),
							'value' => 'left',
						],
						[
							'label' => ( __( 'Hidden', 'jinyu' ) ),
							'value' => 'none',
						],
					],
				],
				[
					'id'    => 'grey',
					'title' => __( 'Site-wide grayscale (mourning mode)', 'jinyu' ),
					'type'  => 'switch',
					'sdt'   => false,
				],
				[
					'id'    => 'load_more_infinite',
					'title' => __( 'Auto-load on scroll to bottom', 'jinyu' ),
					'type'  => 'switch',
					'sdt'   => false,
				],
				[
				'id'    => 'views_wait_seconds',
				'title' => __( 'View count cooldown per IP (seconds)', 'jinyu' ),
				'type'  => 'number',
				'sdt'   => 10,
				'desc'  => __( 'Prevents view-count spamming', 'jinyu' ),
			],
			[
			'id'    => 'enable_webp',
			'title' => __( '启用 WebP 自动转换', 'jinyu' ),
			'type'  => 'switch',
			'sdt'   => 1,
			'desc'  => __( '上传图片时自动生成 WebP 副本并前端优先输出 WebP；关闭后不再生成新 WebP，URL 回退原图（已生成的旧文件留盘无害）。', 'jinyu' ),
		],
		[
			'id'    => 'webp_quality',
			'title' => __( 'WebP 压缩质量', 'jinyu' ),
				'type'  => 'slider',
				'sdt'   => 80,
				'min'   => 40,
				'max'   => 100,
				'step'  => 5,
				'unit'  => '%',
				'desc'  => __( '数值越低，生成的 WebP 文件越小，但画质越粗糙。仅对设置后新生成的 WebP 生效；已存在的图片需重新上传或批量重生成。', 'jinyu' ),
			],
		],
		];
	}
}
