<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 安全加固
 * XML-RPC / REST API 开关逻辑
 */

// XML-RPC 默认关闭：常见暴破与 pingback 攻击面（原 close_xmlrpc 开关，已内置常关）
add_filter('xmlrpc_enabled', '__return_false');
if (jinyu_is_checked('close_rest_api')) {
    // 关闭 REST API 给未登录用户的访问（WP 4.7+ 已废弃 rest_enabled 过滤器，改用 rest_authentication_errors）
    add_filter('rest_authentication_errors', function ($result) {
        if (!empty($result)) {
            return $result;
        }
        if (!is_user_logged_in()) {
            return new WP_Error(
                'jinyu_rest_disabled',
                __('REST API 已对未登录用户关闭。', 'jinyu'),
                ['status' => rest_authorization_required_code()]
            );
        }
        return $result;
    });
    add_filter('rest_jsonp_enabled', '__return_false');

    // 后台提示：说明「关闭 REST API」仅影响未登录访客，古登堡编辑器（已登录）不受影响，
    // 消除原描述「可能影响区块编辑器」造成的误判，避免管理员不敢开启或误以为后台已坏。
    add_action('admin_notices', function () {
        if (!current_user_can('manage_options')) return;
        echo '<div class="notice notice-info"><p>' .
            esc_html__('「关闭 REST API」已生效：仅未登录访客被拒绝，已登录用户在后台使用古登堡编辑器不受影响。', 'jinyu') .
            '</p></div>';
    });
}

// 去掉 WordPress 版本号输出（防扫描）
add_filter('the_generator', '__return_empty_string');

/* ==========================================================================
   安全：客户端真实 IP 与通用 IP 速率限制
   —— 公共能力，供全站 AJAX 接口复用，必须无条件定义（不能包在下方的开关里，
      否则关闭某个开关会导致调用方 Call to undefined function 直接 500）
   ========================================================================== */

// 获取客户端真实 IP：自托管环境优先 REMOTE_ADDR（TCP 对端，不可伪造）。
// 仅在 REMOTE_ADDR 属于可信代理网段时才回退到 X-Forwarded-For，避免攻击者伪造该头绕过限流。
if (!function_exists('jinyu_client_ip')) {
    function jinyu_client_ip(): string
    {
        $remote = isset($_SERVER['REMOTE_ADDR']) ? trim((string) $_SERVER['REMOTE_ADDR']) : '';
        $trusted_proxy = filter_var($remote, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            && (
                str_starts_with($remote, '10.')          // RFC1918 私有地址
                || str_starts_with($remote, '172.16.')   // 172.16.0.0/12（简化判断）
                || str_starts_with($remote, '192.168.')  // 192.168.0.0/16
                || $remote === '127.0.0.1'
            );
        if ($trusted_proxy) {
            foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP'] as $k) {
                if (!empty($_SERVER[$k])) {
                    $ip = trim(explode(',', (string) $_SERVER[$k])[0]);
                    if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
                }
            }
        }
        return $remote !== '' ? $remote : '0.0.0.0';
    }
}

/**
 * 通用 IP 速率限制（基于 transient 的近似滑动窗口）
 * 用于匿名 AJAX 接口（AI 对话 / 海报生成 / 注册 / 找回密码）防刷，
 * 避免被滥用消耗服务器资源或第三方付费额度。
 *
 * @param string $action  动作标识（区分不同接口）
 * @param int    $max     时间窗口内允许的最大请求数
 * @param int    $seconds 时间窗口（秒）
 * @return bool  true=放行，false=已超限需拒绝
 */
if (!function_exists('jinyu_rate_limit_check')) {
    function jinyu_rate_limit_check(string $action, int $max = 10, int $seconds = 60): bool
    {
        $ip = function_exists('jinyu_client_ip') ? jinyu_client_ip() : (isset($_SERVER['REMOTE_ADDR']) ? trim((string) $_SERVER['REMOTE_ADDR']) : '0.0.0.0');
        $key   = 'jinyu_rl_' . md5($action . '|' . $ip);
        $count = (int) get_transient($key);
        if ($count >= $max) {
            return false;
        }
        // 计数 +1；首次写入带 TTL，自然过期后窗口重置
        set_transient($key, $count + 1, $seconds);
        return true;
    }
}

/* ==========================================================================
   安全：后台登录防暴破（按客户端 IP 限流，连续失败锁定一段时间）
   ========================================================================== */
// 登录防暴破（原 login_brute_force 开关，已内置常开）：按客户端 IP 限流，连续失败锁定一段时间
{
    $jinyu_brute_max = 5;                       // 允许的最大连续失败次数
    $jinyu_brute_ttl = 15 * MINUTE_IN_SECONDS; // 锁定时长（秒）
    $jinyu_brute_min = (int) ceil($jinyu_brute_ttl / MINUTE_IN_SECONDS); // 用于提示文案，随 TTL 联动

    // 登录失败时累计该 IP 的失败次数（锁定窗口内持续刷新）。
    // 用 transients 的 timeout 语义实现 TTL；非原子计数在并发下可能少计，仅影响锁定触发的灵敏度，不削弱安全性。
    add_action('wp_login_failed', function () use ($jinyu_brute_ttl) {
        $key  = 'jinyu_brute_' . md5(jinyu_client_ip());
        $fails = (int) get_transient($key) + 1;
        set_transient($key, $fails, $jinyu_brute_ttl);
    });

    // 认证阶段：达到失败阈值时一律拒绝（即便密码正确），确保锁定对所有请求生效，防范暴力枚举。
    add_filter('authenticate', function ($user, $username, $password) use ($jinyu_brute_max, $jinyu_brute_min) {
        $fails = (int) get_transient('jinyu_brute_' . md5(jinyu_client_ip()));
        if ($fails >= $jinyu_brute_max) {
            return new WP_Error(
                'jinyu_brute',
                sprintf(
                    __('登录尝试过于频繁，已被临时锁定，请 %d 分钟后再试。', 'jinyu'),
                    $jinyu_brute_min
                )
            );
        }
        return $user;
    }, 30, 3);

    // 登录成功后清零该 IP 计数器
    add_action('wp_login', function () {
        delete_transient('jinyu_brute_' . md5(jinyu_client_ip()));
    });
}
