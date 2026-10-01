<?php
/**
 * 设置项基类（Jinyu_BaseOptionItem）
 *
 * @package         WordPress
 * @subpackage      Jinyu
 */



namespace Jinyu\Theme\setting\options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 设置项抽象基类
 *
 * 支持字段类型: string | number | select | switch | color | textarea | upload | radio | slider | info
 */
abstract class Jinyu_BaseOptionItem {

	/**
	 * 返回该设置分组的完整定义数组
	 * 结构: ['key'=>, 'title'=>, 'icon'=>, 'fields'=>[...]]
	 */
	abstract public function get_fields(): array;

	/**
	 * All_pages()
	 *
	 * @return array 返回值
	 */
	protected function all_pages(): array {
		$list = [
			[
				'label' => __( 'None', 'jinyu' ),
				'value' => '',
			],
		];
		foreach ( get_pages( 'sort_column=post_parent,menu_order' ) as $p ) {
			$list[] = [
				'label' => $p->post_title,
				'value' => $p->ID,
			];
		}
		return $list;
	}
}
