<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 百度主动推送
 *
 * 用户在后台「扩展及兼容」面板填写接口调用地址（含 token），
 * 发布 / 更新文章时主动将 URL 推送给百度站长平台。
 * 已成功推送过的文章写入 post meta 标记，避免重复推送浪费配额。
 */

function jinyu_baidu_submit(int $post_id): void
{
    // 仅对「已发布」状态的「文章」类型推送（过滤自动保存、修订、页面等）
    if (get_post_type($post_id) !== 'post' || get_post_status($post_id) !== 'publish') {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    // 已成功推送过则跳过，避免重复消耗每日配额
    if (get_post_meta($post_id, 'jinyu_baidu_push_status', true) == 1) {
        return;
    }

    $api_url = jinyu_get_option('baidu_submit_token');
    if (empty($api_url)) {
        return;
    }

    $post_url = get_permalink($post_id);
    if (empty($post_url)) {
        return;
    }

    // 非阻塞触发：百度接口慢/不可达时不拖慢后台发布流程（发布卡顿是真实痛点）。
    // 无法读取响应，故乐观标记已推送，避免每次编辑文章都重复推送、浪费每日配额。
    wp_remote_post($api_url, [
        'timeout'   => 10,
        'blocking'  => false,
        'headers'   => ['Content-Type' => 'text/plain'],
        'body'      => $post_url,
        'sslverify' => (bool) apply_filters('jinyu_baidu_push_ssl_verify', true),
    ]);
    update_post_meta($post_id, 'jinyu_baidu_push_status', 1);
}

if (jinyu_is_checked('baidu_auto_submit')) {
    add_action('save_post', 'jinyu_baidu_submit', 10, 1);
}
