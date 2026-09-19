<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * 杂项 Ajax：随机文章跳转（手气不错）、动态 favicon 未读角标
 */

if (!defined('ABSPATH')) exit;

// 手气不错：返回一个随机文章 URL（游客/登录均可）
add_action('wp_ajax_jinyu_random_post', 'jinyu_random_post');
add_action('wp_ajax_nopriv_jinyu_random_post', 'jinyu_random_post');
function jinyu_random_post()
{
    jinyu_ajax_guard();
    // 随机偏移替代 orderby=rand：避免全表 filesort（单篇随机也要给整张表排序）。
    $published = (int) wp_count_posts('post')->publish;
    $offset    = $published > 1 ? mt_rand(0, $published - 1) : 0;
    $q = new WP_Query([
        'post_type'      => 'post',
        'posts_per_page' => 1,
        'offset'         => $offset,
        'orderby'        => 'ID',
        'no_found_rows'  => true,
        'post_status'    => 'publish',
    ]);
    if ($q->have_posts()) {
        $q->the_post();
        wp_send_json_success(['url' => get_permalink()]);
    }
    wp_send_json_error('empty');
}

// 动态 favicon 未读提示：仅对管理员返回待审评论数
add_action('wp_ajax_jinyu_favicon_count', 'jinyu_favicon_count');
function jinyu_favicon_count()
{
    jinyu_ajax_guard();
    if (!current_user_can('manage_options')) wp_send_json_error('no');
    $count = (int) wp_count_comments()->moderated;
    wp_send_json_success(['count' => $count]);
}
