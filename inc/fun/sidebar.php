<?php
/**
 * 侧栏区域注册
 *
 * @package         WordPress
 * @subpackage      Jinyu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'widgets_init', 'jinyu_register_sidebars' );
/**
 * Jinyu_register_sidebars()
 *
 * @return void 返回值
 */
function jinyu_register_sidebars() {
	register_sidebar(
		[
			'name'          => __( 'Primary Sidebar', 'jinyu' ),
			'id'            => 'sidebar-main',
			'description'   => __( 'Right side of posts, category pages, and the homepage', 'jinyu' ),
			'before_widget' => '<div id="%1$s" class="jinyu-widget %2$s">',
			'after_widget'  => '</div>',
			'before_title'  => '<div class="jinyu-widget-title">',
			'after_title'   => '</div>',
		]
	);

	register_sidebar(
		[
			'name'          => __( 'Footer Widgets', 'jinyu' ),
			'id'            => 'sidebar-footer',
			'description'   => __( 'Above the footer. 3-4 widgets recommended', 'jinyu' ),
			'before_widget' => '<div id="%1$s" class="jinyu-footer-widget %2$s">',
			'after_widget'  => '</div>',
			'before_title'  => '<div class="jinyu-footer-title">',
			'after_title'   => '</div>',
		]
	);

	register_sidebar(
		[
			'name'          => __( 'Below Post Content', 'jinyu' ),
			'id'            => 'sidebar-single-bottom',
			'description'   => __( 'Reading recommendations below single posts', 'jinyu' ),
			// 复用侧栏卡片样式（.jinyu-widget 在 common.less 定义），容器只负责栅格排布.
			'before_widget' => '<div id="%1$s" class="jinyu-widget jinyu-single-widget %2$s">',
			'after_widget'  => '</div>',
			'before_title'  => '<div class="jinyu-widget-title">',
			'after_title'   => '</div>',
		]
	);
}
