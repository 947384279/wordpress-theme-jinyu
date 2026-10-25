<?php
/**
 * 设置项分组：基础
 *
 * @package         WordPress
 * @subpackage      Jinyu
 */



namespace Jinyu\Theme\setting\options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Jinyu_OptionBasic extends Jinyu_BaseOptionItem {

	/**
	 * Get_fields()
	 *
	 * @return array 返回值
	 */
	public function get_fields(): array {
		return [
			'key'    => 'basic',
			'title'  => __( 'Basic Settings', 'jinyu' ),
			'desc'   => __( 'Site info, logo, top notice, footer copyright, and post donation.', 'jinyu' ),
			'icon'   => 'fa-solid fa-gear',
			'fields' => [
				[
					'id'    => 'web_title',
					'title' => __( 'Site Title', 'jinyu' ),
					'type'  => 'string',
					'sdt'   => '',
					'desc'  => __( 'Leave empty to use the WordPress site title', 'jinyu' ),
				],
				[
					'id'    => 'web_logo',
					'title' => __( 'Site Logo (light mode)', 'jinyu' ),
					'type'  => 'upload',
					'sdt'   => '',
					'desc'  => __( 'A transparent PNG is recommended. Dark mode automatically switches to the "Dark mode" logo below; if not set, this image is reused', 'jinyu' ),
				],
				[
				'id'    => 'web_logo_dark',
				'title' => __( 'Site Logo (dark mode)', 'jinyu' ),
				'type'  => 'upload',
				'sdt'   => '',
				'desc'  => __( 'Leave empty to reuse the light-mode logo in dark mode', 'jinyu' ),
			],
			// 总开关：独立占行（标题在左、开关在右），控制公告条整体显隐；关闭只是临时隐藏，内容保留。
			[
				'id'    => 'top_notice_on',
				'title' => __( 'Show notice bar', 'jinyu' ),
				'type'  => 'switch',
				'sdt'   => false,
			],
			[
				'id'    => 'top_notice',
				'title' => __( 'Top Notice', 'jinyu' ),
				'type'  => 'textarea',
				'sdt'   => '',
				'desc'  => __( 'One notice per line. Multiple notices will scroll vertically when scrolling is enabled. Simple HTML (e.g. <a>, <strong>) is supported.', 'jinyu' ),
				'html'  => true,
			],
			// 滚动显示：多条公告时垂直上下轮播（prefers-reduced-motion 下自动关闭）。
			// 作为「显示公告条」的子选项，用带文字的复选框呈现（区别于主开关形态），仅在公告条显示时可见。
			[
				'id'        => 'top_notice_scroll',
				'title'     => __( 'Scrolling notice', 'jinyu' ),
				'type'      => 'checkbox',
				'sdt'       => false,
				'showRefId' => 'top_notice_on',
				'desc'      => __( 'When enabled and more than one notice is entered, notices scroll vertically in a loop. Only available when the notice bar is shown.', 'jinyu' ),
			],
			[
				'id'    => 'single_copyright',
				'title' => __( 'Copyright notice at the end of posts', 'jinyu' ),
					'type'  => 'textarea',
					'sdt'   => __( "Article link: {url}\nPlease credit the source when reposting: {title} (by {author})", 'jinyu' ),
					'desc'  => __( 'Leave empty to use the default notice. Placeholders supported: {url} post link, {title} title, {author} author, {date} date; simple HTML (e.g. links) is supported', 'jinyu' ),
					'html'  => true,
				],
				[
					'id'    => 'reward_title',
					'title' => __( 'Donation title', 'jinyu' ),
					'type'  => 'string',
					'sdt'   => __( 'Tip the author', 'jinyu' ),
				],
				[
					'id'    => 'reward_text',
					'title' => __( 'Donation message', 'jinyu' ),
					'type'  => 'textarea',
					'sdt'   => __( 'If you found this post helpful, feel free to support the author with a donation!', 'jinyu' ),
				],
				[
					'id'    => 'reward_wechat',
					'title' => __( 'WeChat Pay QR code', 'jinyu' ),
					'type'  => 'upload',
					'sdt'   => '',
					'desc'  => __( 'Upload a WeChat Pay QR code image; leave empty to hide this channel', 'jinyu' ),
				],
				[
					'id'    => 'reward_alipay',
					'title' => __( 'Alipay QR code', 'jinyu' ),
					'type'  => 'upload',
					'sdt'   => '',
					'desc'  => __( 'Upload an Alipay QR code image; leave empty to hide this channel', 'jinyu' ),
				],
			],
		];
	}
}
