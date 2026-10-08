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
			'title'  => __( 'About', 'jinyu' ),
			'desc'   => __( 'Theme version, update server, and update check.', 'jinyu' ),
			'icon'   => 'fa-solid fa-info-circle',
			'fields' => [
				[
					'id'    => 'version_info',
					'title' => __( 'Current version', 'jinyu' ),
					'type'  => 'info',
					/* translators: %s: 占位符 */
					'desc'  => sprintf( __( 'Jinyu theme v%s · PHP 8.0+ · WordPress 6.0+', 'jinyu' ), JINYU_CUR_VER ),
				],
			],
		];
	}
}
