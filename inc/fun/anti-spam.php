<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter('pre_comment_approved', 'jinyu_anti_spam', 99, 2);
function jinyu_anti_spam($approved, $commentdata)
{
    if (!jinyu_get_option('anti_spam_enable', true)) return $approved;
    if (current_user_can('manage_options')) return $approved;

    // 关键词过滤
    $words = jinyu_get_option('anti_spam_words', '彩票,色情,赌博,代写,刷量');
    $text  = $commentdata['comment_content'] ?? '';
    foreach (explode(',', $words) as $w) {
        $w = trim($w);
        if ($w && stripos($text, $w) !== false) return 'spam';
    }

    // 频率限制 (10分钟内同 IP 超过 5 条)
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $transient = 'jy_comment_ips_' . md5($ip);
    $count = get_transient($transient) ?: 0;
    if ($count >= 5) return 'spam';
    set_transient($transient, $count + 1, 600);

    // 长度限制
    if (mb_strlen($text) > 2000) return 'spam';
    if (mb_strlen($text) < 2 && !is_user_logged_in()) return 'spam';

    return $approved;
}

// 自动关闭超过 30 天文章的评论
add_action('init', function(){
    if (jinyu_get_option('close_comments_old', true)) {
        add_filter('comments_open', function($open, $pid){
            $days = jinyu_get_option('close_comments_days', 30);
            $post = get_post($pid);
            if ($post && (time() - strtotime($post->post_date)) > $days * 86400) return false;
            return $open;
        }, 10, 2);
    }
});