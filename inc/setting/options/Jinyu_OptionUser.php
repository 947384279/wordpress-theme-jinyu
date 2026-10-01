<?php
/**
 * 设置项分组：用户
 *
 * @package         WordPress
 * @subpackage      Jinyu
 */



namespace Jinyu\Theme\setting\options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Jinyu_OptionUser extends Jinyu_BaseOptionItem {

	/**
	 * Get_fields()
	 *
	 * @return array 返回值
	 */
	public function get_fields(): array {
		return [
			'key'    => 'user',
			'title'  => __( 'Users & Login', 'jinyu' ),
			'desc'   => __( 'Comment avatars, UA badges, login / registration, and user interactions.', 'jinyu' ),
			'icon'   => 'fa-solid fa-user-shield',
			'fields' => [
				[
					'id'    => 'user_center_enable',
					'title' => __( 'Enable user center', 'jinyu' ),
					'type'  => 'switch',
					'sdt'   => true,
					'desc'  => __( 'When off, login / registration entries are hidden', 'jinyu' ),
				],
				[
					'id'      => 'user_center_page',
					'title'   => __( 'User center page', 'jinyu' ),
					'type'    => 'select',
					'sdt'     => '',
					'options' => $this->all_pages(),
					'desc'    => __( 'Select a "User Center" page template; if none is selected, redirects to the admin profile page', 'jinyu' ),
				],

				[
					'id'    => 'login_logo',
					'title' => __( 'Login page logo', 'jinyu' ),
					'type'  => 'upload',
					'sdt'   => '',
					'desc'  => __( 'Custom logo image for the WP login page', 'jinyu' ),
				],
				[
					'id'    => 'login_bg',
					'title' => __( 'Login page background image', 'jinyu' ),
					'type'  => 'upload',
					'sdt'   => '',
					'desc'  => __( 'Custom background image for the WP login page', 'jinyu' ),
				],

				[
					'id'      => 'captcha_policy',
					'title'   => __( 'CAPTCHA policy', 'jinyu' ),
					'type'    => 'select',
					'sdt'     => 'smart',
					'desc'    => __( 'Controls CAPTCHA for login / registration / password reset / friend link application: Smart = always required except for login (registration, reset, friend link application), where it is required only after 3 failed attempts (recommended); Always = required in all cases; Off = never required.', 'jinyu' ),
					'options' => [
						[
							'label' => __( 'Smart (recommended)', 'jinyu' ),
							'value' => 'smart',
						],
						[
							'label' => __( 'Always required', 'jinyu' ),
							'value' => 'always',
						],
						[
							'label' => __( 'Close', 'jinyu' ),
							'value' => 'off',
						],
					],
				],
				[
					'id'    => 'reg_notify_admin',
					'title' => __( 'Notify the admin on registration', 'jinyu' ),
					'type'  => 'switch',
					'sdt'   => false,
				],

				[
					'id'    => 'author_box_enable',
					'title' => __( 'Author card at the end of posts', 'jinyu' ),
					'type'  => 'switch',
					'sdt'   => true,
					'desc'  => __( 'Show the author\'s avatar, bio, and post / comment counts at the bottom of posts', 'jinyu' ),
				],
				[
					'id'      => 'comment_avatar_src',
					'title'   => __( 'Avatar source', 'jinyu' ),
					'type'    => 'select',
					'sdt'     => 'gravatar',
					'options' => [
						[
							'label' => __( 'Gravatar (default)', 'jinyu' ),
							'value' => 'gravatar',
						],
						[
							'label' => __( 'Cravatar (China)', 'jinyu' ),
							'value' => 'cravatar',
						],
						[
							'label' => __( 'WeAvatar (China)', 'jinyu' ),
							'value' => 'weavatar',
						],
						[
							'label' => __( 'V2EX (China)', 'jinyu' ),
							'value' => 'v2ex',
						],
						[
							'label' => __( 'Loli (China)', 'jinyu' ),
							'value' => 'loli',
						],
						[
							'label' => __( 'WebP.se (China)', 'jinyu' ),
							'value' => 'webpse',
						],
						[
							'label' => __( 'Letter avatar placeholder', 'jinyu' ),
							'value' => 'letter',
						],
					],
					'desc'    => __( 'China-based sources (Cravatar / WeAvatar / V2EX / Loli / WebP.se, etc.) are Gravatar-compatible and faster in China, for both comment and author avatars; letter mode needs no avatar server and works offline', 'jinyu' ),
				],

				[
					'id'    => 'user_can_submit',
					'title' => __( 'Allow front-end post submission', 'jinyu' ),
					'type'  => 'switch',
					'sdt'   => false,
					'desc'  => __( 'When enabled, logged-in users can submit posts in the user center; submissions enter the review queue', 'jinyu' ),
				],

				// 第三方登录（QQ / GitHub / Gitee / Apple）逻辑已迁至「金玉增强插件」，.
				// 配置入口在 WP 后台「设置 → 金玉社交登录」。此处不再保留主题侧开关.
			],
		];
	}
}
