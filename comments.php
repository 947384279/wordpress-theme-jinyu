<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if (post_password_required()) return;
if (!comments_open() && get_comments_number() < 1) return;

$comment_smiley_html = '';
if (jinyu_is_checked('comment_smiley')) {
    $comment_smiley_html = '<button type="button" class="jinyu-comment-smiley-btn" aria-label="' . esc_attr__('插入表情', JINYU) . '"><i class="fa-regular fa-face-smile" aria-hidden="true"></i></button>'
        . '<div class="jinyu-smiley-panel" id="jinyu-smiley-panel" hidden></div>';
}
?>
<section class="comments-area" id="comments">
    <h3 class="jinyu-widget-title">
        <?php printf(__('评论 (%d)', JINYU), get_comments_number()); ?>
    </h3>
    <?php get_template_part('ad/comment-top'); ?>
    <?php if (have_comments()): ?>
        <ol class="comment-list jinyu-comment-list">
            <?php wp_list_comments(['style'=>'ol','callback'=>'jinyu_wp_comment','avatar_size'=>48]); ?>
        </ol>
        <?php the_comments_pagination(['prev_text'=>'‹','next_text'=>'›']); ?>
    <?php endif; ?>

    <?php if (comments_open()): ?>
        <div id="respond" class="jinyu-comment-respond">
            <h3 class="jinyu-widget-title"><?php comment_form_title(); ?></h3>
            <?php comment_form([
                'title_reply_before' => '',
                'title_reply_after'  => '',
                'comment_field'      => $comment_smiley_html . '<p class="comment-form-comment"><textarea id="comment" name="comment" rows="4" placeholder="' . esc_attr__('说点什么吧...', JINYU) . '" required></textarea></p>',
                'label_submit'       => __('发表评论', JINYU),
                'submit_button'      => '<input name="%1$s" type="submit" id="%2$s" class="%3$s" value="%4$s">',
                'submit_field'       => '<p class="form-submit">%1$s %2$s</p>',
                'logged_in_as'       => '',
                'comment_notes_before' => '',
                'comment_notes_after'  => '',
            ]); ?>
        </div>
    <?php endif; ?>
</section>