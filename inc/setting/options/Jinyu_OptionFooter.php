<?php
/**
 * 设置项分组：页脚
 *
 * @package         WordPress
 * @subpackage      Jinyu
 */



namespace Jinyu\Theme\setting\options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Jinyu_OptionFooter extends Jinyu_BaseOptionItem {

	/**
	 * Get_fields()
	 *
	 * @return array 返回值
	 */
	public function get_fields(): array {
		return [
			'key'    => 'footer',
			'title'  => __( 'Footer Settings', 'jinyu' ),
			'desc'   => __( 'Footer columns, social links, and copyright / ICP info.', 'jinyu' ),
			'icon'   => 'fa-solid fa-copyright',
			'fields' => [
				[
					'type'  => 'subhead',
					'title' => __( 'About This Site', 'jinyu' ),
				],
				[
					'id'    => 'footer_about_title',
					'title' => __( 'About section title', 'jinyu' ),
					'type'  => 'text',
					'sdt'   => __( 'About This Site', 'jinyu' ),
				],
				[
					'id'          => 'footer_about',
					'title'       => __( 'About this site text', 'jinyu' ),
					'type'        => 'textarea',
					'sdt'         => __( 'Welcome to this site! Here we share …… (introduce your site\'s focus, features, and update direction in a sentence or two)', 'jinyu' ),
					'placeholder' => __( 'Introduce your site in one sentence; leave empty to show the site tagline', 'jinyu' ),
					'desc'        => __( 'Leave empty to use the site tagline. Simple HTML (links / bold, etc.) is supported', 'jinyu' ),
					'html'        => true,
				],

				[
					'type'  => 'subhead',
					'title' => __( 'Friend Links', 'jinyu' ),
				],
				[
					'id'          => 'flink_apply_url',
					'title'       => __( 'Friend link application URL', 'jinyu' ),
					'type'        => 'text',
					'sdt'         => '',
					'placeholder' => __( 'e.g. /friend-link or https://xxx.com/apply', 'jinyu' ),
					'desc'        => __( 'Leave empty to point automatically to a page using the "Apply for friend link" template (create a page → choose the "Apply for friend link" template on the right); if none exists, the entry is hidden. A manually filled URL overrides auto-detection', 'jinyu' ),
				],
				[
					'id'    => 'flink_apply_form',
					'title' => __( 'Enable front-end application form', 'jinyu' ),
					'type'  => 'switch',
					'sdt'   => 1,
					'desc'  => __( 'When enabled, the application page shows an online form; submissions enter the "Friend Links" review queue and go live once approved. When off, only the application requirements and the site owner\'s email are shown', 'jinyu' ),
				],
				[
					'id'    => 'flink_apply_notify',
					'title' => __( 'Email notification for new applications', 'jinyu' ),
					'type'  => 'switch',
					'sdt'   => 0,
					'desc'  => __( 'Send an email to the site owner when a friend link application is submitted (depends on the site\'s mail configuration)', 'jinyu' ),
				],
				[
					'id'          => 'flink_apply_rules',
					'title'       => __( 'Friend link application requirements', 'jinyu' ),
					'type'        => 'textarea',
					'sdt'         => __( "Healthy, original content with no illegal or gray-area material\nThe site must be accessible — not a scraper or a pure link directory\nA link to this site must be added and visible on the homepage\nThe site must have a reasonable amount of content, not an empty shell", 'jinyu' ),
					'placeholder' => __( 'One requirement per line', 'jinyu' ),
					'desc'        => __( 'One per line, shown in the "Requirements" card on the application page; leave empty to hide the card', 'jinyu' ),
				],

				[
					'type'  => 'subhead',
					'title' => __( 'Copyright & ICP', 'jinyu' ),
				],
				[
					'id'          => 'footer_copyright',
					'title'       => __( 'Copyright text', 'jinyu' ),
					'type'        => 'textarea',
					'sdt'         => __( '© {year} {name}. All rights reserved', 'jinyu' ),
					'placeholder' => __( 'e.g. © {year} {name}. All rights reserved. Leave empty to use the default copyright', 'jinyu' ),
					'desc'        => __( 'Leave empty to use the default copyright. Placeholders supported: {year} year, {name} site name; simple HTML (e.g. links) is supported', 'jinyu' ),
					'html'        => true,
				],

				[
					'id'    => 'footer_social',
					'title' => __( 'Social accounts', 'jinyu' ),
					'type'  => 'textarea',
					'sdt'   => '',
					'desc'  => __( 'One per line, format: platform|link. Example: GitHub|https://github.com/xxx. Icons are auto-detected for: GitHub, Gitee, Weibo, WeChat, QQ, Email, Telegram, X, Twitter, Zhihu, Bilibili, RSS, Douyin, Douban, Toutiao', 'jinyu' ),
				],

				[
					'id'    => 'header_social_enable',
					'title' => __( 'Also show social icons in the header', 'jinyu' ),
					'type'  => 'switch',
					'sdt'   => false,
					'desc'  => __( 'Show the social accounts above in the header tools area (the header and footer share the same social list)', 'jinyu' ),
				],

				[
					'id'          => 'company_icp',
					'title'       => __( 'ICP filing number', 'jinyu' ),
					'type'        => 'text',
					'sdt'         => '',
					'placeholder' => __( '如 京ICP备12345678号', 'jinyu' ),
					'desc'        => __( 'Shown in the footer once filled', 'jinyu' ),
				],
			],
		];
	}
}
