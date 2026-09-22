<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 用户前台 Ajax：登录 / 注册 / 找回密码 / 资料 / 头像 / 投稿
 *
 * 安全约定：
 *  - 所有接口走 jinyu_ajax_guard() 校验 nonce
 *  - 密码类字段不做 sanitize（会破坏原始字符），只做长度校验
 *  - 验证码由 inc/fun/captcha.php 统一判定，失败即失效
 */

/* ==========================================================================
   登录
   ========================================================================== */
add_action('wp_ajax_nopriv_jinyu_login', 'jinyu_ajax_login');
function jinyu_ajax_login(): void
{
    jinyu_ajax_guard();

    $login   = trim(sanitize_user(wp_unslash($_POST['log'] ?? ''), true));
    $pass    = (string)($_POST['pwd'] ?? '');
    $captcha = (string)($_POST['captcha'] ?? '');

    if ($login === '' || $pass === '') {
        wp_send_json_error(__('请输入账号和密码', 'jinyu'));
    }

    if (function_exists('jinyu_captcha_verify')) {
        $verify = jinyu_captcha_verify('login', $captcha);
        if (is_wp_error($verify)) {
            if (function_exists('jinyu_login_failure_incr')) { jinyu_login_failure_incr(); }
            wp_send_json_error($verify->get_error_message());
        }
    }

    $user = wp_signon([
        'user_login'    => $login,
        'user_password' => $pass,
        'remember'      => !empty($_POST['rememberme']),
    ], is_ssl());

    if (is_wp_error($user)) {
        if (function_exists('jinyu_login_failure_incr')) { jinyu_login_failure_incr(); }
        wp_send_json_error(__('账号或密码错误', 'jinyu'));
    }

    if (function_exists('jinyu_login_failure_reset')) { jinyu_login_failure_reset(); }
    wp_send_json_success([
        'message'  => __('登录成功，正在跳转…', 'jinyu'),
        'redirect' => jinyu_login_redirect_url(),
    ]);
}

/* ==========================================================================
   注册
   ========================================================================== */
add_action('wp_ajax_nopriv_jinyu_register', 'jinyu_ajax_register');
function jinyu_ajax_register(): void
{
    jinyu_ajax_guard();

    if (!get_option('users_can_register')) {
        wp_send_json_error(__('站点已关闭注册', 'jinyu'));
    }

    // 注册接口是 nopriv 端点，必须限流，否则可被脚本批量刷账号
    if (!jinyu_rate_limit_check('register', 5, MINUTE_IN_SECONDS)) {
        wp_send_json_error(__('操作过于频繁，请稍后再试', 'jinyu'));
    }

    $login   = trim(sanitize_user(wp_unslash($_POST['log'] ?? ''), true));
    $email   = trim(sanitize_email(wp_unslash($_POST['email'] ?? '')));
    $pass    = (string)($_POST['pwd'] ?? '');
    $pass2   = (string)($_POST['pwd2'] ?? '');
    $captcha = (string)($_POST['captcha'] ?? '');

    if (strlen($login) < 3 || strlen($login) > 30) {
        wp_send_json_error(__('用户名长度需为 3-30 个字符', 'jinyu'));
    }
    if (!validate_username($login)) {
        wp_send_json_error(__('用户名含非法字符', 'jinyu'));
    }
    if (username_exists($login)) {
        wp_send_json_error(__('该用户名已被占用', 'jinyu'));
    }
    if (!is_email($email)) {
        wp_send_json_error(__('邮箱格式不正确', 'jinyu'));
    }
    if (email_exists($email)) {
        wp_send_json_error(__('该邮箱已注册', 'jinyu'));
    }
    if (strlen($pass) < 6) {
        wp_send_json_error(__('密码至少 6 位', 'jinyu'));
    }
    if ($pass !== $pass2) {
        wp_send_json_error(__('两次输入的密码不一致', 'jinyu'));
    }

    if (function_exists('jinyu_captcha_verify')) {
        $verify = jinyu_captcha_verify('register', $captcha);
        if (is_wp_error($verify)) {
            wp_send_json_error($verify->get_error_message());
        }
    }

    $uid = wp_create_user($login, $pass, $email);
    if (is_wp_error($uid)) {
        wp_send_json_error($uid->get_error_message());
    }

    wp_update_user([
        'ID'           => $uid,
        'display_name' => $login,
        'nickname'     => $login,
    ]);

    if (jinyu_is_checked('reg_notify_admin')) {
        wp_new_user_notification($uid, null, 'admin');
    }

    // 默认直接登录，减少一步操作
    wp_clear_auth_cookie();
    wp_set_current_user($uid);
    wp_set_auth_cookie($uid, true, is_ssl());

    wp_send_json_success([
        'message'  => __('注册成功，正在跳转…', 'jinyu'),
        'redirect' => jinyu_login_redirect_url(),
    ]);
}

/* ==========================================================================
   找回密码
   ========================================================================== */
add_action('wp_ajax_nopriv_jinyu_reset_password', 'jinyu_ajax_reset_password');
function jinyu_ajax_reset_password(): void
{
    jinyu_ajax_guard();

    $login   = trim(sanitize_text_field(wp_unslash($_POST['log'] ?? '')));
    $captcha = (string)($_POST['captcha'] ?? '');

    if ($login === '') {
        wp_send_json_error(__('请输入用户名或邮箱', 'jinyu'));
    }

    if (function_exists('jinyu_captcha_verify')) {
        $verify = jinyu_captcha_verify('reset', $captcha);
        if (is_wp_error($verify)) {
            wp_send_json_error($verify->get_error_message());
        }
    }

    $user = is_email($login)
        ? get_user_by('email', $login)
        : get_user_by('login', $login);

    // 不暴露账号是否存在，统一提示
    if (!$user) {
        wp_send_json_success(['message' => __('如账号存在，重置链接已发送至邮箱', 'jinyu')]);
    }

    $key = get_password_reset_key($user);
    if (is_wp_error($key)) {
        wp_send_json_error(__('重置失败，请联系管理员', 'jinyu'));
    }

    $url    = add_query_arg(['key' => $key, 'login' => rawurlencode($user->user_login)], wp_login_url());
    $title  = sprintf(__('[%s] 密码重置', 'jinyu'), wp_specialchars_decode(get_option('blogname'), ENT_QUOTES));
    $body   = sprintf(
        __("有人请求重置以下账号的密码：\n\n用户名：%s\n\n如果不是你本人操作，请忽略本邮件。\n\n点击链接重置密码：\n%s\n", 'jinyu'),
        $user->user_login,
        $url
    );

    wp_mail($user->user_email, $title, $body);

    wp_send_json_success(['message' => __('如账号存在，重置链接已发送至邮箱', 'jinyu')]);
}

/* ==========================================================================
   资料更新
   ========================================================================== */
add_action('wp_ajax_jinyu_update_profile', 'jinyu_ajax_update_profile');
function jinyu_ajax_update_profile(): void
{
    jinyu_ajax_guard();

    $uid = get_current_user_id();
    if (!$uid) {
        wp_send_json_error(__('请先登录', 'jinyu'));
    }

    $current = wp_get_current_user();
    $email   = trim(sanitize_email(wp_unslash($_POST['user_email'] ?? '')));
    $data = [
        'ID'           => $uid,
        'display_name' => sanitize_text_field(wp_unslash($_POST['display_name'] ?? '')),
        'user_url'     => esc_url_raw(wp_unslash($_POST['user_url'] ?? '')),
        'description'  => sanitize_textarea_field(wp_unslash($_POST['description'] ?? '')),
    ];

    if ($data['display_name'] === '') {
        wp_send_json_error(__('昵称不能为空', 'jinyu'));
    }
    if ($email === '') {
        wp_send_json_error(__('邮箱不能为空', 'jinyu'));
    }
    if (!is_email($email)) {
        wp_send_json_error(__('邮箱格式不正确', 'jinyu'));
    }
    if (email_exists($email) && $email !== $current->user_email) {
        wp_send_json_error(__('该邮箱已被其他账号使用', 'jinyu'));
    }

    // 头像：优先上传文件，其次 URL
    if (!empty($_POST['avatar_url'])) {
        $url = esc_url_raw(wp_unslash($_POST['avatar_url']));
        if ($url) {
            update_user_meta($uid, 'jinyu_avatar', $url);
        }
    }

    // 邮箱：与原邮箱相同则直接保存；不同则进入「待验证」流程（发确认链接），不立即修改账号邮箱
    $pending = false;
    if ($email === $current->user_email) {
        $data['user_email'] = $email;
        delete_user_meta($uid, 'jinyu_pending_email'); // 改回原邮箱，清除待验证记录
    } else {
        $token = wp_generate_password(32, false);
        update_user_meta($uid, 'jinyu_pending_email', [
            'email'  => $email,
            'token'  => wp_hash($token, 'jinyu_email_confirm'),
            'expire' => time() + DAY_IN_SECONDS,
        ]);
        jinyu_send_email_confirm($uid, $email, $token);
        $pending = true;
    }

    $uid2 = wp_update_user($data);
    if (is_wp_error($uid2)) {
        wp_send_json_error($uid2->get_error_message());
    }

    if ($pending) {
        wp_send_json_success([
            'message' => sprintf(
                __('验证邮件已发送至 %s，请查收并点击完成绑定。若未收到，请稍后重试。', 'jinyu'),
                $email
            ),
        ]);
    }
    wp_send_json_success(['message' => __('资料已保存', 'jinyu')]);
}

/* ==========================================================================
   邮箱修改确认：发送验证邮件 + 确认落地页
   ========================================================================== */
function jinyu_send_email_confirm(int $uid, string $email, string $token): bool
{
    $url = add_query_arg(
        ['action' => 'jinyu_confirm_email', 'token' => $token],
        admin_url('admin-ajax.php')
    );
    $blog  = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
    $title = sprintf(__('[%s] 确认修改邮箱', 'jinyu'), $blog);
    $body  = sprintf(
        __("你正在申请将账号邮箱修改为：%s\n\n请点击以下链接完成验证（24 小时内有效）：\n%s\n\n如果这不是你本人的操作，请忽略本邮件，原邮箱不受影响。\n", 'jinyu'),
        $email,
        $url
    );
    return wp_mail($email, $title, $body);
}

function jinyu_email_confirm_page(bool $ok, string $msg): void
{
    $blog = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
    $home = home_url('/');
    $color = $ok ? '#16a34a' : '#dc2626';
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!doctype html><html lang="zh-CN"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1"><title>'
        . esc_html($blog) . '</title>'
        . '<style>body{font-family:system-ui,-apple-system,"PingFang SC","Microsoft YaHei",sans-serif;'
        . 'background:#f5f6f8;margin:0;display:flex;min-height:100vh;align-items:center;justify-content:center}'
        . '.card{background:#fff;border-radius:14px;padding:40px 36px;max-width:420px;width:90%;text-align:center;'
        . 'box-shadow:0 8px 30px rgba(0,0,0,.08)}.icon{font-size:46px;margin-bottom:12px}'
        . '.t{font-size:20px;font-weight:600;color:#111827;margin-bottom:10px}'
        . '.m{color:#6b7280;line-height:1.7;margin-bottom:24px;word-break:break-all}'
        . '.a{display:inline-block;background:#3b82f6;color:#fff;text-decoration:none;padding:11px 26px;'
        . 'border-radius:8px;font-size:15px}</style></head><body><div class="card">'
        . '<div class="icon">' . ($ok ? '✅' : '⚠️') . '</div>'
        . '<div class="t">' . ($ok ? esc_html__('邮箱已更新', 'jinyu') : esc_html__('验证失败', 'jinyu')) . '</div>'
        . '<div class="m" style="color:' . $color . '">' . esc_html($msg) . '</div>'
        . '<a class="a" href="' . esc_url($home) . '">' . esc_html__('返回网站', 'jinyu') . '</a>'
        . '</div></body></html>';
    exit;
}

add_action('wp_ajax_nopriv_jinyu_confirm_email', 'jinyu_ajax_confirm_email');
add_action('wp_ajax_jinyu_confirm_email', 'jinyu_ajax_confirm_email');
function jinyu_ajax_confirm_email(): void
{
    $token = (string)($_GET['token'] ?? '');
    if (strlen($token) < 16) {
        jinyu_email_confirm_page(false, __('验证链接无效', 'jinyu'));
    }

    $token_hash = wp_hash($token, 'jinyu_email_confirm');
    $users = get_users(['fields' => 'ID', 'meta_key' => 'jinyu_pending_email']);

    foreach ($users as $uid) {
        $m = get_user_meta($uid, 'jinyu_pending_email', true);
        if (!is_array($m) || empty($m['token']) || empty($m['email'])) {
            continue;
        }
        if (!hash_equals((string)$m['token'], $token_hash)) {
            continue;
        }
        if (!empty($m['expire']) && time() > (int)$m['expire']) {
            delete_user_meta($uid, 'jinyu_pending_email');
            jinyu_email_confirm_page(false, __('验证链接已过期，请重新在账号设置中修改邮箱', 'jinyu'));
        }
        $new_email = $m['email'];
        $cur = get_userdata($uid);
        if (email_exists($new_email) && $new_email !== $cur->user_email) {
            delete_user_meta($uid, 'jinyu_pending_email');
            jinyu_email_confirm_page(false, __('该邮箱已被其他账号绑定，请更换后重试', 'jinyu'));
        }
        wp_update_user(['ID' => $uid, 'user_email' => $new_email]);
        delete_user_meta($uid, 'jinyu_pending_email');
        jinyu_email_confirm_page(true, $new_email);
    }

    jinyu_email_confirm_page(false, __('验证链接无效或已被使用', 'jinyu'));
}

/* ==========================================================================
   修改密码
   ========================================================================== */
add_action('wp_ajax_jinyu_update_password', 'jinyu_ajax_update_password');
function jinyu_ajax_update_password(): void
{
    jinyu_ajax_guard();

    $uid = get_current_user_id();
    if (!$uid) {
        wp_send_json_error(__('请先登录', 'jinyu'));
    }

    $old = (string)($_POST['old_pwd'] ?? '');
    $new = (string)($_POST['new_pwd'] ?? '');
    $cfm = (string)($_POST['new_pwd2'] ?? '');

    $user = wp_get_current_user();
    if (!wp_check_password($old, $user->user_pass, $uid)) {
        wp_send_json_error(__('当前密码不正确', 'jinyu'));
    }
    if (strlen($new) < 6) {
        wp_send_json_error(__('新密码至少 6 位', 'jinyu'));
    }
    if ($new !== $cfm) {
        wp_send_json_error(__('两次输入的新密码不一致', 'jinyu'));
    }

    wp_set_password($new, $uid);
    wp_clear_auth_cookie();
    wp_set_current_user($uid);
    wp_set_auth_cookie($uid, true, is_ssl());

    wp_send_json_success(['message' => __('密码已修改', 'jinyu')]);
}

/* ==========================================================================
   头像上传
   订阅者没有 upload_files 权限，此处自行校验类型与大小后落盘
   ========================================================================== */
add_action('wp_ajax_jinyu_upload_avatar', 'jinyu_ajax_upload_avatar');
function jinyu_ajax_upload_avatar(): void
{
    jinyu_ajax_guard();

    $uid = get_current_user_id();
    if (!$uid) {
        wp_send_json_error(__('请先登录', 'jinyu'));
    }
    if (empty($_FILES['avatar'])) {
        wp_send_json_error(__('请选择图片文件', 'jinyu'));
    }

    $file = $_FILES['avatar'];
    if ($file['size'] > 2 * MB_IN_BYTES) {
        wp_send_json_error(__('图片不能超过 2MB', 'jinyu'));
    }

    // MIME => 扩展名：仅用于白名单校验与生成文件名
    $mime_to_ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $type        = mime_content_type($file['tmp_name']);
    if (!isset($mime_to_ext[$type])) {
        wp_send_json_error(__('仅支持 JPG / PNG / WebP / GIF', 'jinyu'));
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    $upload = wp_handle_upload($file, [
        'test_form' => false,
        // wp_handle_upload 要求 扩展名 => MIME，写反会导致扩展名正则永不匹配、一律拒绝上传
        'mimes'     => array_flip($mime_to_ext),
        'unique_filename_callback' => function () use ($uid, $mime_to_ext, $type) {
            return 'avatar-' . $uid . '-' . wp_generate_password(6, false) . '.' . $mime_to_ext[$type];
        },
    ]);

    if (!empty($upload['error'])) {
        wp_send_json_error($upload['error']);
    }

    update_user_meta($uid, 'jinyu_avatar', $upload['url']);

    wp_send_json_success([
        'message' => __('头像已更新', 'jinyu'),
        'url'     => $upload['url'],
    ]);
}

/* ==========================================================================
   前台投稿
   ========================================================================== */
add_action('wp_ajax_jinyu_submit_post', 'jinyu_ajax_submit_post');
function jinyu_ajax_submit_post(): void
{
    jinyu_ajax_guard();

    $uid = get_current_user_id();
    if (!$uid || !jinyu_is_checked('user_can_submit')) {
        wp_send_json_error(__('无投稿权限', 'jinyu'));
    }
    if (jinyu_submit_rate_limited($uid)) {
        wp_send_json_error(__('投稿过于频繁，请稍后再试', 'jinyu'));
    }

    $title   = trim(sanitize_text_field(wp_unslash($_POST['post_title'] ?? '')));
    $content = trim(wp_unslash($_POST['post_content'] ?? ''));
    $cat     = absint($_POST['post_category'] ?? 0);
    $tags    = trim(sanitize_text_field(wp_unslash($_POST['post_tags'] ?? '')));

    if (mb_strlen($title) < 4 || mb_strlen($title) > 100) {
        wp_send_json_error(__('标题长度需为 4-100 个字符', 'jinyu'));
    }
    if (mb_strlen($content) < 20) {
        wp_send_json_error(__('正文至少 20 个字符', 'jinyu'));
    }

    $pid = wp_insert_post([
        'post_title'    => $title,
        'post_content'  => wp_kses_post($content),
        'post_status'   => 'pending',
        'post_author'   => $uid,
        'post_category' => $cat ? [$cat] : [],
        'tags_input'    => $tags,
    ], true);

    if (is_wp_error($pid)) {
        wp_send_json_error($pid->get_error_message());
    }

    set_transient('jy_submit_' . $uid, 1, 5 * MINUTE_IN_SECONDS);

    wp_send_json_success(['message' => __('投稿成功，请等待审核', 'jinyu')]);
}

/* ==========================================================================
   撤回 / 删除自己的待审核或草稿
   ========================================================================== */
add_action('wp_ajax_jinyu_delete_post', 'jinyu_ajax_delete_post');
function jinyu_ajax_delete_post(): void
{
    jinyu_ajax_guard();

    $uid = get_current_user_id();
    if (!$uid) {
        wp_send_json_error(__('请先登录', 'jinyu'));
    }

    $pid = absint($_POST['post_id'] ?? 0);
    if (!$pid) {
        wp_send_json_error(__('参数错误', 'jinyu'));
    }

    $post = get_post($pid);
    if (!$post || (int)$post->post_author !== $uid) {
        wp_send_json_error(__('无权操作', 'jinyu'));
    }
    // 只允许撤回/删除自己的待审核、草稿，已发布文章禁止前台删除
    if (!in_array($post->post_status, ['pending', 'draft', 'auto-draft'], true)) {
        wp_send_json_error(__('已发布的文章不能在此删除', 'jinyu'));
    }

    $del = wp_delete_post($pid, true);
    if (!$del) {
        wp_send_json_error(__('删除失败，请稍后再试', 'jinyu'));
    }

    wp_send_json_success(['message' => __('已删除', 'jinyu')]);
}

/* ==========================================================================
   收藏列表（用户中心取消收藏后局部刷新用）
   ========================================================================== */
add_action('wp_ajax_jinyu_fav_list', 'jinyu_ajax_fav_list');
function jinyu_ajax_fav_list(): void
{
    jinyu_ajax_guard();

    $uid = get_current_user_id();
    if (!$uid) {
        wp_send_json_error(__('请先登录', 'jinyu'));
    }

    $ids = jinyu_get_user_favs($uid);
    if (!$ids) {
        wp_send_json_success(['html' => '<p class="jinyu-empty">' . esc_html__('还没有收藏任何文章', 'jinyu') . '</p>']);
    }

    ob_start();
    $q = new WP_Query([
        'post__in'            => $ids,
        'post_type'           => 'post',
        'post_status'         => 'publish',
        'posts_per_page'      => count($ids),
        'ignore_sticky_posts' => true,
    ]);
    while ($q->have_posts()) {
        $q->the_post();
        get_template_part('templates/module', 'post');
    }
    wp_reset_postdata();

    wp_send_json_success(['html' => (string)ob_get_clean()]);
}

/* ==========================================================================
   关注 / 取关（用户或系列）
   仅登录用户；不允许关注自己
   ========================================================================== */
add_action('wp_ajax_jinyu_toggle_follow', 'jinyu_ajax_toggle_follow');
function jinyu_ajax_toggle_follow(): void
{
    jinyu_ajax_guard();

    $uid = get_current_user_id();
    if (!$uid) {
        wp_send_json_error(__('请先登录', 'jinyu'));
    }

    if (!function_exists('jinyu_follow_user') || !function_exists('jinyu_is_following') || !function_exists('jinyu_unfollow_user') || !function_exists('jinyu_is_following_term') || !function_exists('jinyu_follow_term') || !function_exists('jinyu_unfollow_term')) {
        wp_send_json_error(__('关注功能未启用', 'jinyu'));
    }

    $target = sanitize_key($_POST['target'] ?? '');
    $id     = absint($_POST['id'] ?? 0);
    if (!in_array($target, ['user', 'term'], true) || !$id) {
        wp_send_json_error(__('参数错误', 'jinyu'));
    }

    if ($target === 'user') {
        if ($id === $uid) {
            wp_send_json_error(__('不能关注自己', 'jinyu'));
        }
        if (!get_userdata($id)) {
            wp_send_json_error(__('用户不存在', 'jinyu'));
        }
        if (jinyu_is_following($uid, $id)) {
            jinyu_unfollow_user($uid, $id);
            wp_send_json_success(['following' => false]);
        }
        jinyu_follow_user($uid, $id);
        wp_send_json_success(['following' => true]);
    } else {
        if (jinyu_is_following_term($uid, $id)) {
            jinyu_unfollow_term($uid, $id);
            wp_send_json_success(['following' => false]);
        }
        jinyu_follow_term($uid, $id);
        wp_send_json_success(['following' => true]);
    }
}

/* ==========================================================================
   消息列表（用户中心「消息」tab 拉取）
   ========================================================================== */
add_action('wp_ajax_jinyu_get_notifications', 'jinyu_ajax_get_notifications');
function jinyu_ajax_get_notifications(): void
{
    jinyu_ajax_guard();

    $uid = get_current_user_id();
    if (!$uid) {
        wp_send_json_error(__('请先登录', 'jinyu'));
    }

    if (!function_exists('jinyu_get_notifications') || !function_exists('jinyu_get_unread_count')) {
        wp_send_json_error(__('消息功能未启用', 'jinyu'));
    }

    $page = max(1, absint($_POST['page'] ?? 1));
    $list = jinyu_get_notifications($uid, $page, 20);

    $html = '';
    foreach ($list as $n) {
        $n = (array)$n;
        $is_read = (int)$n['is_read'] === 1;
        $html .= '<li class="jinyu-notif-item' . ($is_read ? ' is-read' : '') . '" data-notif-id="' . (int)$n['id'] . '">';
        if (!empty($n['link'])) {
            $html .= '<a class="jinyu-notif-link" href="' . esc_url($n['link']) . '">';
        }
        $html .= '<div class="jinyu-notif-body">';
        $html .= '<p class="jinyu-notif-title">' . esc_html($n['title']) . '</p>';
        if ($n['content']) {
            $html .= '<p class="jinyu-notif-content">' . esc_html($n['content']) . '</p>';
        }
        $html .= '<p class="jinyu-notif-time">' . esc_html(mysql2date('Y-m-d H:i', $n['created_at'])) . '</p>';
        $html .= '</div>';
        if (!empty($n['link'])) {
            $html .= '</a>';
        }
        $html .= '</li>';
    }

    wp_send_json_success([
        'html'     => $html ?: '<li class="jinyu-empty">' . esc_html__('暂无消息', 'jinyu') . '</li>',
        'unread'   => jinyu_get_unread_count($uid),
        'has_more' => count($list) === 20,
    ]);
}

/* ==========================================================================
   标记消息已读（全部或指定 id）
   ========================================================================== */
add_action('wp_ajax_jinyu_mark_read', 'jinyu_ajax_mark_read');
function jinyu_ajax_mark_read(): void
{
    jinyu_ajax_guard();

    $uid = get_current_user_id();
    if (!$uid) {
        wp_send_json_error(__('请先登录', 'jinyu'));
    }

    if (!function_exists('jinyu_mark_read') || !function_exists('jinyu_get_unread_count')) {
        wp_send_json_error(__('消息功能未启用', 'jinyu'));
    }

    $ids = [];
    if (!empty($_POST['ids']) && is_array($_POST['ids'])) {
        $ids = array_map('absint', $_POST['ids']);
    }
    jinyu_mark_read($uid, $ids);

    wp_send_json_success(['unread' => jinyu_get_unread_count($uid)]);
}
