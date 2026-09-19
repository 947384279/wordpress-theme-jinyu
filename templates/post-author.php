<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * 文章页文末作者信息卡（常显，不依赖评论数）。
 * 复用 inc/fun/comment.php 的 jinyu_author_box()。
 */
if (!jinyu_is_checked('author_box_enable')) return;
echo jinyu_author_box();
