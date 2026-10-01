<?php
/**
 * 无内容时的占位模板片段
 *
 * @package         WordPress
 * @subpackage      Jinyu
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="jinyu-empty">
	<h2><?php esc_html_e( 'Nothing here yet', 'jinyu' ); ?></h2>
	<p><?php esc_html_e( 'Sorry, no related posts found.', 'jinyu' ); ?></p>
</div>
