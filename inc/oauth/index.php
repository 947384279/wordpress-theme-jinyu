<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 读取已配置的第三方登录账号（来自后台「第三方登录账号」dynamic-list）。
 * 兼容两种存储形态：JSON 字符串（保存落库形态）与数组；同时回退升级前的
 * oauth_qq_key / oauth_github_key / oauth_gitee_key 旧字段，避免配置丢失。
 * 返回的 client_secret 仍为密文，由 jinyu_oauth_get_config() 按需解密。
 */
function jinyu_get_oauth_accounts(): array
{
    $raw = jinyu_get_option('oauth_accounts', []);
    if (is_string($raw)) {
        $dec = json_decode($raw, true);
        $raw = is_array($dec) ? $dec : [];
    }
    if (!is_array($raw)) {
        $raw = [];
    }
    $out = [];
    foreach ($raw as $a) {
        if (!is_array($a)) {
            continue;
        }
        $p = $a['platform'] ?? '';
        if (!in_array($p, ['qq', 'github', 'gitee'], true)) {
            continue;
        }
        $out[] = [
            'platform'      => $p,
            'client_id'     => $a['client_id'] ?? '',
            'client_secret' => $a['client_secret'] ?? '',
        ];
    }
    // 回退：升级前逐项配置的旧字段
    foreach (['qq', 'github', 'gitee'] as $p) {
        $legacy_id = jinyu_get_option("oauth_{$p}_key", '');
        if ($legacy_id !== '' && !in_array($p, array_column($out, 'platform'), true)) {
            $out[] = [
                'platform'      => $p,
                'client_id'     => $legacy_id,
                'client_secret' => jinyu_get_option("oauth_{$p}_secret", ''),
            ];
        }
    }
    return $out;
}

add_action('init', 'jinyu_oauth_init');
function jinyu_oauth_init()
{
    if (!jinyu_get_option('oauth_enable', false)) return;
    add_rewrite_rule('oauth/([a-z]+)/?$', 'index.php?jinyu_oauth=$matches[1]', 'top');
    add_rewrite_rule('oauth/callback/?$', 'index.php?jinyu_oauth_callback=1', 'top');
    add_filter('query_vars', 'jinyu_oauth_query_vars');
    add_action('template_redirect', 'jinyu_oauth_handler');
}

function jinyu_oauth_query_vars($vars)
{
    $vars[] = 'jinyu_oauth';
    $vars[] = 'jinyu_oauth_callback';
    return $vars;
}

function jinyu_oauth_handler()
{
    $action = get_query_var('jinyu_oauth');
    if ($action && in_array($action, ['qq', 'github', 'gitee'])) { jinyu_oauth_redirect($action); exit; }
    if (get_query_var('jinyu_oauth_callback')) { jinyu_oauth_process_callback(); exit; }
}

function jinyu_oauth_redirect($platform)
{
    $cfg = jinyu_oauth_get_config($platform);
    if (!$cfg['client_id']) wp_die('请先在后台配置 ' . strtoupper($platform) . ' OAuth');
    if (!session_id()) session_start();
    $_SESSION['jy_oauth_plat'] = $platform;
    $state = bin2hex(random_bytes(16));
    $_SESSION['jy_oauth_state'] = $state;
    $callback = site_url('oauth/callback/');
    $urls = ['qq'=>'https://graph.qq.com/oauth2.0/authorize','github'=>'https://github.com/login/oauth/authorize','gitee'=>'https://gitee.com/oauth/authorize'];
    $p = ['client_id'=>$cfg['client_id'],'redirect_uri'=>$callback,'state'=>$state];
    if ($platform==='github') $p['scope']='user:email read:user';
    wp_redirect(add_query_arg($p, $urls[$platform])); exit;
}

function jinyu_oauth_process_callback()
{
    if (!session_id()) session_start();
    $platform = $_SESSION['jy_oauth_plat'] ?? '';
    $state    = $_SESSION['jy_oauth_state'] ?? '';
    $get_state = sanitize_text_field(wp_unslash($_GET['state'] ?? ''));
    // 一次性消费，避免重放
    unset($_SESSION['jy_oauth_plat'], $_SESSION['jy_oauth_state']);

    // 校验 state：防御 CSRF 伪造 OAuth 回调，比对失败直接拒绝
    if (!$platform || $state === '' || !hash_equals($state, $get_state)) {
        wp_redirect(wp_login_url()); exit;
    }

    $code = sanitize_text_field(wp_unslash($_GET['code'] ?? ''));
    if (!$code) { wp_redirect(wp_login_url()); exit; }

    $cfg = jinyu_oauth_get_config($platform);
    $body = ['client_id'=>$cfg['client_id'],'client_secret'=>$cfg['client_secret'],'code'=>$code,'redirect_uri'=>site_url('oauth/callback/')];
    $tokenUrl = ['qq'=>'https://graph.qq.com/oauth2.0/token','github'=>'https://github.com/login/oauth/access_token','gitee'=>'https://gitee.com/oauth/token'][$platform];
    $r = wp_remote_post($tokenUrl, ['body'=>$body,'headers'=>['Accept'=>'application/json'],'timeout'=>15]);
    if (is_wp_error($r)) { wp_redirect(wp_login_url()); exit; }
    $token = json_decode(wp_remote_retrieve_body($r), true)['access_token'] ?? '';
    if (!$token) { wp_redirect(wp_login_url()); exit; }

    $user = jinyu_oauth_get_userinfo($platform, $token);
    if (!$user) { wp_redirect(wp_login_url()); exit; }

    $uid = jinyu_oauth_find_or_create($platform, $user);
    if (!is_wp_error($uid)) { wp_set_current_user($uid); wp_set_auth_cookie($uid, true); }
    wp_redirect(home_url()); exit;
}

function jinyu_oauth_get_config($p) {
    foreach (jinyu_get_oauth_accounts() as $a) {
        if (($a['platform'] ?? '') === $p) {
            return ['client_id' => $a['client_id'] ?? '', 'client_secret' => jinyu_decrypt($a['client_secret'] ?? '')];
        }
    }
    // 兼容升级前的旧字段（已明文读取，无需解密）
    $legacy_id = jinyu_get_option("oauth_{$p}_key", '');
    if ($legacy_id !== '') {
        return ['client_id' => $legacy_id, 'client_secret' => jinyu_get_option("oauth_{$p}_secret", '')];
    }
    return ['client_id' => '', 'client_secret' => ''];
}

function jinyu_oauth_get_userinfo($platform, $token)
{
    $headers = ['Accept'=>'application/json'];
    if ($platform==='github') $headers['Authorization'] = 'Bearer ' . $token;
    $urls = [
        'qq'     => 'https://graph.qq.com/oauth2.0/me?access_token=' . $token . '&fmt=json',
        'github' => 'https://api.github.com/user',
        'gitee'  => 'https://gitee.com/api/v5/user?access_token=' . $token,
    ];
    $r = wp_remote_get($urls[$platform], ['headers'=>$headers,'timeout'=>15]);
    if (is_wp_error($r)) return false;
    $d = json_decode(wp_remote_retrieve_body($r), true);

    if ($platform==='qq') {
        $openid = $d['openid'] ?? '';
        $r2 = wp_remote_get('https://graph.qq.com/user/get_user_info?access_token='.$token.'&openid='.$openid);
        if (is_wp_error($r2)) return false;
        $q = json_decode(wp_remote_retrieve_body($r2), true);
        return ['id'=>$openid,'nickname'=>$q['nickname']??'','avatar'=>$q['figureurl_qq_2']??'','email'=>'','platform'=>'qq'];
    }
    if ($platform==='github') return ['id'=>(string)($d['id']??''),'nickname'=>$d['name']??$d['login']??'','avatar'=>$d['avatar_url']??'','email'=>$d['email']??'','platform'=>'github'];
    if ($platform==='gitee')  return ['id'=>(string)($d['id']??''),'nickname'=>$d['name']??$d['login']??'','avatar'=>$d['avatar_url']??'','email'=>$d['email']??'','platform'=>'gitee'];
    return false;
}

function jinyu_oauth_find_or_create($platform, $ud)
{
    global $wpdb;
    $mk = 'jinyu_oauth_' . $platform . '_id';
    $existing = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM $wpdb->usermeta WHERE meta_key=%s AND meta_value=%s", $mk, $ud['id']));
    if ($existing) return $existing;
    if (!empty($ud['email'])) {
        $uid = email_exists($ud['email']);
        if ($uid) { update_user_meta($uid, $mk, $ud['id']); return $uid; }
    }
    $username = sanitize_user($platform . '_' . $ud['id']);
    if (username_exists($username)) $username .= '_' . wp_generate_password(4, false);
    $fallback_mail = $platform . '_' . $ud['id'] . '@' . parse_url(home_url(), PHP_URL_HOST);
    $uid = wp_create_user($username, wp_generate_password(16, true), $ud['email'] ?: $fallback_mail);
    if (is_wp_error($uid)) return false;
    wp_update_user(['ID'=>$uid,'display_name'=>$ud['nickname']?:$username,'nickname'=>$ud['nickname']?:$username]);
    update_user_meta($uid, $mk, $ud['id']);
    if (!empty($ud['avatar'])) update_user_meta($uid, 'jinyu_oauth_avatar', $ud['avatar']);
    return $uid;
}

add_shortcode('jinyu_oauth', 'jinyu_oauth_shortcode');
function jinyu_oauth_shortcode()
{
    if (is_user_logged_in() || !jinyu_get_option('oauth_enable', false)) return '';
    $labels = ['qq' => 'QQ', 'github' => 'Github', 'gitee' => 'Gitee'];
    $out = '<div class="jinyu-oauth-btns">';
    foreach (jinyu_get_oauth_accounts() as $a) {
        $p = $a['platform'] ?? '';
        if (!$p || !($a['client_id'] ?? '')) {
            continue;
        }
        $label = $labels[$p] ?? $p;
        $out .= '<a class="jinyu-oauth-btn jinyu-oauth-' . $p . '" href="' . esc_url(site_url('oauth/' . $p . '/')) . '">' . $label . ' 登录</a>';
    }
    $out .= '</div>';
    return $out;
}