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
		$fields = [];
		// w.org 禁止主题提供任意代码注入能力，发行变体中剔除这 4 个字段（自托管版保留）。
		if ( ! \jinyu_is_wporg() ) {
			$fields[] = [
				'id'    => 'css_code_head',
				'title' => __( '头部注入 CSS', 'jinyu' ),
				'type'  => 'textarea',
				'code'  => true,
				'sdt'   => '',
				'desc'  => __( '插入 wp_head 中', 'jinyu' ),
			];
			$fields[] = [
				'id'    => 'js_code_head',
				'title' => __( '头部注入 JS', 'jinyu' ),
				'type'  => 'textarea',
				'code'  => true,
				'sdt'   => '',
			];
			$fields[] = [
				'id'    => 'css_code_foot',
				'title' => __( '底部注入 CSS', 'jinyu' ),
				'type'  => 'textarea',
				'code'  => true,
				'sdt'   => '',
			];
			$fields[] = [
				'id'    => 'js_code_foot',
				'title' => __( '底部注入 JS', 'jinyu' ),
				'type'  => 'textarea',
				'code'  => true,
				'sdt'   => '',
			];
		}
		return [
			'key'    => 'code',
			'title'  => __( '自定义代码', 'jinyu' ),
			'icon'   => 'fa-solid fa-code',
			'desc'   => __( '头部 / 底部注入的自定义 CSS、JS 代码。', 'jinyu' ),
			'fields' => $fields,
		];
	}
}
