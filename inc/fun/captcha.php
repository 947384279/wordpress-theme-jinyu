<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 图形验证码
 *
 * 设计说明：
 *  - 不使用 PHP session（WP 默认不启动，且与部分缓存插件冲突），
 *    验证码文本存 transient，索引 key 写入 httponly cookie，同请求内立即可用。
 *  - 验证码一次性：校验通过或失败后立即失效。
 *  - 图片用内联 SVG 输出，不依赖 GD 扩展。
 */

const JY_CAPTCHA_COOKIE = 'jy_captcha_key';
const JY_CAPTCHA_TTL    = 600;

/**
 * 可用字符（剔除 0/O/1/I/L 等易混淆字形）
 */
function jinyu_captcha_chars(): string
{
    return 'ABCDEFGHJKMNPQRSTUVWXY3456789';
}

/**
 * 当前请求绑定的验证码索引 key
 */
function jinyu_captcha_key(): string
{
    $key = $_COOKIE[JY_CAPTCHA_COOKIE] ?? '';
    return preg_match('/^[a-f0-9]{32}$/', $key) ? $key : '';
}

/**
 * 生成新验证码，返回明文（用于输出图片）
 */
function jinyu_captcha_generate(): string
{
    $chars = jinyu_captcha_chars();
    $max   = strlen($chars) - 1;
    $code  = '';
    for ($i = 0; $i < 4; $i++) {
        $code .= $chars[random_int(0, $max)];
    }

    $key = bin2hex(random_bytes(16));
    set_transient('jy_captcha_' . $key, strtolower($code), JY_CAPTCHA_TTL);

    setcookie(
        JY_CAPTCHA_COOKIE,
        $key,
        time() + JY_CAPTCHA_TTL,
        defined('COOKIEPATH') ? COOKIEPATH : '/',
        defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '',
        is_ssl(),
        true
    );
    // 让本次请求内即可读到，避免依赖浏览器回写
    $_COOKIE[JY_CAPTCHA_COOKIE] = $key;

    return $code;
}

/**
 * 校验验证码（大小写不敏感，一次性）
 */
function jinyu_captcha_check(string $input): bool
{
    $key = jinyu_captcha_key();
    if (!$key) {
        return false;
    }

    $code = get_transient('jy_captcha_' . $key);
    delete_transient('jy_captcha_' . $key);
    if (!$code) {
        return false;
    }

    return hash_equals((string)$code, strtolower(trim($input)));
}

/**
 * 是否需要验证码：后台强制开启，或该 IP 近期失败次数过多
 */
function jinyu_captcha_required(string $scene): bool
{
    $policy = jinyu_get_option('captcha_policy', 'smart');
    if ($policy === 'always') {
        return true;
    }
    if ($policy === 'off') {
        return false;
    }
    // smart（默认）：注册 / 找回始终要求；登录仅失败 3 次后要求
    if ($scene !== 'login') {
        return true;
    }
    return jinyu_login_failures() >= 3;
}

/**
 * 指定 IP 的登录失败次数（15 分钟窗口）
 */
function jinyu_login_failures(): int
{
    return (int)get_transient('jy_login_fail_' . jinyu_client_key());
}

function jinyu_login_failure_incr(): void
{
    $k = 'jy_login_fail_' . jinyu_client_key();
    set_transient($k, jinyu_login_failures() + 1, 15 * MINUTE_IN_SECONDS);
}

function jinyu_login_failure_reset(): void
{
    delete_transient('jy_login_fail_' . jinyu_client_key());
}

function jinyu_client_key(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return md5($ip);
}

/**
 * 统一校验入口：需要验证码时返回 WP_Error，否则返回 true
 */
function jinyu_captcha_verify(string $scene, string $input)
{
    if (!jinyu_captcha_required($scene)) {
        return true;
    }
    if (jinyu_captcha_check($input)) {
        return true;
    }
    return new WP_Error('captcha_error', __('验证码不正确或已过期', JINYU));
}

/* ==========================================================================
   前台弹窗用的验证码 HTML 片段
   图片地址延迟到前端切到对应面板时再注入，避免多个面板同时刷新 cookie 互相覆盖
   ========================================================================== */
function jinyu_captcha_markup(string $scene): string
{
    if (!jinyu_captcha_required($scene)) {
        return '';
    }

    $base = admin_url('admin-ajax.php?action=jinyu_captcha');
    ob_start();
    ?>
    <div class="jinyu-captcha-row" data-jinyu-captcha data-scene="<?php echo esc_attr($scene); ?>"
         data-jinyu-captcha-src="<?php echo esc_url($base); ?>">
      <img class="jinyu-captcha-img" alt="<?php esc_attr_e('验证码', JINYU); ?>" hidden>
      <button type="button" class="jinyu-captcha-refresh" data-jinyu-captcha-refresh
              aria-label="<?php esc_attr_e('刷新验证码', JINYU); ?>">
        <i class="fa-solid fa-rotate" aria-hidden="true"></i>
      </button>
      <input type="text" name="captcha" inputmode="latin" autocomplete="off"
             maxlength="4" placeholder="<?php esc_attr_e('验证码', JINYU); ?>">
    </div>
    <?php
    return (string)ob_get_clean();
}

/* ==========================================================================
   图片输出端点：/wp-admin/admin-ajax.php?action=jinyu_captcha
   ========================================================================== */
add_action('wp_ajax_jinyu_captcha', 'jinyu_captcha_output');
add_action('wp_ajax_nopriv_jinyu_captcha', 'jinyu_captcha_output');
function jinyu_captcha_output(): void
{
    nocache_headers();
    header('Content-Type: image/svg+xml; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo jinyu_captcha_svg(jinyu_captcha_generate());
    exit;
}

/**
 * 生成验证码 SVG
 * 注意：不做 URL 编码依赖，全部为内联图元，浏览器 <img> 可直接渲染
 */
function jinyu_captcha_svg(string $code): string
{
    static $palette = ['#2563eb', '#7c3aed', '#db2777', '#0891b2', '#059669', '#d97706'];

    $w = 112;
    $h = 42;
    $parts = [];

    $parts[] = sprintf('<rect width="%d" height="%d" fill="#eef1f6"/>', $w, $h);

    // 干扰线
    for ($i = 0; $i < 4; $i++) {
        $parts[] = sprintf(
            '<line x1="%d" y1="%d" x2="%d" y2="%d" stroke="%s" stroke-width="1" opacity="0.55"/>',
            random_int(0, $w),
            random_int(0, $h),
            random_int(0, $w),
            random_int(0, $h),
            $palette[random_int(0, count($palette) - 1)]
        );
    }

    // 干扰点
    for ($i = 0; $i < 28; $i++) {
        $parts[] = sprintf(
            '<circle cx="%d" cy="%d" r="1" fill="%s" opacity="0.5"/>',
            random_int(2, $w - 2),
            random_int(2, $h - 2),
            $palette[random_int(0, count($palette) - 1)]
        );
    }

    // 字符
    $len = strlen($code);
    for ($i = 0; $i < $len; $i++) {
        $x = 14 + $i * 24;
        $parts[] = sprintf(
            '<text x="%d" y="%d" font-family="Menlo,Consolas,monospace" font-size="24" font-weight="700" fill="%s" transform="rotate(%d %d 26)">%s</text>',
            $x,
            random_int(28, 33),
            $palette[random_int(0, count($palette) - 1)],
            random_int(-18, 18),
            $x + 8,
            esc_html($code[$i])
        );
    }

    return sprintf(
        '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d">%s</svg>',
        $w,
        $h,
        $w,
        $h,
        implode('', $parts)
    );
}
