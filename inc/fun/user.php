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
    return [
        'dashboard'     => ['label' => __('概览', 'jinyu'),    'icon' => 'fa-solid fa-gauge-high'],
        'posts'         => ['label' => __('我的文章', 'jinyu'), 'icon' => 'fa-solid fa-file-lines'],
        'interact'      => ['label' => __('互动', 'jinyu'),    'icon' => 'fa-solid fa-comment-dots'],
        'profile'       => ['label' => __('资料设置', 'jinyu'), 'icon' => 'fa-solid fa-user-gear'],
        'notifications' => ['label' => __('消息', 'jinyu'),    'icon' => 'fa-solid fa-bell'],
    ];
}

/**
 * 「互动」tab 的子页签（评论 / 收藏 / 关注），子状态走 &sub= 参数。
 * 三者同为低频查看型内容，合并后侧栏从 8 项收敛到 5 项。
 */
function jinyu_user_interact_tabs(): array
{
    return [
        'comments' => ['label' => __('评论', 'jinyu'), 'icon' => 'fa-solid fa-comments'],
        'favs'     => ['label' => __('收藏', 'jinyu'), 'icon' => 'fa-solid fa-bookmark'],
        'follow'   => ['label' => __('关注', 'jinyu'), 'icon' => 'fa-solid fa-user-group'],
    ];
}

function jinyu_current_interact_sub(): string
{
    $sub = sanitize_key($_GET['sub'] ?? 'comments');
    return array_key_exists($sub, jinyu_user_interact_tabs()) ? $sub : 'comments';
}

/**
 * 旧版独立 tab → 新信息架构的映射：
 * comments/favs/follow → interact 子页签；submit → posts 撰写态（?compose=1）。
 */
function jinyu_user_legacy_tabs(): array
{
    return [
        'comments' => ['interact', 'comments'],
        'favs'     => ['interact', 'favs'],
        'follow'   => ['interact', 'follow'],
        'submit'   => ['posts', ''],
    ];
}

function jinyu_current_user_tab(): string
{
    $tab = sanitize_key($_GET['tab'] ?? 'dashboard');
    return array_key_exists($tab, jinyu_user_tabs()) ? $tab : 'dashboard';
}

/**
 * 旧 tab 链接 301 到新地址：收藏夹 / 站内旧入口不断链。
 */
add_action('template_redirect', function (): void {
    if (!is_page_template('pages/template-user.php')) {
        return;
    }
    $tab    = sanitize_key($_GET['tab'] ?? '');
    $legacy = jinyu_user_legacy_tabs();
    if (!isset($legacy[$tab])) {
        return;
    }
    $args = ['tab' => $legacy[$tab][0]];
    if ('' !== $legacy[$tab][1]) {
        $args['sub'] = $legacy[$tab][1];
    } else {
        $args['compose'] = '1';
    }
    wp_safe_redirect(add_query_arg($args, jinyu_user_page_url()), 301);
    exit;
});

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
   分页
   ========================================================================== */
/**
 * 用户中心当前页码。
 * 用户中心是页面型模板 + ?tab= 参数：rewrite 形态 /page/2/ 落到 page 变量，
 * 查询串 ?paged=2 落到 paged 变量，两者都读，避免任何形态下翻页失效。
 */
function jinyu_user_paged(): int
{
    $p = (int) get_query_var('paged') ?: (int) get_query_var('page');
    if (!$p && isset($_GET['paged'])) {
        $p = absint($_GET['paged']);
    }
    return max(1, $p);
}

/**
 * 用户中心分页导航（复用全站 .jinyu-pagination 样式，含移动端密度适配）。
 * base 一律从用户中心页 permalink 构建：AJAX 渲染时当前 URI 是 admin-ajax.php，
 * 直接 add_query_arg 会产出断链；从 permalink 构建则整页/AJAX 两态都正确且保留 tab 参数。
 */
function jinyu_user_pagination(int $total): void
{
    $total = (int) $total;
    if ($total <= 1) {
        return;
    }
    $qv   = (int) get_query_var('paged') ? 'paged' : 'page';
    // 互动 tab 带 sub 参数：翻页链接必须保留当前子页签，否则翻页后跳回默认子页
    $tab  = jinyu_current_user_tab();
    $url  = 'interact' === $tab
        ? add_query_arg('sub', jinyu_current_interact_sub(), jinyu_user_page_url($tab))
        : jinyu_user_page_url($tab);
    $base = add_query_arg($qv, '%#%', $url);
    $links = paginate_links([
        'base'      => $base,
        'format'    => '',
        'current'   => jinyu_user_paged(),
        'total'     => $total,
        'type'      => 'list',
        'mid_size'  => 2,
        'end_size'  => 1,
        'prev_text' => '<i class="fa-solid fa-angle-left"></i>',
        'next_text' => '<i class="fa-solid fa-angle-right"></i>',
    ]);
    if (!$links) {
        return;
    }
    printf(
        '<nav class="jinyu-pagination jinyu-user-pagination" aria-label="%s">%s</nav>',
        esc_attr__('用户中心分页', 'jinyu'),
        $links
    );
}

/**
 * 输出用户中心「我的文章 / 概览-最近文章」单行（缩略图 + 标题 + 状态/日期/数据 + 操作）。
 * 须在 WP_Query 循环内调用（依赖全局 $post）；概览与列表两处共用，保证结构一致。
 */
function jinyu_user_post_row(): void
{
    $pid    = get_the_ID();
    $status = get_post_status();
    $views  = function_exists('jinyu_get_post_views') ? jinyu_get_post_views($pid) : 0;
    ?>
    <li class="jinyu-user-post-item">
      <a class="jinyu-user-post-thumb" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
        <?php if (has_post_thumbnail()) : ?>
          <?php echo get_the_post_thumbnail($pid, 'thumbnail', ['loading' => 'lazy']); ?>
        <?php else : ?>
          <i class="fa-regular fa-image" aria-hidden="true"></i>
        <?php endif; ?>
      </a>
      <div class="jinyu-user-post-body">
        <a class="jinyu-user-post-title" href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
        <span class="jinyu-user-post-meta">
          <span class="jinyu-user-post-status jinyu-status-<?php echo esc_attr($status); ?>">
            <?php echo esc_html(jinyu_post_status_label($status)); ?>
          </span>
          <span class="jinyu-user-post-date"><?php echo esc_html(get_the_date()); ?></span>
          <span class="jinyu-user-post-num"><i class="fa-regular fa-eye" aria-hidden="true"></i><?php echo esc_html(number_format_i18n($views)); ?></span>
          <span class="jinyu-user-post-num"><i class="fa-regular fa-comment" aria-hidden="true"></i><?php echo esc_html(number_format_i18n((int) get_comments_number($pid))); ?></span>
        </span>
      </div>
      <span class="jinyu-user-post-acts">
        <?php if (current_user_can('edit_post', $pid)) : ?>
          <a class="jinyu-btn jinyu-btn-ghost jinyu-post-act"
             href="<?php echo esc_url(admin_url('post.php?post=' . $pid . '&action=edit')); ?>">
            <?php esc_html_e('编辑', 'jinyu'); ?>
          </a>
        <?php endif; ?>
        <?php if ('pending' === $status) : ?>
          <button type="button" class="jinyu-btn jinyu-btn-ghost jinyu-post-act jinyu-post-del"
                  data-post-id="<?php echo esc_attr($pid); ?>"
                  data-confirm="<?php esc_attr_e('确定撤回该投稿吗？撤回后不可恢复。', 'jinyu'); ?>">
            <?php esc_html_e('撤回', 'jinyu'); ?>
          </button>
        <?php elseif ('draft' === $status) : ?>
          <button type="button" class="jinyu-btn jinyu-btn-ghost jinyu-post-act jinyu-post-del"
                  data-post-id="<?php echo esc_attr($pid); ?>"
                  data-confirm="<?php esc_attr_e('确定删除该草稿吗？删除后不可恢复。', 'jinyu'); ?>">
            <?php esc_html_e('删除', 'jinyu'); ?>
          </button>
        <?php endif; ?>
      </span>
    </li>
    <?php
}

/**
 * 用户中心空态（图标 + 文案 + 可选引导按钮），替代单行灰字的 .jinyu-empty
 */
function jinyu_user_empty(string $icon, string $text, string $btn = '', string $btn_url = ''): void
{
    echo '<div class="jinyu-empty-box">';
    echo '<i class="' . esc_attr($icon) . '" aria-hidden="true"></i>';
    echo '<p>' . esc_html($text) . '</p>';
    if ($btn !== '' && $btn_url !== '') {
        echo '<a class="jinyu-btn jinyu-btn-primary" href="' . esc_url($btn_url) . '">' . esc_html($btn) . '</a>';
    }
    echo '</div>';
}

/**
 * 输出用户中心「我的收藏」单个卡片（卡片 + 悬浮取消收藏按钮）。
 * 须在 WP_Query 循环内调用（依赖全局 $post）；AJAX 局部刷新走同一出口，保证结构一致。
 */
function jinyu_fav_cell(): void
{
    $pid = get_the_ID();
    echo '<div class="jinyu-fav-cell">';
    get_template_part('templates/module', 'post');
    echo '<button type="button" class="jinyu-fav-remove" data-jinyu-fav-remove data-post-id="' . esc_attr($pid) . '">'
        . '<i class="fa-solid fa-bookmark-slash" aria-hidden="true"></i>' . esc_html__('取消收藏', 'jinyu')
        . '</button>';
    echo '</div>';
}

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

function jinyu_user_comments(int $uid, int $per = 20, int $paged = 1): array
{
    return get_comments([
        'user_id' => $uid,
        'status'  => 1,
        'number'  => $per,
        'offset'  => max(0, ($paged - 1) * $per),
    ]);
}

/**
 * 该用户已通过审核的评论总数（分页用）
 */
function jinyu_user_comments_count(int $uid): int
{
    return (int) get_comments([
        'user_id' => $uid,
        'status'  => 1,
        'count'   => true,
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
