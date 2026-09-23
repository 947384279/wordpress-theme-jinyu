<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if (post_password_required()) return;
if (!comments_open() && get_comments_number() < 1) return;

$comment_smiley_html = '';
if (jinyu_is_checked('comment_smiley')) {
    $comment_smiley_html = '<button type="button" class="jinyu-comment-smiley-btn" aria-label="' . esc_attr__('插入表情', 'jinyu') . '" aria-expanded="false" aria-controls="jinyu-smiley-panel"><i class="fa-regular fa-face-smile" aria-hidden="true"></i></button>'
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
            <?php
            // 问候语：头像 + 欢迎文案。
            // 头像优先级：登录用户 Gravatar > 老访客（评论者 Cookie 邮箱）Gravatar > emoji 兜底
            $jinyu_cur_user  = wp_get_current_user();
            $jinyu_commenter = wp_get_current_commenter();
            if ($jinyu_cur_user->exists()) {
                $jinyu_greet_avatar = get_avatar($jinyu_cur_user->ID, 88, '', $jinyu_cur_user->display_name, ['class' => 'jinyu-greet-ava']);
                $jinyu_greet_name   = $jinyu_cur_user->display_name;
            } elseif ($jinyu_commenter['comment_author_email']) {
                $jinyu_greet_avatar = get_avatar($jinyu_commenter['comment_author_email'], 88, '', $jinyu_commenter['comment_author'], ['class' => 'jinyu-greet-ava']);
                $jinyu_greet_name   = $jinyu_commenter['comment_author'];
            } else {
                $jinyu_greet_avatar = '<i class="fa-regular fa-face-smile" aria-hidden="true"></i>';
                $jinyu_greet_name   = '';
            }
            if ($jinyu_greet_name !== '') {
                $jinyu_greet_main = sprintf(wp_kses(__('欢迎回来，<em>%s</em>，感谢参与互动！', 'jinyu'), ['em' => []]), esc_html($jinyu_greet_name));
            } else {
                $jinyu_greet_main = wp_kses(__('欢迎你，<em>新朋友</em>，感谢参与互动！', 'jinyu'), ['em' => []]);
            } ?>
            <div class="jinyu-comment-greet">
                <span class="jinyu-comment-greet-avatar" aria-hidden="true"><?php echo $jinyu_greet_avatar; // phpcs:ignore -- get_avatar 输出已转义 ?></span>
                <div class="jinyu-comment-greet-txt">
                    <span class="jinyu-comment-greet-main"><?php echo $jinyu_greet_main; // phpcs:ignore -- 仅含白名单 em 标签 ?></span>
                    <span class="jinyu-comment-greet-sub"><?php esc_html_e('文明发言，理性交流 · 首次评论将在审核后展示', 'jinyu'); ?></span>
                </div>
            </div>
            <?php
            // 未登录用户：后台验证码策略要求时，在评论表单内注入图形验证码
            // （comment_form_after_fields 仅对访客触发，登录用户不显示、服务端亦跳过校验）
            add_action('comment_form_after_fields', function () {
                echo function_exists('jinyu_captcha_markup') ? jinyu_captcha_markup('comment') : '';
            });
            $commenter = wp_get_current_commenter();
            $req       = get_option('require_name_email');
            $aria_req  = $req ? ' aria-required="true"' : '';
            // hairline 分栏字段：称呼 | 邮箱 | 网址，聚焦时主色下划线动效（无可见方框）
            $fields = [
                'author' => '<div class="jinyu-cf-row"><div class="jinyu-cf-field">'
                    . '<input id="author" name="author" type="text" value="' . esc_attr($commenter['comment_author']) . '" placeholder="' . esc_attr__('称呼', 'jinyu') . '" aria-label="' . esc_attr__('称呼', 'jinyu') . '"' . $aria_req . '></div>',
                'email'  => '<div class="jinyu-cf-field">'
                    . '<input id="email" name="email" type="email" value="' . esc_attr($commenter['comment_email']) . '" placeholder="' . esc_attr__('邮箱（仅站长可见）', 'jinyu') . '" aria-label="' . esc_attr__('邮箱', 'jinyu') . '"' . $aria_req . '></div>',
                'url'    => '<div class="jinyu-cf-field">'
                    . '<input id="url" name="url" type="url" value="' . esc_attr($commenter['comment_url']) . '" placeholder="' . esc_attr__('网址（可选）', 'jinyu') . '" aria-label="' . esc_attr__('网址', 'jinyu') . '"></div></div>',
            ];
            comment_form([
                // 标题由外层问候行承担；不再输出「发表回复」，回复态仅显示「取消回复」链接
                'title_reply'        => '',
                'title_reply_to'     => '',
                'title_reply_before' => '',
                'title_reply_after'  => '',
                'fields'             => $fields,
                'comment_field'      => '<div class="jinyu-comment-editor"><p class="comment-form-comment"><textarea id="comment" name="comment" rows="4" placeholder="' . esc_attr__('说说你的看法…', 'jinyu') . '" aria-label="' . esc_attr__('评论内容', 'jinyu') . '" required></textarea></p></div>',
                'label_submit'       => __('提交评论', 'jinyu'),
                'submit_button'      => '<input name="%1$s" type="submit" id="%2$s" class="%3$s" value="%4$s">',
                // 底部工具栏：表情按钮 + 字数计数 + 提交（隐藏的评论元字段 %2$s 一并收进工具栏）
                'submit_field'       => '<div class="jinyu-comment-toolbar">' . $comment_smiley_html . '<span class="jinyu-comment-counter" data-jinyu-counter>0 / 1000</span>%1$s %2$s</div>',
                'logged_in_as'       => '',
                'comment_notes_before' => '',
                'comment_notes_after'  => '',
            ]); ?>
        </div>
    <?php endif; ?>
</section>