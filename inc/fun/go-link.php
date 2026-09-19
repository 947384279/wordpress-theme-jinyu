<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * 短链跳转（go.php 等价功能）
 *
 * 通过 rewrite 把 /go/?url=XXX 映射到本处理器，校验协议后 302 跳转。
 * 后台「扩展及兼容」开启 go_link_enable 后，正文外链自动改写为 /go/ 短链，
 * 便于统一跳转、点击统计与防泄漏。
 */

if (!defined('ABSPATH')) exit;

add_action('init', function () {
    add_rewrite_tag('%jy_go%', '1');
    add_rewrite_rule('^go/?$', 'index.php?jy_go=1', 'top');
});

add_filter('query_vars', function ($vars) {
    $vars[] = 'jy_go';
    return $vars;
});

// 主题切换时刷新重写规则，确保 /go/ 生效
add_action('after_switch_theme', 'flush_rewrite_rules');

/**
 * 短链签名。
 * /go/ 是公开端点，若不加签名，任何人都能拿本站域名做跳板跳去钓鱼站（开放重定向）。
 * 只有正文外链改写时由主题自己签发的 URL 才放行；签名用站点的 salt 做 HMAC，外部无法伪造。
 */
if (!function_exists('jinyu_go_sign')) {
    function jinyu_go_sign(string $url): string
    {
        return substr(hash_hmac('sha256', $url, wp_salt('nonce')), 0, 20);
    }
}

add_action('template_redirect', function () {
    if (get_query_var('jy_go') != 1) return;

    // 签名基于「原始 href」计算，故先取原始值校验，再做转义/协议白名单
    $url = isset($_GET['url']) ? (string) wp_unslash($_GET['url']) : '';
    $sig = isset($_GET['sig']) ? (string) wp_unslash($_GET['sig']) : '';

    if (!$url || !hash_equals(jinyu_go_sign($url), $sig)) {
        wp_safe_redirect(home_url(), 302); // 无签名 / 签名不符：外部伪造的跳转，回首页
        exit;
    }

    $url = esc_url_raw($url);

    // 仅允许 http/https，杜绝 javascript:/data: 等危险 scheme
    $scheme = wp_parse_url($url, PHP_URL_SCHEME);
    if (!$url || !in_array($scheme, ['http', 'https'], true)) {
        wp_safe_redirect(home_url(), 302);
        exit;
    }

    // 联盟点击追踪：记录目标域名 + 来源（受后台「联盟点击追踪」开关控制）
    if (jinyu_is_checked('track_clicks_enable')) {
        $host = wp_parse_url($url, PHP_URL_HOST);
        $referer = isset($_SERVER['HTTP_REFERER']) ? (string) $_SERVER['HTTP_REFERER'] : '';
        $post_id = 0;
        // 从 referer 推断来源文章（站内链接带 ?p= 或 /YYYY/MM/..）
        if (preg_match('/[?&]p=(\d+)/', $referer, $m)) {
            $post_id = (int) $m[1];
        } elseif (function_exists('url_to_postid')) {
            $ref_path = wp_parse_url($referer, PHP_URL_PATH);
            if ($ref_path && strpos($referer, home_url()) === 0) {
                $post_id = (int) url_to_postid($ref_path);
            }
        }
        jinyu_track_event('go', ['meta' => $host ?: '', 'post_id' => $post_id]);
    }

    wp_redirect($url, 302);
    exit;
});
