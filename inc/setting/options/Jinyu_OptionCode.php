<?php
/**
 * 设置项分组：代码
 *
 * @package         WordPress
 * @subpackage      Jinyu
 */



namespace Jinyu\Theme\setting\options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Jinyu_OptionCode extends Jinyu_BaseOptionItem {

	/**
	 * Get_fields()
	 *
	 * @return array 返回值
	 */
	public function get_fields(): array {
		return [
			'key'    => 'code',
			'title'  => __( '自定义代码', 'jinyu' ),
			'icon'   => 'fa-solid fa-code',
			'desc'   => __( '头部 / 底部注入的自定义 CSS、JS 代码。', 'jinyu' ),
			'fields' => [
				/* ─── 注入代码 ─── */
				[
					'id'    => 'css_code_head',
					'title' => __( '头部注入 CSS', 'jinyu' ),
					'type'  => 'textarea',
					'code'  => true,
					'sdt'   => '',
					'desc'  => __( '插入 wp_head 中', 'jinyu' ),
				],
				[
					'id'    => 'js_code_head',
					'title' => __( '头部注入 JS', 'jinyu' ),
					'type'  => 'textarea',
					'code'  => true,
					'sdt'   => '',
				],
				[
					'id'    => 'css_code_foot',
					'title' => __( '底部注入 CSS', 'jinyu' ),
					'type'  => 'textarea',
					'code'  => true,
					'sdt'   => '',
				],
				[
					'id'    => 'js_code_foot',
					'title' => __( '底部注入 JS', 'jinyu' ),
					'type'  => 'textarea',
					'code'  => true,
					'sdt'   => '',
				],
			],
		];
	}
}
