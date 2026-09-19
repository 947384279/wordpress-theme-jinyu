<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 评论邮件通知
 * 回复邮件 + 评论作者通知
 */

add_action('wp_insert_comment', 'jinyu_comment_notify', 10, 2);
function jinyu_comment_notify($comment_id, $comment_approved)
{
    if ($comment_approved != 1) return;

    $comment = get_comment($comment_id);
    if (!$comment || $comment->comment_type === 'pingback' || $comment->comment_type === 'trackback') return;

    $post = get_post($comment->comment_post_ID);
    if (!$post) return;

    $subject = sprintf('[%s] %s', get_bloginfo('name'), __('有新的评论回复', JINYU));
    $headers = ['Content-Type: text/html; charset=UTF-8'];

    // 如果有父评论 -> 通知父评论作者
    if ($comment->comment_parent > 0) {
        $parent = get_comment($comment->comment_parent);
        if ($parent && $parent->comment_author_email && $parent->comment_author_email !== $comment->comment_author_email) {
            $link = get_comment_link($comment_id);
            $body = '<p>' . sprintf(__('你在《%s》下的评论有了新回复', JINYU), $post->post_title) . '</p>'
                   . '<blockquote>' . esc_html($parent->comment_content) . '</blockquote>'
                   . '<p><strong>' . esc_html($comment->comment_author) . '：</strong>' . esc_html($comment->comment_content) . '</p>'
                   . '<p><a href="' . esc_url($link) . '">' . __('查看回复', JINYU) . '</a></p>';
            wp_mail($parent->comment_author_email, $subject, $body, $headers);
        }
    }

    // 通知文章作者
    $post_author_email = get_userdata($post->post_author)->user_email;
    if ($post_author_email && $post_author_email !== $comment->comment_author_email) {
        $link = get_permalink($post->ID);
        $body = '<p>' . sprintf(__('你的文章《%s》有新评论', JINYU), $post->post_title) . '</p>'
               . '<p><strong>' . esc_html($comment->comment_author) . '：</strong>' . esc_html($comment->comment_content) . '</p>'
               . '<p><a href="' . esc_url($link) . '">' . __('查看文章', JINYU) . '</a></p>';
        wp_mail($post_author_email, $subject, $body, $headers);
    }
}

// SMTP 接管统一由 inc/fun/email.php 的 Jinyu_SmtpConfig 在 phpmailer_init 钩子完成，
// 此处不再重复注册，避免两个钩子相互覆盖（尤其会冲掉测试邮件的临时覆盖配置）。

