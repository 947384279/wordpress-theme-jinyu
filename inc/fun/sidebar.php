<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action('widgets_init', 'jinyu_register_sidebars');
function jinyu_register_sidebars()
{
    register_sidebar([
        'name'          => __('主侧边栏', 'jinyu'),
        'id'            => 'sidebar-main',
        'description'   => __('文章页 / 分类页 / 首页右侧', 'jinyu'),
        'before_widget' => '<div id="%1$s" class="jinyu-widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<div class="jinyu-widget-title">',
        'after_title'   => '</div>',
    ]);

    register_sidebar([
        'name'          => __('底部小工具', 'jinyu'),
        'id'            => 'sidebar-footer',
        'description'   => __('页脚上方，建议 3-4 个', 'jinyu'),
        'before_widget' => '<div id="%1$s" class="jinyu-footer-widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<div class="jinyu-footer-title">',
        'after_title'   => '</div>',
    ]);

    register_sidebar([
        'name'          => __('文章底部', 'jinyu'),
        'id'            => 'sidebar-single-bottom',
        'description'   => __('单篇文章下方，阅读推荐', 'jinyu'),
        // 复用侧栏卡片样式（.jinyu-widget 在 common.less 定义），容器只负责栅格排布
        'before_widget' => '<div id="%1$s" class="jinyu-widget jinyu-single-widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<div class="jinyu-widget-title">',
        'after_title'   => '</div>',
    ]);
}