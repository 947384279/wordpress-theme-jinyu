<?php
/**
 * 设置项分组：关于
 *
 * @package         WordPress
 * @subpackage      Jinyu
 */



namespace Jinyu\Theme\setting\options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Jinyu_OptionAbout extends Jinyu_BaseOptionItem {

	/**
	 * Get_fields()
	 *
	 * @return array 返回值
	 */
	public function get_fields(): array {
		return [
			'key'    => 'about',
			'title'  => __( '关于', 'jinyu' ),
			'desc'   => __( '主题版本、更新服务器与检查更新。', 'jinyu' ),
			'icon'   => 'fa-solid fa-info-circle',
			'fields' => [
				[
					'id'    => 'version_info',
					'title' => __( '当前版本', 'jinyu' ),
					'type'  => 'info',
					/* translators: %s: 占位符 */
					'desc'  => sprintf( __( '金玉主题 v%s · PHP 8.0+ · WordPress 6.0+', 'jinyu' ), JINYU_CUR_VER ),
				],
				[
					'id'    => 'update_check',
					'title' => __( '检查更新', 'jinyu' ),
					'type'  => 'update_check',
				],
			],
		];
	}
}
