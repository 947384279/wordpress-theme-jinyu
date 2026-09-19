<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 整页缓存（仅未登录访客的 GET 请求）
 * 内容更新时由 inc/fun/cache.php 的 jinyu_cache_flush() 自动失效。
 */

if (jinyu_is_checked('page_cache_enable')) {
    add_action('init', 'jinyu_page_cache_serve');
    add_action('template_redirect', 'jinyu_page_cache_capture');
}

function jinyu_page_cache_key(): string
{
    // 缓存页里内嵌了 wp_create_nonce('jinyu_front')，nonce 以 12h 为一个 tick 轮换。
    // 把当前 tick 并入 key：跨 tick 后旧缓存自然失效，绝不会把已过期的 nonce 发给访客
    // （否则点赞 / AI 对话 / 评论会被 check_ajax_referer 打回 -1）。
    $tick = function_exists('wp_nonce_tick') ? wp_nonce_tick() : (int) ceil(time() / (DAY_IN_SECONDS / 2));
    return jinyu_cache_key('page_' . $tick . '_' . md5($_SERVER['REQUEST_URI'] ?? '/'));
}

function jinyu_page_cache_serve(): void
{
    if (empty($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'GET') return;
    if (is_user_logged_in() || is_admin()) return;
    if (strpos($_SERVER['REQUEST_URI'] ?? '', 'wp-admin') !== false) return;
    if (strpos($_SERVER['REQUEST_URI'] ?? '', 'wp-login') !== false) return;

    $html = get_transient(jinyu_page_cache_key());
    if ($html !== false) {
        // 标记本次为缓存命中：性能采样（inc/fun/live.php）据此跳过，
        // 否则几毫秒的缓存响应会把「实时心跳」曲线压成一条直线。
        define('JINYU_CACHE_HIT', true);
        header('X-Jinyu-Cache: HIT');
        // 缓存命中时已在 init 阶段 echo+exit，template_redirect 不会触发，
        // 而来源统计(jinyu_track_visit_source)挂在该钩子上，故在此显式调用，
        // 确保匿名访客的来源 PV/UV 在缓存命中时仍被记录（写库已在函数内延迟到 shutdown，exit 后仍会执行）。
        if (function_exists('jinyu_track_visit_source')) {
            jinyu_track_visit_source();
        }
        echo $html;
        exit;
    }
}

function jinyu_page_cache_capture(): void
{
    // 检测到第三方整页缓存插件时自动让位，避免两层 HTML 缓存冲突 / 内容不同步。
    if (function_exists('jinyu_has_external_page_cache') && jinyu_has_external_page_cache()) return;
    if (empty($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'GET') return;
    if (is_user_logged_in() || is_admin()) return;
    // 这些响应体不该进整页缓存：404 / feed / 预览 / robots / trackback
    if (is_404() || is_feed() || is_preview() || is_robots() || is_trackback()) return;

    ob_start(function ($html) {
        if (strlen($html) < 500) return $html;
        $ttl = max(60, (int) jinyu_get_option('page_cache_ttl', 3600));
        set_transient(jinyu_page_cache_key(), $html, $ttl);
        return $html;
    });
}
