<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 用户中心辅助层
 *
 * 只放纯查询与格式化函数，交互逻辑一律走 inc/ajax/user.php
 */

/* ==========================================================================
   页面地址
   ========================================================================== */

/**
 * 后台配置的用户中心页面 ID，未配置返回 0
 */
function jinyu_user_page_id(): int
{
    return absint(jinyu_get_option('user_center_page', 0));
}

/**
 * 用户中心地址，可带 tab 参数
 */
function jinyu_user_page_url(string $tab = ''): string
{
    $id = jinyu_user_page_id();
    $base = $id ? get_permalink($id) : admin_url('profile.php');
    return $tab ? add_query_arg('tab', $tab, $base) : $base;
}

/**
 * 登录后跳转地址（优先取 redirect_to，其次来源页，最后用户中心）
 */
function jinyu_login_redirect_url(): string
{
    if (!empty($_REQUEST['redirect_to'])) {
        // esc_url_raw 仅过滤危险协议、不校验主机；必须用 wp_validate_redirect 限制跳回本站，杜绝开放重定向钓鱼
        return wp_validate_redirect(esc_url_raw(wp_unslash($_REQUEST['redirect_to'])), jinyu_user_page_url());
    }
    $ref = wp_get_referer();
    return $ref ?: jinyu_user_page_url();
}

/* ==========================================================================
   Tab 定义
   ========================================================================== */
function jinyu_user_tabs(): array
{
    $tabs = [
        'dashboard' => ['label' => __('概览', 'jinyu'),    'icon' => 'fa-solid fa-gauge-high'],
        'posts'     => ['label' => __('我的文章', 'jinyu'), 'icon' => 'fa-solid fa-file-lines'],
        'comments'  => ['label' => __('我的评论', 'jinyu'), 'icon' => 'fa-solid fa-comments'],
        'favs'      => ['label' => __('我的收藏', 'jinyu'), 'icon' => 'fa-solid fa-bookmark'],
        'profile'   => ['label' => __('资料设置', 'jinyu'), 'icon' => 'fa-solid fa-user-gear'],
    ];
    if (jinyu_is_checked('user_can_submit')) {
        $tabs['submit'] = ['label' => __('投稿', 'jinyu'), 'icon' => 'fa-solid fa-pen-to-square'];
    }
    $tabs['follow']        = ['label' => __('我的关注', 'jinyu'), 'icon' => 'fa-solid fa-user-group'];
    $tabs['notifications'] = ['label' => __('消息', 'jinyu'),     'icon' => 'fa-solid fa-bell'];
    return $tabs;
}

function jinyu_current_user_tab(): string
{
    $tab = sanitize_key($_GET['tab'] ?? 'dashboard');
    return array_key_exists($tab, jinyu_user_tabs()) ? $tab : 'dashboard';
}

/* ==========================================================================
   头像
   ========================================================================== */
/**
 * 头像源统一升级 https。
 * 历史 OAuth 头像（thirdqq.qlogo.cn 等）在库中存的是 http://，
 * https 页面加载 http 子资源会被浏览器按混合内容拦截 → 裂图。
 * 这些头像 CDN 均支持 https，出口处统一替换。
 */
function jinyu_avatar_https(string $url): string
{
    return preg_replace('#^http://#i', 'https://', $url);
}

function jinyu_user_avatar_url(int $user_id, int $size = 96): string
{
    $custom = get_user_meta($user_id, 'jinyu_avatar', true);
    if ($custom) {
        return jinyu_avatar_https(jinyu_img_to_webp_url(esc_url_raw($custom)));
    }
    // 兼容：旧版全局 jinyu_oauth_avatar + 新版按平台 jinyu_oauth_{platform}_avatar
    $oauth = '';
    foreach ( get_user_meta( $user_id ) ?: [] as $k => $v ) {
        if ( 'jinyu_oauth_avatar' === $k || preg_match( '/^jinyu_oauth_.+_avatar$/', (string) $k ) ) {
            $val = is_array( $v ) ? ( $v[0] ?? '' ) : $v;
            if ( ! empty( $val ) ) {
                $oauth = $val;
                break;
            }
        }
    }
    if ($oauth) {
        return jinyu_avatar_https(jinyu_img_to_webp_url(esc_url_raw($oauth)));
    }
    // 兼容本地头像插件与旧主题遗留：Simple Local Avatars 标准 meta（simple_local_avatar）
    // 及 Kratos 旧主题的 kratos_local_avatar，结构均为序列化数组（full + 各尺寸 URL）。
    // 优先取匹配尺寸，无则回退 full，保证老站迁移后用户设置过的头像不丢。
    foreach (['simple_local_avatar', 'kratos_local_avatar'] as $local_key) {
        $local = get_user_meta($user_id, $local_key, true);
        if (is_array($local)) {
            $url = $local[$size] ?? ($local['full'] ?? '');
            if (is_string($url) && preg_match('#^https?://#i', $url)) {
                return jinyu_avatar_https(jinyu_img_to_webp_url(esc_url_raw($url)));
            }
        }
    }
    // 无自定义 / OAuth / 本地插件头像时返回空，由各调用点的 jinyu_avatar_default() 兜底，
    // 显示主题首字母占位图（彩色圆底 + 居中白字），永不破图、视觉统一。
    // 此前回落 Cravatar 会因生成图不居中、被圆形裁切显得歪，已撤除。
    return '';
}

if (!function_exists('jinyu_letter_avatar')) {
    /**
     * 按名字生成首字母占位头像（内联 SVG data URI）。
     *
     * 不依赖任何外部头像服务，永不破图；同一名字得到的底色稳定（按名字哈希取色）。
     * 匿名评论者（无用户 ID、无可靠 Gravatar）也能有头像，视觉统一。
     *
     * ⚠️ 返回的是 data URI，输出到 src 时**必须用 esc_attr()**。
     *    禁用 esc_url()：其协议白名单不含 data:，会把整个地址清空 → src="" 破图。
     *
     * @param string $name 显示名
     * @param int    $size 边长（px）
     */
    function jinyu_letter_avatar(string $name, int $size = 64): string
    {
        $name = trim($name);
        $ch   = $name !== '' ? mb_substr($name, 0, 1) : '?';

        // 一组与主题品牌协调的底色，按名字哈希稳定取色
        static $palette = ['#1c60f3', '#2f9e6e', '#e8813a', '#9b59b6', '#e0574f', '#0f9bb5', '#c2952b', '#4b6ca8'];
        $color = $palette[crc32($name) % count($palette)];

        $half = (int) round($size / 2);
        $font = (int) round($size * 0.46);
        $svg  = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%1$d" viewBox="0 0 %1$d %1$d">'
            . '<rect width="%1$d" height="%1$d" rx="%2$d" fill="%3$s"/>'
            . '<text x="%2$d" y="%2$d" dy=".35em" text-anchor="middle" font-family="-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif" font-size="%4$d" font-weight="600" fill="#ffffff">%5$s</text>'
            . '</svg>',
            $size,
            $half,
            $color,
            $font,
            esc_html($ch)
        );
        return 'data:image/svg+xml;charset=utf-8,' . rawurlencode($svg);
    }
}

/**
 * 离线友好的默认头像（首字母 SVG），Gravatar 不可达时兜底
 */
function jinyu_avatar_default(int $user_id): string
{
    $user = get_userdata($user_id);
    return jinyu_letter_avatar($user ? (string) $user->display_name : '', 64);
}

add_filter('get_avatar_url', function ($url, $id_or_email) {
    $uid = 0;
    if (is_numeric($id_or_email)) {
        $uid = (int)$id_or_email;
    } elseif (is_object($id_or_email) && isset($id_or_email->user_id)) {
        $uid = (int)$id_or_email->user_id;
    } elseif (is_string($id_or_email) && ($u = get_user_by('email', $id_or_email))) {
        $uid = (int)$u->ID;
    }
    if (!$uid) {
        return $url;
    }
    $custom = jinyu_user_avatar_url($uid);
    return $custom ?: $url;
}, 10, 2);

// 本地上传的自定义头像也走 WebP 替换（Gravatar/外部 OAuth 头像域名不匹配，自动回退原图）
add_filter('get_avatar_url', function ($url) {
    return jinyu_img_to_webp_url($url);
}, 20, 1);

/**
 * 国内头像源：按后台「头像来源」把 Gravatar 主机改写为 Cravatar / WeAvatar。
 * 二者兼容 Gravatar 同一套 /avatar/{md5(email)} 协议（同样支持 s/d/r/f 参数）。
 * 仅改写真正的 Gravatar 默认地址；自定义头像（data: 或第三方 URL）不动。
 */
add_filter('get_avatar_url', function ($url, $id_or_email, $args) {
    static $map = [
        'cravatar' => 'https://cn.cravatar.com/avatar/',
        'weavatar' => 'https://weavatar.com/avatar/',
        'v2ex'     => 'https://cdn.v2ex.com/gravatar/',
        'loli'     => 'https://gravatar.loli.net/avatar/',
        'qiniu'    => 'https://dn-qiniu-avatar.qbox.me/avatar/',
        'webpse'   => 'https://gravatar.webp.se/avatar/',
    ];
    $src = jinyu_get_option('comment_avatar_src', 'gravatar');
    if (!isset($map[$src])) {
        return $url;
    }
    if (preg_match('#^https?://[^/]+/avatar/#i', $url)) {
        return preg_replace('#^https?://[^/]+/avatar/#i', $map[$src], $url);
    }
    return $url;
}, 20, 3);

/* ==========================================================================
   统计与列表
   ========================================================================== */
function jinyu_user_stats(int $uid): array
{
    return [
        'posts'    => (int)count_user_posts($uid, 'post', true),
        'comments' => (int)get_comments(['user_id' => $uid, 'count' => true, 'status' => 'approve']),
        'favs'     => count(jinyu_get_user_favs($uid)),
        'likes'    => (int)get_user_meta($uid, 'jinyu_received_likes', true),
    ];
}

/**
 * 用户身份（角色）的中文标签，用于用户中心概览页展示
 */
function jinyu_user_role_label(int $uid): string
{
    $user = get_userdata($uid);
    if (!$user || empty($user->roles)) {
        return __('访客', 'jinyu');
    }
    $map = [
        'administrator' => __('管理员', 'jinyu'),
        'editor'        => __('编辑', 'jinyu'),
        'author'        => __('作者', 'jinyu'),
        'contributor'   => __('投稿者', 'jinyu'),
        'subscriber'    => __('订阅者', 'jinyu'),
    ];
    $role = $user->roles[0];
    return $map[$role] ?? $role;
}

function jinyu_user_posts(int $uid, int $paged = 1, int $per = 10): WP_Query
{
    return new WP_Query([
        'author'         => $uid,
        'post_type'      => 'post',
        'post_status'    => ['publish', 'pending', 'draft'],
        'posts_per_page' => $per,
        'paged'          => $paged,
    ]);
}

function jinyu_user_comments(int $uid, int $per = 20): array
{
    return get_comments([
        'user_id' => $uid,
        'status'  => 1,
        'number'  => $per,
    ]);
}

/* ==========================================================================
   第三方登录（oauth）入口：社交登录函数族 jinyu_oauth_* 由配套插件提供。
   主题侧调用点以 function_exists() 守卫：插件未启用时登录弹窗不显示第三方入口，
   不会出现致命错误。
   ========================================================================== */

/* ==========================================================================
   投稿
   ========================================================================== */
function jinyu_user_can_submit(): bool
{
    return jinyu_is_checked('user_can_submit') && is_user_logged_in();
}

if (!function_exists('jinyu_post_status_label')) {
    /**
     * 投稿状态的中文友好标签（用于「我的文章 / 概览」列表的状态徽标）
     * - pending → 审核中（比 WP 默认的「待审」更贴合前台投稿语境）
     */
    function jinyu_post_status_label($status): string
    {
        $map = [
            'publish' => __('已发布', 'jinyu'),
            'pending' => __('审核中', 'jinyu'),
            'draft'   => __('草稿', 'jinyu'),
        ];
        if (isset($map[$status])) return $map[$status];
        $obj = get_post_status_object($status);
        return $obj && !empty($obj->label) ? $obj->label : (string)$status;
    }
}

/**
 * 投稿频控：同一用户 5 分钟 1 篇
 */
function jinyu_submit_rate_limited(int $uid): bool
{
    return (bool)get_transient('jy_submit_' . $uid);
}
