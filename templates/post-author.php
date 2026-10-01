<?php
/**
 * 文章作者信息模板片段
 *
 * @package         WordPress
 * @subpackage      Jinyu
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * 文章页文末作者信息卡（常显，不依赖评论数）。
 * 复用 inc/fun/comment.php 的 jinyu_author_box()。
 */
if ( ! jinyu_is_checked( 'author_box_enable' ) ) {
	return;
}
echo jinyu_author_box(); /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 输出经 esc_html/esc_attr/wp_kses 处理或为核心传入值/整型，WPCS 追不到集中式委托故误报 */
