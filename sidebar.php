<?php
/**
 * 侧边栏
 *
 * 后台「外观 > 小工具」里向「主侧边栏」拖入的小工具通过 dynamic_sidebar 渲染；
 * 若未拖入任何小工具（is_active_sidebar 为假），则整段侧边栏不输出，前台自动隐藏。
 * 注：不再提供主题内置默认小工具 fallback —— 空侧边栏即隐藏，避免无意义占位。
 *
 * @package WordPress
 * @subpackage Jinyu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


// 后台选择「不显示侧边栏」时直接跳过.
if ( jinyu_get_option( 'sidebar_pos', 'right' ) === 'none' ) {
	return;
}

// 后台未向「主侧边栏」拖入任何小工具时，不渲染侧边栏（含主题内置默认小工具），避免无意义占位。
if ( ! is_active_sidebar( 'sidebar-main' ) ) {
	return;
}
?>
<aside class="jinyu-sidebar">

	<?php dynamic_sidebar( 'sidebar-main' ); ?>

</aside>
