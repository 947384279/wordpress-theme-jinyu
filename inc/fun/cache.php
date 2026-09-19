<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 缓存层：对象缓存（同请求内）+ transient（跨请求）双层。
 *
 * 约定：所有 key 统一加 jinyu_ 前缀，便于按前缀批量清理。
 */

if (!defined('JINYU_CACHE_PREFIX')) {
    define('JINYU_CACHE_PREFIX', 'jinyu_');
}

if (!function_exists('jinyu_cache_key')) {
    function jinyu_cache_key(string $key): string
    {
        return JINYU_CACHE_PREFIX . $key;
    }
}

if (!function_exists('jinyu_cache_get')) {
    function jinyu_cache_get(string $key, mixed $default = false): mixed
    {
        $key = jinyu_cache_key($key);
        $v   = wp_cache_get($key, JINYU);
        // 仅在无持久对象缓存时回退读 transient（transient 落 options 表，有外部缓存时冗余）
        if ($v === false && !wp_using_ext_object_cache()) {
            $v = get_transient($key);
        }
        return ($v === false) ? $default : $v;
    }
}

if (!function_exists('jinyu_cache_set')) {
    function jinyu_cache_set(string $key, mixed $value, int $expire = 0): bool
    {
        $key = jinyu_cache_key($key);
        wp_cache_set($key, $value, JINYU, $expire);
        // 有持久对象缓存（Redis/Memcached）时 wp_cache 已跨请求持久化，无需再写 transient（避免每次填充缓存都落库一次）。
        // 无外部缓存时，用 transient 兜底做跨请求持久化（写入 options 表）。
        if (!wp_using_ext_object_cache()) {
            set_transient($key, $value, $expire);
        }
        return true;
    }
}

if (!function_exists('jinyu_cache_delete')) {
    function jinyu_cache_delete(string $key): bool
    {
        $key = jinyu_cache_key($key);
        wp_cache_delete($key, JINYU);
        if (!wp_using_ext_object_cache()) {
            delete_transient($key);
        }
        return true;
    }
}

if (!function_exists('jinyu_cache_flush')) {
    /**
     * 清空本主题写入的所有 transient（对象缓存随请求结束自然失效）
     */
    function jinyu_cache_flush(): int
    {
        global $wpdb;
        $count = 0;

        // 无外部对象缓存：transient 落 options 表，用单条 DELETE 批量清理（避免原 O(n) 循环 delete_transient）。
        if (!wp_using_ext_object_cache()) {
            $like_t  = $wpdb->esc_like('_transient_' . JINYU_CACHE_PREFIX) . '%';
            $like_to = $wpdb->esc_like('_transient_timeout_' . JINYU_CACHE_PREFIX) . '%';
            $count   = (int) $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                $like_t,
                $like_to
            ));
        }

        // 有外部对象缓存：transient 已不落库，必须直接刷 jinyu_ 缓存组，否则新内容要等 TTL 才显现（缓存陈旧穿透）。
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group(JINYU);
        }

        return $count;
    }
}

/* ==========================================================================
   查询级缓存：WordPress 核心对象缓存只缓存 meta/term/comment/option，
   不会缓存 WP_Query 结果集，因此每个 new WP_Query / get_posts 每次请求都会
   重跑 SQL。下面两个函数把「文章 ID 列表」缓存起来，TTL 内复用，
   命中后再用 get_posts(['post__in']) 取对象（对象已进对象缓存时≈0 次 SQL）。
   ========================================================================== */

if (!function_exists('jinyu_cached_post_ids')) {
    /**
     * 运行一个 WP_Query 并把返回的文章 ID 列表缓存起来（而非整条结果集）。
     *
     * @param string $key 缓存 key（自动加 jinyu_ 前缀）
     * @param int    $ttl 过期秒数
     * @param array  $args WP_Query 参数（fields 会被强制覆盖为 ids）
     * @return int[] 文章 ID 列表
     */
    function jinyu_cached_post_ids(string $key, int $ttl, array $args): array
    {
        $ids = jinyu_cache_get($key);
        if (is_array($ids)) {
            return $ids;
        }
        $args = array_merge($args, [
            'fields'        => 'ids',
            'no_found_rows' => true,
        ]);
        $q   = new \WP_Query($args);
        $ids = $q->have_posts() ? array_map('intval', $q->posts) : [];
        jinyu_cache_set($key, $ids, $ttl);
        return $ids;
    }
}

if (!function_exists('jinyu_hydrate_posts')) {
    /**
     * 按缓存的 ID 列表取文章对象，保持原顺序（orderby=post__in）。
     *
     * @param int[]  $ids   文章 ID 列表
     * @param array  $extra 额外 get_posts 参数（如 ignore_sticky_posts）
     * @return WP_Post[]
     */
    function jinyu_hydrate_posts(array $ids, array $extra = []): array
    {
        if (empty($ids)) {
            return [];
        }
        return get_posts(array_merge([
            'post_type'           => 'post',
            'post__in'            => $ids,
            'orderby'             => 'post__in',
            'no_found_rows'       => true,
            'ignore_sticky_posts' => true,
        ], $extra));
    }
}

/* ==========================================================================
   封面附件预热：卡片渲染时每篇文章封面图都会懒加载一次
   get_post(附件) + 附件 meta 查询，N 篇文章 = N 次查询（实测首页可达 37 次
   posts/meta 查询）。这里在 loop_start 阶段一次性批量预热所有封面附件
   （文章对象 + meta），把 N 次懒加载压成 1~2 次 bulk 查询，且与对象缓存/结果集
   缓存叠加后，暖缓存下这些查询趋近 0。
   ========================================================================== */

add_action('loop_start', function ($q) {
    if (empty($q->posts)) {
        return;
    }
    // fields=ids 的查询只用来取 ID 列表、不渲染卡片，跳过以免反而增加查询；
    // 注意 WP_Query 默认 fields='all'，不能仅凭非空就跳过。
    if (!empty($q->query_vars['fields']) && $q->query_vars['fields'] === 'ids') {
        return;
    }

    $attach_ids = [];
    foreach ($q->posts as $p) {
        if (!isset($p->ID)) {
            continue;
        }
        $tid = (int) get_post_thumbnail_id($p->ID);
        if ($tid > 0) {
            $attach_ids[] = $tid;
        }
        // 正文提取封面的附件 ID（已在提取时存入 post meta，随主查询 meta 缓存一并命中，这里不额外查）
        $cid = (int) get_post_meta($p->ID, '_jinyu_cover_attach_id', true);
        if ($cid > 0) {
            $attach_ids[] = $cid;
        }
    }
    $attach_ids = array_unique($attach_ids);

    if (!empty($attach_ids)) {
        // true/true = 同时批量预取文章对象与 meta，覆盖 wp_get_attachment_image_src 的取图需求，
        // 把 N 篇文章各自的懒加载附件查询压成 1~2 次 bulk 查询，与对象缓存叠加后暖缓存趋近 0
        _prime_post_caches($attach_ids, 'attachment', true, true);
    }
});

/* ==========================================================================
   自动失效：内容或设置发生变化时清理对应缓存，避免前台读到旧数据
   ========================================================================== */

// 文章新增 / 更新 / 删除
add_action('save_post', function ($post_id) {
    jinyu_cache_delete('post_' . $post_id);
    jinyu_cache_delete('related_' . $post_id);
    jinyu_cache_delete('hot_posts');
    // 整页缓存（首页 / 归档等）依赖文章列表，必须一并失效，
    // 否则开启整页缓存后发新文章，首页要等 TTL 才刷新（最长 1 小时）。
    jinyu_cache_flush();
}, 10, 1);

add_action('deleted_post', function ($post_id) {
    jinyu_cache_delete('post_' . $post_id);
    jinyu_cache_delete('hot_posts');
    jinyu_cache_flush();
}, 10, 1);

// 评论变动（含审核通过 / 删除）
add_action('wp_set_comment_status', function ($comment_id, $status) {
    $comment = get_comment($comment_id);
    if ($comment) {
        jinyu_cache_delete('post_' . $comment->comment_post_ID);
    }
}, 10, 2);

// 评论硬删除 / 状态流转：最新评论等全局小工具依赖评论数据，必须失效整页缓存。
// 注意 wp_delete_comment(force=true) 不会触发 wp_set_comment_status，故单独挂钩。
add_action('delete_comment', function ($comment_id) {
    jinyu_cache_flush();
}, 10, 1);
add_action('transition_comment_status', function ($new_status, $old_status, $comment) {
    jinyu_cache_flush();
}, 10, 3);

// 主题设置保存
add_action('update_option_' . JINYU_OPT, function () {
    jinyu_cache_flush();
});

// 切换主题时清理残留
add_action('switch_theme', function () {
    jinyu_cache_flush();
});
