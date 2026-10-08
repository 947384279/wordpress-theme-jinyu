<?php
/**
 * 设置项分组：扩展
 *
 * @package         WordPress
 * @subpackage      Jinyu
 */



namespace Jinyu\Theme\setting\options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Jinyu_OptionExtend extends Jinyu_BaseOptionItem {

	/**
	 * Get_fields()
	 *
	 * @return array 返回值
	 */
	public function get_fields(): array {
		return [
			'key'    => 'extend',
			'title'  => __( 'Advanced Settings', 'jinyu' ),
			'desc'   => __( 'Third-party integrations, compatibility toggles, AI chat, theme updates, and other advanced configurations.', 'jinyu' ),
			'icon'   => 'fa-solid fa-puzzle-piece',
			'fields' => [
				[
					'id'    => 'enable_pjax',
					'title' => __( 'Enable PJAX navigation', 'jinyu' ),
					'type'  => 'switch',
					'sdt'   => false,
					'desc'  => __( 'Switch content via internal links without page reloads (experimental; test with a small traffic sample first)', 'jinyu' ),
				],
				[
					'id'    => 'live_ip_location',
					'title' => __( 'Sidebar visitor location lookup', 'jinyu' ),
					'type'  => 'switch',
					'sdt'   => false,
					'desc'  => __( '⚠️ Privacy note: when enabled, sidebar real-time data sends the visitor\'s IP to a third-party service (whois.pconline.com.cn) for location lookup. Off by default; only locally available IP/OS/browser info is shown. Results are cached for 12 hours; sending stops immediately when disabled', 'jinyu' ),
				],
			],
		];
	}
}
