<?php
/**
 * 设置项分组：风格外观
 *
 * @package         WordPress
 * @subpackage      Jinyu
 */



namespace Jinyu\Theme\setting\options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Jinyu_OptionStyle extends Jinyu_BaseOptionItem {

	/**
	 * Get_fields()
	 *
	 * @return array 返回值
	 */
	public function get_fields(): array {
		return [
			'key'    => 'style',
			'title'  => __( 'Style & Appearance', 'jinyu' ),
			'desc'   => __( 'Visual style: theme color, light/dark mode, border radius, fonts, etc.', 'jinyu' ),
			'icon'   => 'fa-solid fa-palette',
			'fields' => [
				[
					'type'  => 'subhead',
					'title' => __( 'Basic Appearance', 'jinyu' ),
				],
				[
					'id'      => 'style_color_primary',
					'title'   => __( 'Theme primary color', 'jinyu' ),
					'type'    => 'color',
					'sdt'     => '#FF6B35',
					'presets' => [ '#FF6B35', '#1C60F3', '#07c160', '#ff4d4f', '#722ed1', '#000000' ],
				],
				[
					'id'    => 'style_radius',
					'title' => __( 'Border radius', 'jinyu' ),
					'type'  => 'slider',
					'sdt'   => 6,
					'min'   => 0,
					'max'   => 16,
					'step'  => 1,
					'unit'  => 'px',
				],
				[
					'id'    => 'content_font_size',
					'title' => __( 'Body font size', 'jinyu' ),
					'type'  => 'slider',
					'sdt'   => 16,
					'min'   => 13,
					'max'   => 20,
					'step'  => 1,
					'unit'  => 'px',
					'desc'  => __( 'Base font size for post content', 'jinyu' ),
				],
				[
					'id'      => 'dark_palette',
					'title'   => __( 'Dark color scheme', 'jinyu' ),
					'type'    => 'select',
					'sdt'     => 'default',
					'desc'    => __( 'Only takes effect in dark mode: click the moon icon at the top right on the front end to see it. "Dark gray" is softer and easier on the eyes; "Pure black" has stronger contrast, saves power, and suits OLED screens', 'jinyu' ),
					'options' => [
						[
							'label' => __( 'Dark gray', 'jinyu' ),
							'value' => 'default',
						],
						[
							'label' => __( 'Pure black', 'jinyu' ),
							'value' => 'pureblack',
						],
					],
				],
				[
					'id'    => 'cn_autospace',
					'title' => __( 'Auto-spacing between CJK and Latin text', 'jinyu' ),
					'type'  => 'switch',
					'sdt'   => false,
					'desc'  => __( 'Handled in PHP: automatically inserts spaces at CJK / Latin / number boundaries (protects HTML tags and links; does not affect already formatted content)', 'jinyu' ),
				],
				[
					'id'    => 'ext_link_target',
					'title' => __( 'External links in new window + nofollow', 'jinyu' ),
					'type'  => 'switch',
					'sdt'   => true,
					'desc'  => __( 'Open content external links in a new window with rel="nofollow noopener"', 'jinyu' ),
				],

				[
					'type'  => 'subhead',
					'title' => __( 'Theme Mode', 'jinyu' ),
				],
				/* ─── 主题模式 ─── */
				[
					'id'      => 'theme_mode',
					'title'   => __( 'Default theme mode', 'jinyu' ),
					'type'    => 'select',
					'sdt'     => 'auto',
					'desc'    => __( '"Light / Dark" sets a fixed default mode; "Follow system" switches with the visitor\'s OS. Either way, the light/dark toggle always appears at the top right on the front end, and visitors can switch manually at any time (choice is remembered).', 'jinyu' ),
					'options' => [
						[
							'label' => __( 'Light mode', 'jinyu' ),
							'value' => 'light',
						],
						[
							'label' => __( 'Dark mode', 'jinyu' ),
							'value' => 'dark',
						],
						[
							'label' => __( 'Follow system', 'jinyu' ),
							'value' => 'auto',
						],
					],
				],
				[
					'id'    => 'nav_blur',
					'title' => __( 'Navbar frosted-glass effect', 'jinyu' ),
					'type'  => 'switch',
					'sdt'   => true,
					'desc'  => __( 'Semi-transparent frosted-glass effect for the header nav; falls back to a solid color when the browser does not support backdrop-filter', 'jinyu' ),
				],

				[
					'type'  => 'subhead',
					'title' => __( 'Fonts', 'jinyu' ),
				],
				/* ─── 字体 ─── */
				[
					'id'      => 'content_font',
					'title'   => __( 'Body font', 'jinyu' ),
					'type'    => 'select',
					'sdt'     => 'system',
					'options' => [
						[
							'label' => __( 'System sans-serif (default)', 'jinyu' ),
							'value' => 'system',
						],
						[
							'label' => __( 'Serif (SimSun)', 'jinyu' ),
							'value' => 'serif',
						],
						[
							'label' => __( 'Monospace', 'jinyu' ),
							'value' => 'mono',
						],
						[
							'label' => __( 'Rounded (YouYuan / Microsoft YaHei)', 'jinyu' ),
							'value' => 'round',
						],
					],
					'desc'    => __( 'Overrides the global font stack for content and UI', 'jinyu' ),
				],
			],
		];
	}
}
