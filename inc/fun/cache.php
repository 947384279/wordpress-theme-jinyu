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

if (!function_exists('jinyu_cache_prime_transients')) {
    /**
     * 无外部对象缓存时，把本主题写入的全部 transient 一次性批量载入内存缓存，
     * 避免每个 jinyu_cache_get 都对 options 表各发一条 SELECT（首页稳态曾达 ~30 次，
     * 占总查询一半以上）。仅在无 Memcached/Redis 时生效；有外部缓存时 wp_cache 已跨请求
     * 持久化，无需此步。单次请求内只执行一次（静态标记）。
     */
    function jinyu_cache_prime_transients(): void
    {
        static $primed = false;
        if ($primed) {
            return;
        }
        $primed = true;
        global $wpdb;
        if (empty($wpdb)) {
            return;
        }
        $like = $wpdb->esc_like('_transient_' . JINYU_CACHE_PREFIX) . '%';
        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s", $like),
            ARRAY_A
        );
        if (empty($rows)) {
            return;
        }
        foreach ($rows as $row) {
            $k = substr($row['option_name'], strlen('_transient_'));
            $v = maybe_unserialize($row['option_value']);
            wp_cache_set($k, $v, 'jinyu');
        }
    }
}

if (!function_exists('jinyu_cache_get')) {
    function jinyu_cache_get(string $key, mixed $default = false): mixed
    {
        $key = jinyu_cache_key($key);
        $v   = wp_cache_get($key, 'jinyu');
        // 无持久对象缓存：本主题 transient 落 options 表。首次访问批量载入（见 jinyu_cache_prime_transients），
        // 之后直接从内存缓存取，避免每个 key 各查一次 options。
        if ($v === false && !wp_using_ext_object_cache()) {
            jinyu_cache_prime_transients();
            $v = wp_cache_get($key, 'jinyu');
        }
        return ($v === false) ? $default : $v;
    }
}

if (!function_exists('jinyu_cache_set')) {
    function jinyu_cache_set(string $key, mixed $value, int $expire = 0): bool
    {
        $key = jinyu_cache_key($key);
        wp_cache_set($key, $value, 'jinyu', $expire);
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
        wp_cache_delete($key, 'jinyu');
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
            wp_cache_flush_group('jinyu');
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

if (!function_exists('jinyu_hydrate_posts_cached')) {
    /**
     * jinyu_hydrate_posts 的跨请求持久化版。
     *
     * 问题：jinyu_hydrate_posts 每请求都跑 get_posts(['post__in'])，在无外部对象缓存
     * （Memcached/Redis）时这一步不持久化，稳态首页会退化到 ~113 条 SQL（ID 列表已缓存，
     * 但对象/meta/term 每次重取）。首页两栏盒子(index.php)早已手动缓存 WP_Post[] 规避此问题，
     * 本函数把同样的做法抽成统一入口。
     *
     * 机制：
     * - 未命中：正常取对象（get_posts 内部已批量预热 posts/meta/term 到「本次请求」的对象缓存），
     *   抓取已预热的 meta / term 随 WP_Post[] 一起持久化到 jinyu_ 缓存组（有外部缓存落 Memcached，
     *   无则落 transient / options 表）。
     * - 命中：把缓存的 WP_Post[] 与 meta / term 重新注入 WP 对象缓存（posts / post_meta /
     *   post_terms_* 组），使渲染期的 get_post / get_the_title / get_post_meta / 分类链接等
     *   全部命中内存缓存、不再回查数据库 → 整段循环 0 SQL。
     *
     * 行为一致性：有外部对象缓存时，本函数与 jinyu_hydrate_posts 等价（命中直接返回，无 SQL）；
     * 无外部对象缓存时提供 transient 兜底，避免裸跑到 113 SQL。
     *
     * @param int[]  $ids   已缓存/已计算的文章 ID 列表
     * @param string $key   缓存 key（自动加 jinyu_ 前缀；内部以 $key . '_hyd' 存对象）
     * @param int    $ttl   过期秒数
     * @param array  $extra jinyu_hydrate_posts 的额外参数
     * @return WP_Post[]
     */
    function jinyu_hydrate_posts_cached(array $ids, string $key, int $ttl, array $extra = []): array
    {
        if (empty($ids)) {
            return [];
        }

        $hk     = $key . '_hyd';
        $cached = jinyu_cache_get($hk);
        if (is_array($cached) && isset($cached['p']) && is_array($cached['p'])) {
            // 命中：把对象与 meta / term 重新注入 WP 对象缓存，渲染期 0 SQL
            foreach ($cached['p'] as $p) {
                if (isset($p->ID)) {
                    wp_cache_set($p->ID, $p, 'posts');
                }
            }
            if (!empty($cached['m']) && is_array($cached['m'])) {
                foreach ($cached['m'] as $id => $m) {
                    $id = (int) $id;
                    // 命中时把快照 meta 重新注入 WP 对象缓存。快照可能比线上陈旧
                    // （缓存期内有人点赞/浏览，jinyu_likes / jinyu_views 已变）：
                    // 若无条件 wp_cache_set 覆盖，会把新鲜值冲掉，前端点赞数误显 0。
                    // 规则：① 线上 post_meta 缓存为空（如刚被 update_post_meta 失效）
                    // 时不注入快照，交给 WP 重新回源取最新；② 线上已有值时合并，
                    // 以线上新鲜值优先，仅补足快照独有键。
                    $existing = wp_cache_get($id, 'post_meta');
                    if ($existing === false || !is_array($existing)) {
                        continue;
                    }
                    wp_cache_set($id, array_merge($m, $existing), 'post_meta');
                }
            }
            if (!empty($cached['t']) && is_array($cached['t'])) {
                foreach ($cached['t'] as $id => $t) {
                    foreach ($t as $tax => $terms) {
                        wp_cache_set((int) $id, $terms, 'post_terms_' . $tax);
                    }
                }
            }
            return $cached['p'];
        }

        // 未命中：正常取对象（get_posts 内部已批量预热 posts / meta / term 到本次请求的对象缓存）
        $posts = jinyu_hydrate_posts($ids, $extra);
        if (empty($posts)) {
            jinyu_cache_set($hk, ['p' => [], 'm' => [], 't' => []], $ttl);
            return [];
        }

        // 抓取本次已预热的 meta / term，随对象一起持久化，供命中时重新注入
        $meta_map   = [];
        $term_map   = [];
        $taxonomies = get_object_taxonomies('post');
        foreach ($posts as $p) {
            $id = $p->ID;
            $mc = wp_cache_get($id, 'post_meta');
            if (is_array($mc)) {
                $meta_map[$id] = $mc;
            }
            $tc = [];
            foreach ($taxonomies as $tax) {
                $tt = wp_cache_get($id, 'post_terms_' . $tax);
                if (is_array($tt)) {
                    $tc[$tax] = $tt;
                }
            }
            if (!empty($tc)) {
                $term_map[$id] = $tc;
            }
        }

        jinyu_cache_set($hk, ['p' => $posts, 'm' => $meta_map, 't' => $term_map], $ttl);
        return $posts;
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
    static $primed = [];   // 跨循环去重：同一请求内每个封面附件只 prime 一次
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

    // 只取本请求尚未预热的封面，避免首页多个循环（热门/相关/随机/热评/两栏…）
    // 各自 prime 同一批附件导致重复 bulk 查询。
    $todo = array_values(array_diff($attach_ids, $primed));
    if (!empty($todo)) {
        // true/true = 同时批量预取文章对象与 meta，覆盖 wp_get_attachment_image_src 的取图需求，
        // 把 N 篇文章各自的懒加载附件查询压成 1~2 次 bulk 查询，与对象缓存叠加后暖缓存趋近 0
        _prime_post_caches($todo, true, true);
        $primed = array_merge($primed, $todo);
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

/* ==========================================================================
   后台提示：未启用持久对象缓存（Memcached / Redis）时，在主题设置页建议用户启用。
   金玉主题首页在无对象缓存下约 70+ 条 SQL，高并发偏慢；装上后即 4 条 / ~60ms。
   仅管理员可见、仅设置页出现、按用户关闭（避免全站后台刷屏与反复打扰）。
   ========================================================================== */

if (is_admin()) {
    // 关闭提示（持久化到用户 meta）
    add_action('admin_init', function () {
        if (empty($_GET['jinyu_dismiss_cache_notice'])) {
            return;
        }
        if (!current_user_can('manage_options')) {
            return;
        }
        check_admin_referer('jinyu_dismiss_cache_notice');
        update_user_meta(get_current_user_id(), 'jinyu_dismiss_cache_notice', 1);
        wp_safe_redirect(remove_query_arg('jinyu_dismiss_cache_notice'));
        exit;
    });

    add_action('admin_notices', function () {
        if (!current_user_can('manage_options')) {
            return;
        }
        if (wp_using_ext_object_cache()) {
            return; // 已装 Memcached/Redis，无需提示
        }
        if (get_user_meta(get_current_user_id(), 'jinyu_dismiss_cache_notice', true)) {
            return; // 用户已关闭
        }
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'jinyu-options') === false) {
            return; // 只在主题设置页显示
        }
        $url = wp_nonce_url(add_query_arg('jinyu_dismiss_cache_notice', '1'), 'jinyu_dismiss_cache_notice');
        echo '<div class="notice notice-warning"><p>'
            . __('检测到当前站点未启用持久对象缓存（Memcached / Redis）。金玉主题首页在无对象缓存下约需 70+ 条数据库查询，高并发时响应偏慢。建议安装并启用对象缓存，以获得约 4 条查询 / 60ms 的极致性能。', 'jinyu')
            . ' <a href="' . esc_url($url) . '">' . __('不再提示', 'jinyu') . '</a></p></div>';
    });
}
