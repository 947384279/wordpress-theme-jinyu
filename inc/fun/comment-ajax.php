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
            ? __('评论已发布', JINYU)
            : __('评论已提交，等待审核', JINYU),
    ]);
}
