<?php
/**
 * 设置项分组：评论
 *
 * @package         WordPress
 * @subpackage      Jinyu
 */



namespace Jinyu\Theme\setting\options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Jinyu_OptionComment extends Jinyu_BaseOptionItem {

	/**
	 * Get_fields()
	 *
	 * @return array 返回值
	 */
	public function get_fields(): array {
		return [
			'key'    => 'comment',
			'title'  => __( 'Comments & Interaction', 'jinyu' ),
			'desc'   => __( 'Comment toggles, pagination, auto-closing comments on old posts, emoji, and spam keywords.', 'jinyu' ),
			'icon'   => 'fa-solid fa-comments',
			'fields' => [
				[
					'id'    => 'close_post_comment',
					'title' => __( 'Disable comments site-wide', 'jinyu' ),
					'type'  => 'switch',
					'sdt'   => false,
				],
				[
					'id'    => 'comment_pages_enable',
					'title' => __( 'Paginate comments', 'jinyu' ),
					'type'  => 'switch',
					'sdt'   => true,
					'desc'  => __( 'Split comments into pages when there are many, to avoid overly long pages', 'jinyu' ),
				],
				[
					'id'    => 'comments_per_page',
					'title' => __( 'Comments per page', 'jinyu' ),
					'type'  => 'number',
					'sdt'   => 10,
					'min'   => 1,
					'max'   => 50,
				],
				[
					'id'    => 'close_comments_old',
					'title' => __( 'Auto-close comments on old posts', 'jinyu' ),
					'type'  => 'switch',
					'sdt'   => false,
					'desc'  => __( 'Automatically close comments on posts older than the specified number of days', 'jinyu' ),
				],
				[
					'id'    => 'close_comments_days',
					'title' => __( 'Comment-closing threshold (days)', 'jinyu' ),
					'type'  => 'number',
					'sdt'   => 30,
					'min'   => 1,
					'unit'  => __( 'days', 'jinyu' ),
				],
				[
					'id'    => 'comment_smiley',
					'title' => __( 'Comment emoji picker', 'jinyu' ),
					'type'  => 'switch',
					'sdt'   => true,
					'desc'  => __( 'Provide an emoji panel in the comment box; click to insert emoji', 'jinyu' ),
				],
				[
					'id'    => 'comment_show_ua',
					'title' => __( 'Show UA in comments', 'jinyu' ),
					'type'  => 'switch',
					'sdt'   => true,
					'desc'  => __( 'Show the commenter\'s browser and OS in comments (parsed from User-Agent)', 'jinyu' ),
				],
				[
					'id'    => 'anti_spam_words',
					'title' => __( 'Spam comment keywords', 'jinyu' ),
					'type'  => 'textarea',
					'sdt'   => defined( 'JINYU_DEFAULT_SPAM_WORDS' ) ? JINYU_DEFAULT_SPAM_WORDS : __( '彩票,色情,赌博,代写,刷量,贷款,发票,办证,加微信,返利,兼职,代运营', 'jinyu' ),
					'desc'  => __( 'Comma-separated. If a comment contains any of these words, it is marked as spam. A list of common spam words is built in; you can add or remove entries here', 'jinyu' ),
				],
			],
		];
	}
}
