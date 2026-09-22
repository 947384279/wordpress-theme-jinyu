<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 前台 Ajax 接口：点赞 / 收藏 / 加载更多
 * 统一通过 check_ajax_referer 校验 nonce，拒绝未授权请求。
 */

const JINYU_NONCE_ACTION = 'jinyu_front';

/**
 * 通用前置校验
 */
function jinyu_ajax_guard()
{
    check_ajax_referer(JINYU_NONCE_ACTION, '_ajax_nonce');
}

/* ==========================================================================
   点赞
   ========================================================================== */
add_action('wp_ajax_jinyu_like', 'jinyu_ajax_like');
add_action('wp_ajax_nopriv_jinyu_like', 'jinyu_ajax_like');
function jinyu_ajax_like()
{
    jinyu_ajax_guard();

    $pid = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    if (!$pid || get_post_status($pid) !== 'publish') {
        wp_send_json_error(__('文章不存在', 'jinyu'));
    }

    $act     = isset($_POST['action_type']) ? sanitize_key($_POST['action_type']) : 'like';
    $is_like = ($act !== 'unlike');

    $uid = get_current_user_id();
    $ip  = function_exists('jinyu_client_ip') ? jinyu_client_ip() : (isset($_SERVER['REMOTE_ADDR']) ? trim((string) $_SERVER['REMOTE_ADDR']) : '0.0.0.0');

    if ($uid) {
        // 登录用户：user meta 去重（服务端可信），支持点赞/取消点赞切换
        $liked = jinyu_meta_ids($uid, 'jinyu_liked_posts');
        $has   = in_array($pid, $liked, true);
        if ($is_like) {
            if ($has) {
                wp_send_json_error(__('已点赞', 'jinyu'));
            }
            $liked[] = $pid;
            update_user_meta($uid, 'jinyu_liked_posts', $liked);
        } else {
            if (!$has) {
                wp_send_json_error(__('尚未点赞', 'jinyu'));
            }
            $liked = array_values(array_diff($liked, array($pid)));
            update_user_meta($uid, 'jinyu_liked_posts', $liked);
        }
    } else {
        // 游客：cookie 仅作 UI 提示（可被清除绕过），必须叠加服务端 IP 去重 + 限流，杜绝无限刷量
        if (!jinyu_rate_limit_check('like', 20, MINUTE_IN_SECONDS)) {
            wp_send_json_error(__('操作过于频繁，请稍后再试', 'jinyu'));
        }
        $dedupe = 'jinyu_like_' . md5($ip . '|' . $pid);
        $has    = (bool) get_transient($dedupe) || !empty($_COOKIE['jinyu_liked_' . $pid]);
        if ($is_like) {
            if ($has) {
                wp_send_json_error(__('已点赞', 'jinyu'));
            }
            setcookie('jinyu_liked_' . $pid, '1', time() + DAY_IN_SECONDS * 30, '/', '', is_ssl(), false);
            set_transient($dedupe, 1, DAY_IN_SECONDS * 30);
        } else {
            if (!$has) {
                wp_send_json_error(__('尚未点赞', 'jinyu'));
            }
            setcookie('jinyu_liked_' . $pid, '0', time() - DAY_IN_SECONDS, '/', '', is_ssl(), false);
            delete_transient($dedupe);
        }
    }

    $count = (int)get_post_meta($pid, 'jinyu_likes', true);
    $count = $is_like ? ($count + 1) : max(0, $count - 1);
    update_post_meta($pid, 'jinyu_likes', $count);

    wp_send_json_success($count);
}

/* 批量读取点赞状态（页面缓存场景下的前端水合：保证计数/已赞态与 DB 一致，不依赖缓存失效） */
add_action('wp_ajax_jinyu_like_state', 'jinyu_ajax_like_state');
add_action('wp_ajax_nopriv_jinyu_like_state', 'jinyu_ajax_like_state');
function jinyu_ajax_like_state()
{
    jinyu_ajax_guard();

    $raw = isset($_POST['ids']) ? (string) $_POST['ids'] : '';
    $ids = array_filter(array_map('absint', explode(',', $raw)));
    if (empty($ids)) {
        wp_send_json_success([]);
    }

    $out = [];
    foreach ($ids as $pid) {
        $out[(int) $pid] = [
            'count' => jinyu_get_post_likes($pid),
            'liked' => jinyu_is_liked($pid),
        ];
    }
    wp_send_json_success($out);
}

/* ==========================================================================
   收藏
   ========================================================================== */
add_action('wp_ajax_jinyu_fav', 'jinyu_ajax_fav');
function jinyu_ajax_fav()
{
    jinyu_ajax_guard();

    if (!is_user_logged_in()) {
        wp_send_json_error(__('请先登录', 'jinyu'));
    }

    $pid = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    $fav = isset($_POST['fav']) && $_POST['fav'] === '1';

    if (!$pid || get_post_status($pid) !== 'publish') {
        wp_send_json_error(__('文章不存在', 'jinyu'));
    }

    $uid  = get_current_user_id();
    $favs = jinyu_meta_ids($uid, 'jinyu_fav_posts');
    $idx  = array_search($pid, $favs, true);

    if ($fav && $idx === false) {
        $favs[] = $pid;
    } elseif (!$fav && $idx !== false) {
        array_splice($favs, $idx, 1);
    }

    update_user_meta($uid, 'jinyu_fav_posts', array_values($favs));

    wp_send_json_success($fav);
}

/* ==========================================================================
   实时搜索建议（下拉预读）
   ========================================================================== */
add_action('wp_ajax_jinyu_search', 'jinyu_ajax_search');
add_action('wp_ajax_nopriv_jinyu_search', 'jinyu_ajax_search');
function jinyu_ajax_search()
{
    jinyu_ajax_guard();

    $q = isset($_POST['q']) ? sanitize_text_field(wp_unslash($_POST['q'])) : '';
    if (mb_strlen($q) < 1) {
        wp_send_json_success(['html' => '', 'count' => 0]);
    }

    $query = new WP_Query([
        's'              => $q,
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => 8,
        'no_found_rows'  => true,
    ]);

    ob_start();
    if ($query->have_posts()) {
        while ($query->have_posts()): $query->the_post();
            $cover = jinyu_get_post_cover(get_the_ID(), 'thumbnail');
            ?>
            <a class="jinyu-search-sg" href="<?php the_permalink(); ?>">
                <span class="jinyu-search-sg-cover">
                    <?php if ($cover): ?><img src="<?php echo esc_url($cover); ?>" alt="" loading="lazy" decoding="async"><?php endif; ?>
                </span>
                <span class="jinyu-search-sg-body">
                    <span class="jinyu-search-sg-title"><?php the_title(); ?></span>
                    <span class="jinyu-search-sg-meta"><?php echo esc_html(get_the_date('Y-m-d')); ?> · <?php echo esc_html(jinyu_read_time()); ?></span>
                </span>
            </a>
            <?php
        endwhile;
        wp_reset_postdata();
    }
    $html  = ob_get_clean();
    $count = (int) $query->found_posts;

    wp_send_json_success([
        'html'  => $html,
        'count' => $count,
        'more'  => home_url('/?s=' . rawurlencode($q)),
    ]);
}

/* ==========================================================================
   加载更多
   ========================================================================== */
add_action('wp_ajax_jinyu_load_more', 'jinyu_ajax_load_more');
add_action('wp_ajax_nopriv_jinyu_load_more', 'jinyu_ajax_load_more');
function jinyu_ajax_load_more()
{
    jinyu_ajax_guard();

    $current = isset($_POST['page']) ? absint($_POST['page']) : 1;
    $next    = max(2, $current + 1);

    $q = new WP_Query([
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => (int)get_option('posts_per_page', 10),
        'paged'          => $next,
    ]);

    if (!$q->have_posts()) {
        wp_send_json_error(__('没有更多了', 'jinyu'));
    }

    ob_start();
    while ($q->have_posts()) {
        $q->the_post();
        get_template_part('templates/module', 'post');
    }
    wp_reset_postdata();
    $html = ob_get_clean();

    wp_send_json_success([
        'html'     => $html,
        'page'     => $next,
        'has_more' => $next < (int)$q->max_num_pages,
    ]);
}

/* ==========================================================================
   站内链接悬停预览（Hover Card）
   ========================================================================== */
add_action('wp_ajax_jinyu_link_preview', 'jinyu_ajax_link_preview');
add_action('wp_ajax_nopriv_jinyu_link_preview', 'jinyu_ajax_link_preview');
function jinyu_ajax_link_preview()
{
    jinyu_ajax_guard();

    $url = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '';
    $pid = url_to_postid($url);
    if (!$pid || get_post_status($pid) !== 'publish' || get_post_type($pid) !== 'post') {
        wp_send_json_success(['ok' => false]);
    }

    $excerpt = wp_strip_all_tags(get_the_excerpt($pid));
    if (!$excerpt) {
        $excerpt = wp_strip_all_tags(get_post_field('post_content', $pid));
    }
    $excerpt = mb_substr($excerpt, 0, 110);

    wp_send_json_success([
        'ok'      => true,
        'title'   => get_the_title($pid),
        'excerpt' => $excerpt,
        'thumb'   => jinyu_get_post_cover($pid, 'medium'),
        'url'     => get_permalink($pid),
        'date'    => get_the_date('Y-m-d', $pid),
    ]);
}

/* ==========================================================================
   协同过滤推荐：浏览轨迹记录 + 取推荐
   数据存于 option jinyu_coview_map（非 autoload，避免每次请求加载）
   ========================================================================== */
add_action('wp_ajax_jinyu_coview_track', 'jinyu_ajax_coview_track');
add_action('wp_ajax_nopriv_jinyu_coview_track', 'jinyu_ajax_coview_track');
function jinyu_ajax_coview_track()
{
    jinyu_ajax_guard();

    $prev = isset($_POST['prev']) ? absint($_POST['prev']) : 0;
    $cur  = isset($_POST['cur'])  ? absint($_POST['cur'])  : 0;
    if (!$prev || !$cur || $prev === $cur) {
        wp_send_json_success(['ok' => false]);
    }
    if (get_post_status($prev) !== 'publish' || get_post_status($cur) !== 'publish') {
        wp_send_json_success(['ok' => false]);
    }

    $map = get_option('jinyu_coview_map', []);
    if (!is_array($map)) $map = [];

    $map[$cur][$prev] = (int)($map[$cur][$prev] ?? 0) + 1;
    $map[$prev][$cur] = (int)($map[$prev][$cur] ?? 0) + 1;

    // 每个节点只保留协同权重最高的若干邻居
    foreach ([$cur, $prev] as $k) {
        if (!empty($map[$k]) && is_array($map[$k])) {
            arsort($map[$k]);
            $map[$k] = array_slice($map[$k], 0, 15, true);
        }
    }

    // 总量上限：按协同总权重裁剪最冷节点
    if (count($map) > 400) {
        uasort($map, function ($a, $b) {
            return array_sum((array)$a) - array_sum((array)$b);
        });
        $map = array_slice($map, -400, null, true);
    }

    update_option('jinyu_coview_map', $map, false);
    wp_send_json_success(['ok' => true]);
}

add_action('wp_ajax_jinyu_coview', 'jinyu_ajax_coview');
add_action('wp_ajax_nopriv_jinyu_coview', 'jinyu_ajax_coview');
function jinyu_ajax_coview()
{
    jinyu_ajax_guard();

    $pid = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    $num = isset($_POST['num']) ? absint($_POST['num']) : 4;
    if ($num < 1 || $num > 12) $num = 4;

    $list = [];
    $map  = get_option('jinyu_coview_map', []);
    if (!empty($map[$pid]) && is_array($map[$pid])) {
        arsort($map[$pid]);
        $ids = array_keys(array_slice($map[$pid], 0, $num, true));
        foreach ($ids as $id) {
            if (get_post_status($id) === 'publish') {
                $list[] = jinyu_coview_item($id);
            }
        }
    }

    // 协同数据不足时用「热门相关」补足，保证区域始终有内容
    if (count($list) < $num && function_exists('jinyu_get_related_posts')) {
        $rel = jinyu_get_related_posts($pid, $num - count($list), 'views');
        if ($rel && $rel->have_posts()) {
            while ($rel->have_posts()) {
                $rel->the_post();
                $list[] = jinyu_coview_item(get_the_ID());
            }
            wp_reset_postdata();
        }
    }

    wp_send_json_success(['list' => $list]);
}

function jinyu_coview_item($id)
{
    return [
        'id'    => $id,
        'title' => get_the_title($id),
        'url'   => get_permalink($id),
        'thumb' => jinyu_get_post_cover($id, 'thumbnail'),
    ];
}

/* ==========================================================================
   文末「这篇有帮助」投票
   ========================================================================== */
add_action('wp_ajax_jinyu_vote', 'jinyu_ajax_vote');
add_action('wp_ajax_nopriv_jinyu_vote', 'jinyu_ajax_vote');
function jinyu_ajax_vote()
{
    jinyu_ajax_guard();

    $pid = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    $dir = isset($_POST['dir']) ? sanitize_key($_POST['dir']) : '';
    if (!$pid || !in_array($dir, ['yes', 'no'], true) || get_post_status($pid) !== 'publish') {
        wp_send_json_error(__('参数无效', 'jinyu'));
    }

    // 游客端原本无任何防重复，可无限刷。叠加 IP 去重（每 IP 每文一票）+ 限流
    if (!is_user_logged_in()) {
        if (!jinyu_rate_limit_check('vote', 20, MINUTE_IN_SECONDS)) {
            wp_send_json_error(__('操作过于频繁，请稍后再试', 'jinyu'));
        }
        $ip     = function_exists('jinyu_client_ip') ? jinyu_client_ip() : (isset($_SERVER['REMOTE_ADDR']) ? trim((string) $_SERVER['REMOTE_ADDR']) : '0.0.0.0');
        $dedupe = 'jinyu_vote_' . md5($ip . '|' . $pid);
        if (get_transient($dedupe)) {
            wp_send_json_error(__('已投票', 'jinyu'));
        }
        set_transient($dedupe, 1, DAY_IN_SECONDS * 30);
    }

    $key   = $dir === 'yes' ? '_jinyu_helpful_yes' : '_jinyu_helpful_no';
    $count = (int)get_post_meta($pid, $key, true) + 1;
    update_post_meta($pid, $key, $count);

    wp_send_json_success([
        'yes' => (int)get_post_meta($pid, '_jinyu_helpful_yes', true),
        'no'  => (int)get_post_meta($pid, '_jinyu_helpful_no', true),
    ]);
}
