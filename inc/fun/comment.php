<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 评论系统：列表渲染回调 + 表单字段定制
 */

/* 全站关闭评论（后台「全局设置 › 关闭全站评论功能」）；已有评论仍保留展示 */
add_filter('comments_open', function ($open) {
    return jinyu_is_checked('close_post_comment') ? false : $open;
}, 99);

if (!function_exists('jinyu_comment_avatar')) {
    /**
     * 按后台「评论头像来源」返回头像 HTML
     * - gravatar：默认（含主题自定义的微信/QQ头像、Gravatar；无头像者前端兜底首字母图）
     * - letter：始终用首字母占位 SVG，不依赖 Gravatar 服务器
     */
    function jinyu_comment_avatar($comment): string
    {
        if (jinyu_get_option('comment_avatar_src', 'gravatar') === 'letter') {
            $uid = (int) $comment->user_id;
            $src = $uid ? jinyu_avatar_default($uid) : jinyu_letter_avatar((string) $comment->comment_author, 48);
            // data URI 必须用 esc_attr：esc_url 会把 data: 协议整条清空 → src="" 破图
            return '<img class="jinyu-avatar-img" src="' . esc_attr($src) . '" alt="">';
        }
        // gravatar/国内镜像模式：d=404 探测 —— 评论者没设过 Gravatar 时源站返回 404，
        // 前端经 data-jinyu-fallback（jinyu.js 图片兜底模块）换成首字母占位图，
        // 替代 WP 默认的灰色「神秘人」剪影，让无头像评论者也有稳定视觉。
        // 有真实头像的评论者不受影响（200 正常加载，兜底不触发）。
        $fallback = jinyu_letter_avatar((string) $comment->comment_author, 48);
        return get_avatar($comment, 48, '404', (string) $comment->comment_author, [
            'class'      => 'jinyu-avatar-img',
            'loading'    => 'lazy',
            'extra_attr' => 'data-jinyu-fallback="' . esc_attr($fallback) . '"',
        ]);
    }
}

if (!function_exists('jinyu_comment_excerpt')) {
    /**
     * 评论正文纯文本摘要
     *
     * 刻意不用 wp_trim_words()：它按空白分词，中文长段落整段没有空格，
     * 会被当成「1 个词」直接返回原文（截不断）。这里改为剥短代码/标签后按字符数裁切。
     */
    function jinyu_comment_excerpt($content, int $length = 56): string
    {
        $text = wp_strip_all_tags(strip_shortcodes((string) $content));
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        if ($text === '') {
            return '';
        }
        return mb_strlen($text) > $length ? mb_substr($text, 0, $length) . '…' : $text;
    }
}

if (!function_exists('jinyu_recent_comments_list')) {
    /**
     * 最新评论列表
     *
     * 「金玉·最新评论」小工具与侧栏默认卡共用本函数，避免两处各写一份（改一处即可）。
     *
     * 结构契约（与 assets/style/widgets.less 的 .jinyu-rc-* 严格对应）：
     *   ul.jinyu-rc-list > li.jinyu-rc-item
     *     > a.jinyu-rc-avatar( img )
     *     + div.jinyu-rc-body( > div.jinyu-rc-head( > span.jinyu-rc-author + time.jinyu-rc-time )
     *                          + a.jinyu-rc-text( span.jinyu-rc-reply + 正文 ) )
     *
     * @param int $num 条数
     * @return string 无评论时返回空串，空状态文案由调用方决定
     */
    function jinyu_recent_comments_list(int $num = 5): string
    {
        $num = max(1, (int) $num);
        // 结果集缓存：最新评论不需要实时，3 分钟 TTL 足以；评论变动经 cache.php 的
        // jinyu_cache_flush() 自动失效，不会出现「刚审核通过的评论不显示」。
        $cache_key = 'recent_comments_' . $num;
        $comments  = jinyu_cache_get($cache_key);
        if (!is_array($comments)) {
            $comments = get_comments([
                'status'        => 'approve',
                'number'        => $num,
                'type'          => 'comment',
                'post_status'   => 'publish', // 不展示草稿/回收站文章上的评论
                'no_found_rows' => true,
            ]);
            jinyu_cache_set($cache_key, $comments, 3 * MINUTE_IN_SECONDS);
        }
        if (!$comments) {
            return '';
        }

        $out = '<ul class="jinyu-rc-list">';
        foreach ($comments as $jc_com) {
            $link = get_comment_link($jc_com);

            // 回复型评论标出「回复了谁」，让上下文一眼可读
            $reply = '';
            if ((int) $jc_com->comment_parent) {
                $jc_parent = get_comment($jc_com->comment_parent);
                if ($jc_parent && $jc_parent->comment_author !== '') {
                    $reply = '<span class="jinyu-rc-reply">'
                        . sprintf(esc_html__('回复了 %s：', 'jinyu'), esc_html($jc_parent->comment_author))
                        . '</span>';
                }
            }

            $jc_excerpt = jinyu_comment_excerpt($jc_com->comment_content, 56);
            $jc_full    = jinyu_comment_excerpt($jc_com->comment_content, 200);
            $jc_post    = get_post($jc_com->comment_post_ID);
            // 悬停提示：文章标题 + 完整正文（列表里只放一行，避免挤压）
            $jc_tip = $jc_post ? '《' . $jc_post->post_title . '》' . "\n" : '';
            $jc_tip .= $jc_full !== '' ? $jc_full : $jc_excerpt;

            $out .= '<li class="jinyu-rc-item">';
            $out .= '<a class="jinyu-rc-avatar" href="' . esc_url($link) . '" tabindex="-1" aria-hidden="true">'
                  . jinyu_comment_avatar($jc_com) . '</a>';
            $out .= '<div class="jinyu-rc-body">';
            $out .= '<div class="jinyu-rc-head">';
            $out .= '<span class="jinyu-rc-author">' . esc_html($jc_com->comment_author) . '</span>';
            $out .= '<time class="jinyu-rc-time" datetime="' . esc_attr(get_comment_time('c', false, false, $jc_com)) . '"'
                  . ' title="' . esc_attr(get_comment_time('Y-m-d H:i', false, false, $jc_com)) . '">'
                  . esc_html(sprintf(__('%s前', 'jinyu'), human_time_diff(get_comment_time('U', false, false, $jc_com), current_time('timestamp'))))
                  . '</time>';
            $out .= '</div>';
            $out .= '<a class="jinyu-rc-text" href="' . esc_url($link) . '" title="' . esc_attr($jc_tip) . '">'
                  . $reply . esc_html($jc_excerpt === '' ? __('（无正文）', 'jinyu') : $jc_excerpt) . '</a>';
            $out .= '</div></li>';
        }
        $out .= '</ul>';

        return $out;
    }
}

if (!function_exists('jinyu_wp_comment')) {
    /**
     * 单条评论渲染回调
     *
     * @param WP_Comment $comment
     * @param array      $args
     * @param int        $depth
     */
    function jinyu_wp_comment($comment, $args, $depth)
    {
        $GLOBALS['comment'] = $comment;
        $is_author = (int)$comment->user_id > 0 && (int)$comment->user_id === (int)get_post_field('post_author', $comment->comment_post_ID);
        ?>
        <li id="comment-<?php comment_ID(); ?>" <?php comment_class('jinyu-comment-item' . ($is_author ? ' jinyu-comment-author' : '')); ?>>
            <div class="jinyu-comment-avatar">
                <?php echo jinyu_comment_avatar($comment); ?>
            </div>

            <div class="jinyu-comment-body">
                <div class="jinyu-comment-head">
                    <span class="jinyu-comment-author"><?php comment_author_link($comment); ?></span>
                    <?php if ($is_author) : ?>
                        <span class="jinyu-comment-badge"><?php esc_html_e('作者', 'jinyu'); ?></span>
                    <?php endif; ?>
                    <time class="jinyu-comment-time" datetime="<?php echo esc_attr(get_comment_time('c')); ?>">
                        <?php echo esc_html(sprintf(__('%s前', 'jinyu'), human_time_diff(get_comment_time('U'), current_time('timestamp')))); ?>
                    </time>
                    <?php if ($comment->comment_approved === '0') : ?>
                        <span class="jinyu-comment-waiting"><?php esc_html_e('审核中', 'jinyu'); ?></span>
                    <?php endif; ?>
                </div>

                <div class="jinyu-comment-content"><?php comment_text($comment); ?></div>

                <?php if (jinyu_is_checked('comment_show_ua', true) && !empty($comment->comment_agent)) :
                    $ua = jinyu_parse_ua($comment->comment_agent);
                    if (!empty($ua['browser']) || !empty($ua['platform'])) :
                ?>
                <div class="jinyu-comment-ua">
                    <?php if (!empty($ua['platform'])) : ?>
                        <span class="jinyu-ua-item" title="<?php echo esc_attr($ua['platform']); ?>">
                            <?php echo jinyu_ua_icon($ua['platform'], 'platform'); ?>
                            <?php echo esc_html($ua['platform']); ?>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($ua['browser'])) :
                        $label = $ua['version'] ? $ua['browser'] . ' ' . $ua['version'] : $ua['browser'];
                    ?>
                        <span class="jinyu-ua-item" title="<?php echo esc_attr($label); ?>">
                            <?php echo jinyu_ua_icon($ua['browser'], 'browser'); ?>
                            <?php echo esc_html($ua['browser']); ?>
                        </span>
                    <?php endif; ?>
                </div>
                <?php endif; endif; ?>

                <div class="jinyu-comment-actions">
                    <?php
                    comment_reply_link(array_merge($args, [
                        'reply_text' => __('回复', 'jinyu'),
                        'depth'      => $depth,
                        'max_depth'  => isset($args['max_depth']) ? $args['max_depth'] : 5,
                        'before'     => '',
                        'after'      => '',
                    ]));
                    ?>
                </div>
            </div>
        </li>
        <?php
    }
}

/**
 * 评论表单字段：把评论框排到最后，并补上占位文案
 */
add_filter('comment_form_fields', 'jinyu_comment_form_fields');
function jinyu_comment_form_fields($fields)
{
    if (isset($fields['comment'])) {
        $comment_field = str_replace(
            '<textarea',
            '<textarea rows="4" placeholder="' . esc_attr__('说点什么吧...', 'jinyu') . '"',
            $fields['comment']
        );
        unset($fields['comment']);
        $fields['comment'] = $comment_field;
    }
    return $fields;
}

/**
 * 评论提交后清理文章缓存
 */
add_action('wp_insert_comment', function ($comment_id, $comment) {
    if (!empty($comment->comment_post_ID)) {
        // 注意：jinyu_cache_delete 已自动加 jinyu_ 前缀，此处不再重复，否则会出现
        // jinyu_jinyu_post_X 双重前缀、与 post_X 缓存键不匹配、导致失效失效。
        jinyu_cache_delete('post_' . $comment->comment_post_ID);
    }
}, 10, 2);

if (!function_exists('jinyu_author_box')) {
    /**
     * 文末作者信息卡（在 comments.php 中调用），返回 HTML
     */
    function jinyu_author_box(): string
    {
        $post = get_post();
        if (!$post) return '';
        $uid = (int)$post->post_author;
        if (!$uid) return '';

        $user = get_userdata($uid);
        if (!$user) return '';
        $custom_avatar = jinyu_user_avatar_url($uid);
        $fallback = jinyu_avatar_default($uid);
        $stats = jinyu_user_stats($uid);
        $bio = get_the_author_meta('description', $uid);

        $html = '<section class="jinyu-author-box" aria-label="' . esc_attr__('关于作者', 'jinyu') . '">';
        $html .= '<img class="jinyu-author-avatar" src="' . ($custom_avatar !== '' ? esc_url($custom_avatar) : $fallback) . '" alt="' . esc_attr($user->display_name) . '" loading="lazy" data-jinyu-fallback="' . esc_url($fallback) . '">';
        $html .= '<div class="jinyu-author-info">';
        $html .= '<div class="jinyu-author-top">';
        $html .= '<span class="jinyu-author-name">' . esc_html($user->display_name) . '</span>';
        $html .= '<a class="jinyu-author-home" href="' . esc_url(get_author_posts_url($uid)) . '">' . esc_html__('查看主页', 'jinyu') . '<i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>';
        $html .= '</div>';
        if ($bio !== '') {
            $html .= '<p class="jinyu-author-bio">' . esc_html($bio) . '</p>';
        }
        $html .= '<div class="jinyu-author-meta">';
        $html .= '<span class="jinyu-author-num"><b>' . (int)$stats['posts'] . '</b>' . esc_html__('文章', 'jinyu') . '</span>';
        $html .= '<span class="jinyu-author-sep" aria-hidden="true"></span>';
        $html .= '<span class="jinyu-author-num"><b>' . (int)$stats['comments'] . '</b>' . esc_html__('评论', 'jinyu') . '</span>';
        $html .= '</div></div></section>';
        return $html;
    }
}
