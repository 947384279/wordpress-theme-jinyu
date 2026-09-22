<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if (post_password_required()) return;
if (!comments_open() && get_comments_number() < 1) return;

$comment_smiley_html = '';
if (jinyu_is_checked('comment_smiley')) {
    $comment_smiley_html = '<button type="button" class="jinyu-comment-smiley-btn" aria-label="' . esc_attr__('插入表情', 'jinyu') . '"><i class="fa-regular fa-face-smile" aria-hidden="true"></i></button>'
        . '<div class="jinyu-smiley-panel" id="jinyu-smiley-panel" hidden></div>';
}
?>
<section class="comments-area" id="comments">
    <h3 class="jinyu-widget-title">
        <?php printf(__('评论 (%d)', 'jinyu'), get_comments_number()); ?>
    </h3>
    <?php get_template_part('ad/comment-top'); ?>
    <?php if (have_comments()): ?>
        <ol class="comment-list jinyu-comment-list" data-post-id="<?php the_ID(); ?>">
            <?php wp_list_comments(['style'=>'ol','callback'=>'jinyu_wp_comment','avatar_size'=>48]); ?>
        </ol>
        <?php
        $jinyu_lazy_comments = ! empty( jinyu_perf_get_options()['comment_lazyload'] ) && get_comment_pages_count() > 1;
        if ( $jinyu_lazy_comments ) :
            $jinyu_cm_max = (int) get_comment_pages_count();
        ?>
        <style>.jinyu-comments-more{display:block;margin:16px auto 4px;padding:9px 24px;border:1px solid var(--j-line,#e5e7eb);background:var(--j-bg,#fff);color:var(--j-accent,#4f46e5);border-radius:999px;cursor:pointer;font-size:14px;transition:transform .12s ease,box-shadow .12s ease}.jinyu-comments-more:hover{transform:translateY(-1px);box-shadow:0 4px 14px rgba(79,70,229,.16)}.jinyu-comments-more:active{transform:translateY(0)}</style>
        <button type="button" class="jinyu-comments-more" data-post-id="<?php the_ID(); ?>" data-page="2" data-max="<?php echo $jinyu_cm_max; ?>" data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
            <span class="jinyu-comments-more-txt"><?php esc_html_e( '加载更多评论', 'jinyu'); ?></span>
        </button>
        <?php endif; ?>
        <?php the_comments_pagination(['prev_text'=>'‹','next_text'=>'›']); ?>
    <?php endif; ?>

    <?php if (comments_open()): ?>
        <div id="respond" class="jinyu-comment-respond">
            <h3 class="jinyu-widget-title"><?php comment_form_title(); ?></h3>
            <?php
            // 未登录用户：后台验证码策略要求时，在评论表单内注入图形验证码
            // （comment_form_after_fields 仅对访客触发，登录用户不显示、服务端亦跳过校验）
            add_action('comment_form_after_fields', function () {
                echo function_exists('jinyu_captcha_markup') ? jinyu_captcha_markup('comment') : '';
            });
            comment_form([
                'title_reply_before' => '',
                'title_reply_after'  => '',
                'comment_field'      => $comment_smiley_html . '<p class="comment-form-comment"><textarea id="comment" name="comment" rows="4" placeholder="' . esc_attr__('说点什么吧...', 'jinyu') . '" required></textarea></p>',
                'label_submit'       => __('发表评论', 'jinyu'),
                'submit_button'      => '<input name="%1$s" type="submit" id="%2$s" class="%3$s" value="%4$s">',
                'submit_field'       => '<p class="form-submit">%1$s %2$s</p>',
                'logged_in_as'       => '',
                'comment_notes_before' => '',
                'comment_notes_after'  => '',
            ]); ?>
        </div>
    <?php endif; ?>
</section>