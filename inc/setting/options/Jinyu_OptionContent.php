<?php
/**
 * 设置项分组：内容
 *
 * @package         WordPress
 * @subpackage      Jinyu
 */



namespace Jinyu\Theme\setting\options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Jinyu_OptionContent extends Jinyu_BaseOptionItem {

	/**
	 * Get_fields()
	 *
	 * @return array 返回值
	 */
	public function get_fields(): array {
		return [
			'key'    => 'content',
			'title'  => __( 'Content Enhancements', 'jinyu' ),
			'icon'   => 'fa-solid fa-wand-magic-sparkles',
			'desc'   => __( 'Reading enhancements for single posts (TOC / breadcrumbs / progress bar, etc.) and post-bottom module toggles.', 'jinyu' ),
			'fields' => [
				[
					'type'  => 'subhead',
					'title' => __( 'Reading Enhancements', 'jinyu' ),
				],
				/* ─── 阅读增强 ─── */
				[
					'id'    => 'toc_enable',
					'title' => __( 'Post table of contents (TOC)', 'jinyu' ),
					'type'  => 'switch',
					'sdt'   => true,
					'desc'  => __( 'Automatically build a TOC from H2/H3/H4 in the content with scroll highlighting', 'jinyu' ),
				],
				[
					'id'      => 'toc_depth',
					'title'   => __( 'TOC heading levels', 'jinyu' ),
					'type'    => 'select',
					'sdt'     => '3',
					'options' => [
						[
							'label' => __( 'H2 only', 'jinyu' ),
							'value' => '2',
						],
						[
							'label' => __( 'H2–H3', 'jinyu' ),
							'value' => '3',
						],
						[
							'label' => __( 'H2–H4', 'jinyu' ),
							'value' => '4',
						],
					],
				],
				[
					'id'    => 'breadcrumb_enable',
					'title' => __( 'Breadcrumbs', 'jinyu' ),
					'type'  => 'switch',
					'sdt'   => true,
				],
				[
					'id'    => 'back_top_enable',
					'title' => __( 'Back-to-top button', 'jinyu' ),
					'type'  => 'switch',
					'sdt'   => true,
				],
				[
					'id'    => 'code_highlight_enable',
					'title' => __( 'Syntax highlighting', 'jinyu' ),
					'type'  => 'switch',
					'sdt'   => true,
				],
				[
					'id'    => 'read_progress_enable',
					'title' => __( 'Reading progress bar', 'jinyu' ),
					'type'  => 'switch',
					'sdt'   => true,
				],
				[
					'id'    => 'show_cover',
					'title' => __( 'Post cover image (global)', 'jinyu' ),
					'type'  => 'switch',
					'sdt'   => true,
					'desc'  => __( 'When off, featured images are hidden site-wide; single posts can still override with "Hide cover"', 'jinyu' ),
				],
				[
					'id'    => 'default_thumbnail',
					'title' => __( 'Default thumbnail', 'jinyu' ),
					'type'  => 'upload',
					'sdt'   => '',
					'desc'  => __( 'Fallback thumbnail when a post has no featured image and no images in the content (leave empty to use the theme\'s built-in "colorful cloud" gradient default; override here only when you want a different image)', 'jinyu' ),
				],
				[
					'id'    => 'excerpt_length',
					'title' => __( 'Excerpt length', 'jinyu' ),
					'type'  => 'number',
					'sdt'   => 120,
					'min'   => 20,
					'max'   => 300,
					'unit'  => __( 'words', 'jinyu' ),
					'desc'  => __( 'Auto excerpt length on list / archive pages', 'jinyu' ),
				],

				[
					'type'  => 'subhead',
					'title' => __( 'List card meta line', 'jinyu' ),
				],
				/* ─── 列表卡片信息行 ─── */
				[
					'id'    => 'card_series_enable',
					'title' => __( 'Show series in cards', 'jinyu' ),
					'type'  => 'switch',
					'sdt'   => true,
					'desc'  => __( 'The meta line shows "Series name · Part N/M" to guide series reading; posts not in a series show nothing', 'jinyu' ),
				],
				[
					'id'    => 'card_updated_enable',
					'title' => __( 'Show last updated in cards', 'jinyu' ),
					'type'  => 'switch',
					'sdt'   => true,
					'desc'  => __( 'Shows "Updated on X" only when a post is modified more than 3 days after publication, to avoid noise from minor edits', 'jinyu' ),
				],

				[
					'type'  => 'subhead',
					'title' => __( 'Post-bottom modules', 'jinyu' ),
				],
				/* ─── 文章底部模块 ─── */
				[
					'id'    => 'related_enable',
					'title' => __( 'Related Posts', 'jinyu' ),
					'type'  => 'switch',
					'sdt'   => true,
				],
				[
					'id'    => 'related_num',
					'title' => __( 'Number of related posts', 'jinyu' ),
					'type'  => 'number',
					'sdt'   => 4,
					'min'   => 1,
					'max'   => 12,
				],
				[
					'id'      => 'related_type',
					'title'   => __( 'Related posts based on', 'jinyu' ),
					'type'    => 'select',
					'sdt'     => 'tags',
					'options' => [
						[
							'label' => __( 'Same tags', 'jinyu' ),
							'value' => 'tags',
						],
						[
							'label' => __( 'Same categories', 'jinyu' ),
							'value' => 'cats',
						],
						[
							'label' => __( 'Random', 'jinyu' ),
							'value' => 'random',
						],
					],
				],
				[
					'id'    => 'post_nav_enable',
					'title' => __( 'Previous / Next', 'jinyu' ),
					'type'  => 'switch',
					'sdt'   => true,
				],

				[
					'type'  => 'subhead',
					'title' => __( 'Post action bar', 'jinyu' ),
				],
				/* ─── 文章操作栏 ─── */
				[
					'id'    => 'like_enable',
					'title' => __( 'Like', 'jinyu' ),
					'type'  => 'switch',
					'sdt'   => true,
				],
				[
					'id'    => 'fav_enable',
					'title' => __( 'Favorite', 'jinyu' ),
					'type'  => 'switch',
					'sdt'   => true,
				],
				[
					'id'    => 'share_enable',
					'title' => __( 'Share', 'jinyu' ),
					'type'  => 'switch',
					'sdt'   => true,
				],
				[
					'id'      => 'share_channels',
					'title'   => __( 'Share channels', 'jinyu' ),
					'type'    => 'checkboxes',
					'sdt'     => 'weibo,qq,qzone,wechat,link',
					'options' => [
						[
							'label' => __( 'Weibo', 'jinyu' ),
							'value' => 'weibo',
						],
						[
							'label' => __( 'QQ', 'jinyu' ),
							'value' => 'qq',
						],
						[
							'label' => __( 'Qzone', 'jinyu' ),
							'value' => 'qzone',
						],
						[
							'label' => __( 'WeChat', 'jinyu' ),
							'value' => 'wechat',
						],
						[
							'label' => __( 'Copy link', 'jinyu' ),
							'value' => 'link',
						],
					],
					'desc'    => __( 'Multiple choices; leave empty to show all', 'jinyu' ),
				],
				[
					'id'    => 'poster_enable',
					'title' => __( 'Poster generation', 'jinyu' ),
					'type'  => 'switch',
					'sdt'   => true,
				],
				[
					'id'    => 'qr_enable',
					'title' => __( 'QR Code', 'jinyu' ),
					'type'  => 'switch',
					'sdt'   => false,
				],
			],
		];
	}
}
