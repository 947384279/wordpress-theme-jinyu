<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 时光圈（说说 / 动态）自定义文章类型
 * 使「说说」成为独立数据流，
 * 可在后台「时光圈」菜单发布，前端由 pages/template-moments.php 以时间线展示。
 */

function jinyu_moments_init(): void
{
    $name = __('时光圈', JINYU);
    register_post_type('moments', [
        'labels' => [
            'name'               => $name,
            'singular_name'      => $name,
            'add_new'            => sprintf(__('发表%s', JINYU), $name),
            'add_new_item'       => sprintf(__('发表%s', JINYU), $name),
            'edit_item'          => sprintf(__('编辑%s', JINYU), $name),
            'new_item'           => sprintf(__('新%s', JINYU), $name),
            'view_item'          => sprintf(__('查看%s', JINYU), $name),
            'search_items'       => sprintf(__('搜索%s', JINYU), $name),
            'not_found'          => sprintf(__('暂无%s', JINYU), $name),
            'not_found_in_trash' => sprintf(__('没有已遗弃的%s', JINYU), $name),
            'menu_name'          => $name,
        ],
        'public'              => true,
        'publicly_queryable'  => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'query_var'           => true,
        'rewrite'             => ['slug' => 'moments', 'with_front' => false],
        'capability_type'     => 'post',
        'has_archive'         => true,
        'hierarchical'        => false,
        'menu_icon'           => 'dashicons-format-status',
        'supports'            => ['title', 'editor', 'author', 'comments', 'thumbnail'],
        'show_in_rest'        => true,
    ]);

    // 单条说说使用 /moments/{id}.html 的固定链接
    add_rewrite_rule(
        'moments/([0-9]+)\.html$',
        'index.php?post_type=moments&p=$matches[1]',
        'top'
    );
}
add_action('init', 'jinyu_moments_init');

function jinyu_moments_link(string $link, \WP_Post $post): string
{
    if ($post->post_type === 'moments') {
        return home_url('moments/' . $post->ID . '.html');
    }
    return $link;
}
add_filter('post_type_link', 'jinyu_moments_link', 1, 2);

// 主题启用 / 切换时刷新重写规则，使 /moments/ 归档与 .html 单页生效
function jinyu_moments_flush(): void
{
    jinyu_moments_init();
    flush_rewrite_rules();
}
add_action('after_switch_theme', 'jinyu_moments_flush');
