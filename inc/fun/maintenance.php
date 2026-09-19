<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if (!function_exists('jinyu_maintenance_mode')) {
    /**
     * 维护模式：开启后，非管理员访问前台显示「网站维护中」页（HTTP 503）。
     * 管理员与后台 / 登录接口不受影响。
     */
    function jinyu_maintenance_mode()
    {
        if (!jinyu_is_checked('maintenance_mode')) return;
        if (is_admin()) return;
        if (defined('DOING_AJAX') && DOING_AJAX) return;
        if (defined('DOING_CRON') && DOING_CRON) return;
        // 登录/注册等 wp-login.php 请求需放行，否则无人能进后台关闭维护模式
        if (isset($GLOBALS['pagenow']) && $GLOBALS['pagenow'] === 'wp-login.php') return;
        if (current_user_can('manage_options')) return;

        header('HTTP/1.1 503 Service Temporarily Unavailable', true, 503);
        header('Retry-After: 3600');
        $text = trim((string) jinyu_get_option('maintenance_text', ''));
        if (!$text) {
            $text = __('网站正在维护中，请稍后再访。', JINYU);
        }
        $title = get_bloginfo('name');
        echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . esc_html($title) . ' · ' . esc_html__('维护中', JINYU) . '</title>'
            . '<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;'
            . 'background:#0f1115;color:#e5e7eb;font-family:system-ui,-apple-system,"PingFang SC","Microsoft YaHei",sans-serif;'
            . 'text-align:center;padding:24px;box-sizing:border-box}'
            . '.m-card{max-width:520px}.m-icon{font-size:48px;margin-bottom:16px}'
            . 'h1{font-size:22px;margin:0 0 12px;font-weight:600}.m-text{color:#9ca3af;line-height:1.7}'
            . '.m-site{margin-top:24px;font-size:13px;color:#6b7280}</style></head>'
            . '<body><div class="m-card"><div class="m-icon">🛠️</div>'
            . '<h1>' . esc_html__('网站维护中', JINYU) . '</h1>'
            . '<p class="m-text">' . esc_html($text) . '</p>'
            . '<div class="m-site">' . esc_html($title) . '</div></div></body></html>';
        exit;
    }
}
