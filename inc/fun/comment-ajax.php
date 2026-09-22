<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ajax 评论提交
 * 复用 WordPress 内核的 wp_handle_comment_submission()，
 * 由内核统一负责校验、限流、去重、审核状态与评论者 cookie，避免自行造轮子。
 */

add_action('wp_ajax_jinyu_comment', 'jinyu_ajax_comment');
add_action('wp_ajax_nopriv_jinyu_comment', 'jinyu_ajax_comment');
function jinyu_ajax_comment()
{
    check_ajax_referer('jinyu_front', '_ajax_nonce');

    // 访客评论服务端校验图形验证码：杜绝匿名刷评（登录用户免验证）
    if (!is_user_logged_in()) {
        if (function_exists('jinyu_captcha_verify')) {
            $captcha = (string)($_POST['captcha'] ?? '');
            $verify  = jinyu_captcha_verify('comment', $captcha);
            if (is_wp_error($verify)) {
                wp_send_json_error($verify->get_error_message(), 400);
            }
        }
    }

    $comment = wp_handle_comment_submission(wp_unslash($_POST));

    if (is_wp_error($comment)) {
        wp_send_json_error($comment->get_error_message(), 400);
    }

    $approved = ($comment->comment_approved === '1');

    // 渲染单条评论 HTML，供前端直接追加到列表尾部
    $html = '';
    if ($approved) {
        ob_start();
        jinyu_wp_comment($comment, [
            'max_depth' => (int)get_option('thread_comments_depth', 5),
            'style'     => 'ol',
        ], 1);
        $html = (string)ob_get_clean();
    }

    wp_send_json_success([
        'html'    => $html,
        'message' => $approved
            ? __('评论已发布', 'jinyu')
            : __('评论已提交，等待审核', 'jinyu'),
    ]);
}
