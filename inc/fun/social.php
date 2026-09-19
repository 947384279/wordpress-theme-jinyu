<?php
if (!defined('ABSPATH')) exit;

/* ==========================================================================
   社交：关注（用户 / 系列）+ 站内通知
   - 关注关系存 user meta 数组（双写：following / followers），查询轻量
   - 通知存独立表 wp_jinyu_notify，支撑已读 / 分页 / 未读计数 / 角标
   ========================================================================== */

/* ----------------------------- 关注：用户 ----------------------------- */
function jinyu_follow_user(int $follower, int $following): bool
{
    if ($follower <= 0 || $following <= 0 || $follower === $following) return false;
    if (!get_userdata($following)) return false;

    $following_list = jinyu_meta_ids($follower, 'jinyu_following');
    $followers_list = jinyu_meta_ids($following, 'jinyu_followers');

    if (in_array($following, $following_list, true)) return true; // 已关注

    $following_list[] = $following;
    $followers_list[] = $follower;
    update_user_meta($follower, 'jinyu_following', array_values($following_list));
    update_user_meta($following, 'jinyu_followers', array_values($followers_list));

    // 给被关注者发一条「关注了你」通知
    jinyu_add_notification($following, 'follow', __('关注了你', JINYU), '', '', $follower);
    return true;
}

function jinyu_unfollow_user(int $follower, int $following): bool
{
    if ($follower <= 0 || $following <= 0) return false;

    $following_list = jinyu_meta_ids($follower, 'jinyu_following');
    $followers_list = jinyu_meta_ids($following, 'jinyu_followers');

    $fi = array_search($following, $following_list, true);
    $fo = array_search($follower, $followers_list, true);
    if ($fi !== false) array_splice($following_list, $fi, 1);
    if ($fo !== false) array_splice($followers_list, $fo, 1);

    update_user_meta($follower, 'jinyu_following', array_values($following_list));
    update_user_meta($following, 'jinyu_followers', array_values($followers_list));
    return true;
}

function jinyu_is_following(int $follower, int $following): bool
{
    if ($follower <= 0 || $following <= 0) return false;
    $following_list = jinyu_meta_ids($follower, 'jinyu_following');
    return in_array($following, $following_list, true);
}

function jinyu_get_following_users(int $uid, int $limit = 0): array
{
    if ($uid <= 0) return [];
    $ids = jinyu_meta_ids($uid, 'jinyu_following');
    if ($limit > 0) $ids = array_slice($ids, 0, $limit);
    $out = [];
    foreach ($ids as $id) {
        if ($u = get_userdata($id)) $out[] = $u;
    }
    return $out;
}

/* ----------------------------- 关注：系列 ----------------------------- */
function jinyu_follow_term(int $uid, int $term_id): bool
{
    if ($uid <= 0 || $term_id <= 0) return false;
    $terms = jinyu_meta_ids($uid, 'jinyu_following_terms');
    if (in_array($term_id, $terms, true)) return true;
    $terms[] = $term_id;
    update_user_meta($uid, 'jinyu_following_terms', array_values($terms));
    return true;
}

function jinyu_unfollow_term(int $uid, int $term_id): bool
{
    if ($uid <= 0 || $term_id <= 0) return false;
    $terms = jinyu_meta_ids($uid, 'jinyu_following_terms');
    $i = array_search($term_id, $terms, true);
    if ($i !== false) array_splice($terms, $i, 1);
    update_user_meta($uid, 'jinyu_following_terms', array_values($terms));
    return true;
}

function jinyu_is_following_term(int $uid, int $term_id): bool
{
    if ($uid <= 0 || $term_id <= 0) return false;
    $terms = jinyu_meta_ids($uid, 'jinyu_following_terms');
    return in_array($term_id, $terms, true);
}

function jinyu_get_following_terms(int $uid): array
{
    if ($uid <= 0) return [];
    $ids = jinyu_meta_ids($uid, 'jinyu_following_terms');
    $out = [];
    foreach ($ids as $id) {
        // get_term 对不存在/已删除的系列返回 WP_Error（truthy），直接入列会在
        // get_term_link() 处触发 fatal，导致整页中断（骨架屏不消失）
        $t = get_term($id, 'jinyu_series');
        if ($t && !is_wp_error($t)) $out[] = $t;
    }
    return $out;
}

/* ----------------------------- 站内通知 ----------------------------- */
function jinyu_notify_install()
{
    global $wpdb;
    $table = $wpdb->prefix . 'jinyu_notify';
    // 幂等：表已存在则跳过
    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
        return;
    }
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();
    dbDelta("
        CREATE TABLE {$table} (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
          type VARCHAR(32) NOT NULL DEFAULT '',
          actor_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
          title VARCHAR(255) NOT NULL DEFAULT '',
          content TEXT NOT NULL,
          link VARCHAR(512) NOT NULL DEFAULT '',
          is_read TINYINT UNSIGNED NOT NULL DEFAULT 0,
          created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
          PRIMARY KEY (id),
          KEY user_read (user_id, is_read),
          KEY created_at (created_at)
        ) {$charset};
    ");
}

function jinyu_add_notification(int $user_id, string $type, string $title, string $content = '', string $link = '', int $actor_id = 0): int
{
    if ($user_id <= 0) return 0;
    global $wpdb;
    $table = $wpdb->prefix . 'jinyu_notify';
    $wpdb->insert(
        $table,
        [
            'user_id'    => $user_id,
            'type'       => $type,
            'actor_id'   => $actor_id,
            'title'      => $title,
            'content'    => $content,
            'link'       => $link,
            'is_read'    => 0,
            'created_at' => current_time('mysql'),
        ],
        ['%d', '%s', '%d', '%s', '%s', '%s', '%d', '%s']
    );
    return (int)$wpdb->insert_id;
}

function jinyu_get_notifications(int $user_id, int $page = 1, int $per = 20): array
{
    if ($user_id <= 0) return [];
    global $wpdb;
    $table = $wpdb->prefix . 'jinyu_notify';
    $page  = max(1, $page);
    $offset = ($page - 1) * $per;
    return (array)$wpdb->get_results(
        $wpdb->prepare("SELECT * FROM $table WHERE user_id = %d ORDER BY id DESC LIMIT %d OFFSET %d", $user_id, $per, $offset)
    );
}

function jinyu_get_unread_count(int $user_id): int
{
    if ($user_id <= 0) return 0;
    global $wpdb;
    $table = $wpdb->prefix . 'jinyu_notify';
    return (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE user_id = %d AND is_read = 0", $user_id));
}

function jinyu_mark_read(int $user_id, array $ids = []): int
{
    if ($user_id <= 0) return 0;
    global $wpdb;
    $table = $wpdb->prefix . 'jinyu_notify';
    if (empty($ids)) {
        return (int)$wpdb->query($wpdb->prepare("UPDATE $table SET is_read = 1 WHERE user_id = %d", $user_id));
    }
    $ids = array_map('intval', $ids);
    $placeholders = implode(',', array_fill(0, count($ids), '%d'));
    return (int)$wpdb->query(
        $wpdb->prepare("UPDATE $table SET is_read = 1 WHERE user_id = %d AND id IN ($placeholders)", array_merge([$user_id], $ids))
    );
}

/* ----------------------------- 触发：评论回复通知 ----------------------------- */
add_action('wp_insert_comment', 'jinyu_notify_comment_reply', 10, 2);
function jinyu_notify_comment_reply($comment_id, $comment)
{
    if ((int)$comment->comment_approved !== 1) {
        return; // 仅已通过审核的评论才通知
    }
    $parent_id = (int)$comment->comment_parent;
    if (!$parent_id) {
        return;
    }
    $parent = get_comment($parent_id);
    if (!$parent) {
        return;
    }
    $parent_uid = (int)$parent->user_id;
    if (!$parent_uid || $parent_uid === (int)$comment->user_id) {
        return; // 游客评论 / 自己回复自己不通知
    }

    $actor    = (int)$comment->user_id;
    $actor_name = $actor ? get_userdata($actor)->display_name : ($comment->comment_author ?: __('有人', JINYU));
    $post     = get_post($comment->comment_post_ID);
    $post_title = $post ? $post->post_title : '';
    $title    = sprintf(__('%s 回复了你的评论', JINYU), $actor_name);
    $content  = ($post_title ? '《' . $post_title . '》 ' : '') . mb_substr(wp_strip_all_tags($comment->comment_content), 0, 140);
    $link     = get_comment_link($comment_id);

    jinyu_add_notification($parent_uid, 'comment_reply', $title, $content, $link, $actor);
}

/* ----------------------------- 安装钩子 ----------------------------- */
add_action('after_switch_theme', 'jinyu_notify_install');
// 兜底：切主题动作若因缓存/顺序错过，前台访问 12h 内补建一次
add_action('wp_footer', 'jinyu_notify_install_footer', 1);
function jinyu_notify_install_footer()
{
    if (get_transient('jinyu_notify_check')) {
        return;
    }
    set_transient('jinyu_notify_check', 1, 12 * HOUR_IN_SECONDS);
    jinyu_notify_install();
}
