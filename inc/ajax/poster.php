<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action('wp_ajax_jinyu_poster', 'jinyu_poster_generate');
add_action('wp_ajax_nopriv_jinyu_poster', 'jinyu_poster_generate');
function jinyu_poster_generate()
{
    $post_id = isset($_REQUEST['post_id']) ? intval($_REQUEST['post_id']) : 0;
    if (!$post_id) wp_send_json_error('invalid');
    $post = get_post($post_id);
    if (!$post) wp_send_json_error('not found');

    // 防滥用：每 IP 速率限制，避免匿名用户频繁触发 GD 图像生成消耗服务器资源
    if (!jinyu_rate_limit_check('poster', 20, MINUTE_IN_SECONDS)) {
        wp_send_json_error('请求过于频繁，请稍后再试');
    }

    $cache_key = 'jinyu_poster_' . $post_id;
    $cached = get_transient($cache_key);
    // 仅当本地文件真实存在时复用缓存（旧版纯 URL transient 会被跳过并重建为新结构）
    if (is_array($cached) && !empty($cached['file']) && file_exists($cached['file'])) {
        wp_send_json_success(['url' => $cached['url']]);
    }
    if (!extension_loaded('gd')) wp_send_json_error('GD 库未启用');

    $w = 750; $h = 1000;
    $img = imagecreatetruecolor($w, $h);
    $primary = jinyu_get_option('style_color_primary', '#FF6B35');
    list($r,$g,$b) = sscanf(ltrim($primary,'#'),'%02x%02x%02x');
    $bg = imagecolorallocate($img, 247, 248, 250);
    $accent = imagecolorallocate($img, $r, $g, $b);
    $white = imagecolorallocate($img, 255, 255, 255);
    $title_c = imagecolorallocate($img, 17, 19, 23);
    $site_c = imagecolorallocate($img, 107, 114, 128);

    imagefill($img, 0, 0, $bg);
    imagefilledrectangle($img, 0, 0, $w, 200, $accent);

    $cover_url = jinyu_get_post_cover($post_id, 'large');
    if ($cover_url) {
        $cover_data = file_get_contents($cover_url);
        if ($cover_data) {
            // imagecreatefromstring 对损坏数据会发告警，此处仅抑制该调用的告警（结果已显式判断）
            set_error_handler(static function () { return true; });
            $cover = imagecreatefromstring($cover_data);
            restore_error_handler();
            if ($cover) {
                $cw = imagesx($cover); $ch = imagesy($cover);
                imagecopyresampled($img, $cover, 50, 250, 0, 0, 650, 400, $cw, $ch);
                imagedestroy($cover);
            }
        }
    }

    $font = null;
    $font_candidates = [
        ABSPATH . 'wp-includes/fonts/opensans/OpenSans-Regular.ttf',
        JINYU_ABS_DIR . '/assets/fonts/poster.ttf',
        'C:/Windows/Fonts/msyh.ttc',
        'C:/Windows/Fonts/msyh.ttf',
        'C:/Windows/Fonts/arial.ttf',
        '/System/Library/Fonts/PingFang.ttc',
        '/System/Library/Fonts/Supplemental/Arial.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
    ];
    foreach ($font_candidates as $c) {
        if ($c && file_exists($c)) { $font = $c; break; }
    }
    if (function_exists('imagettftext') && file_exists($font)) {
        imagettftext($img, 28, 0, 50, 720, $title_c, $font, $post->post_title);
        imagettftext($img, 16, 0, 50, 850, $site_c, $font, get_bloginfo('name'));
        imagettftext($img, 14, 0, 50, 930, $site_c, $font, get_permalink($post_id));
    } else {
        imagestring($img, 5, 50, 700, substr($post->post_title,0,40), $title_c);
    }

    $upload = wp_upload_dir();
    $file = $upload['basedir'] . '/jinyu-poster-' . $post_id . '.png';
    imagepng($img, $file);
    imagedestroy($img);

    $url = str_replace($upload['basedir'], $upload['baseurl'], $file);
    set_transient($cache_key, ['url' => $url, 'file' => $file], HOUR_IN_SECONDS);
    wp_send_json_success(['url' => $url]);
}