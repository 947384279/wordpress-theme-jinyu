<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 金玉主题 · 模板标签
 * 统一输出层：所有面向模板的输出函数集中在此，必须做转义。
 */

if (!function_exists('jinyu_pagination')) {
    /**
     * 分页导航。开启「加载更多」时输出按钮，否则输出传统分页。
     */
    function jinyu_pagination()
    {
        global $wp_query;

        $total = isset($wp_query->max_num_pages) ? (int)$wp_query->max_num_pages : 1;
        if ($total <= 1) return;

        if (jinyu_is_checked('blog_show_load_more')) {
            $current = max(1, (int)get_query_var('paged'));
            printf(
                '<div class="jinyu-load-more-wrap"><button type="button" class="jinyu-load-more" data-page="%d" data-target=".jinyu-post-grid" data-total="%d">%s</button></div>',
                $current,
                $total,
                esc_html__('加载更多', JINYU)
            );
            return;
        }

        $links = paginate_links([
            'base'      => str_replace('%_%', '%#%', esc_url(get_pagenum_link())),
            'format'    => '?paged=%#%',
            'current'   => max(1, (int)get_query_var('paged')),
            'total'     => $total,
            'type'      => 'list',
            'prev_text' => '<i class="fa-solid fa-angle-left"></i>',
            'next_text' => '<i class="fa-solid fa-angle-right"></i>',
        ]);

        if ($links) {
            echo '<nav class="jinyu-pagination">' . $links . '</nav>';
        }
    }
}

if (!function_exists('jinyu_read_time')) {
    /**
     * 预估阅读时长（中文按 400 字/分钟）
     */
    function jinyu_read_time($post_id = 0)
    {
        $post_id = $post_id ?: get_the_ID();
        $content = get_post_field('post_content', $post_id);
        $count   = mb_strlen(preg_replace('/\s+/', '', strip_tags((string)$content)), 'UTF-8');
        return sprintf(esc_html__('%d 分钟阅读', JINYU), max(1, (int)ceil($count / 400)));
    }

    if (!function_exists('jinyu_post_word_count')) {
        /**
         * 正文纯文字数（中英文统一按字符计），带千分位
         */
        function jinyu_post_word_count($post_id = 0)
        {
            $post_id = $post_id ?: get_the_ID();
            $content = get_post_field('post_content', $post_id);
            $count   = mb_strlen(preg_replace('/\s+/', '', strip_tags((string)$content)), 'UTF-8');
            return number_format($count, 0, '.', ',') . ' ' . esc_html__('字', JINYU);
        }
    }
}

if (!function_exists('jinyu_breadcrumbs')) {
    /**
     * 面包屑导航
     */
    function jinyu_breadcrumbs()
    {
        $sep = '<span class="jinyu-bread-sep" aria-hidden="true"><i class="fa-solid fa-angle-right"></i></span>';
        $cur = 'class="jinyu-bread-current" aria-current="page"';
        $html = '<nav class="jinyu-breadcrumbs" aria-label="' . esc_attr__('面包屑导航', JINYU) . '">';
        $html .= '<a class="jinyu-bread-home" href="' . esc_url(home_url('/')) . '"><i class="fa-solid fa-house" aria-hidden="true"></i>' . esc_html__('首页', JINYU) . '</a>';

        if (is_single()) {
            $cats = get_the_category();
            if ($cats) {
                $html .= $sep . '<a href="' . esc_url(get_category_link($cats[0]->term_id)) . '">' . esc_html($cats[0]->name) . '</a>';
            }
            $html .= $sep . '<span ' . $cur . '>' . esc_html(get_the_title()) . '</span>';
        } elseif (is_category()) {
            $html .= $sep . '<span ' . $cur . '>' . esc_html(single_cat_title('', false)) . '</span>';
        } elseif (is_tag()) {
            $html .= $sep . '<span ' . $cur . '>#' . esc_html(single_tag_title('', false)) . '</span>';
        } elseif (is_search()) {
            $html .= $sep . '<span ' . $cur . '>' . esc_html__('搜索:', JINYU) . ' ' . esc_html(get_search_query()) . '</span>';
        } elseif (is_author()) {
            $html .= $sep . '<span ' . $cur . '>' . esc_html(get_the_author()) . '</span>';
        } elseif (is_page()) {
            $html .= $sep . '<span ' . $cur . '>' . esc_html(get_the_title()) . '</span>';
        } elseif (is_404()) {
            $html .= $sep . '<span ' . $cur . '>404</span>';
        }

        echo $html . '</nav>';
    }
}

if (!function_exists('jinyu_footer_copyright')) {
    /**
     * 页脚版权兜底文案（无自定义时返回默认）。
     * 自定义版权统一在「页脚设置 › 版权信息文案」(footer_copyright) 配置，由 footer.php 负责占位符替换。
     */
    function jinyu_footer_copyright()
    {
        return '&copy; {year} {name}';
    }
}

if (!function_exists('jinyu_parse_social')) {
    /**
     * 解析「平台|链接」格式的社交配置，返回 [{icon, title, url}]
     */
    function jinyu_parse_social(string $raw): array
    {
        $raw = trim($raw);
        if (!$raw) return [];

        $map = [
            'github'   => 'fa-brands fa-github',
            'gitee'    => 'fa-solid fa-code-branch',
            'gitlab'   => 'fa-brands fa-gitlab',
            '微博'      => 'fa-brands fa-weibo',
            'weibo'    => 'fa-brands fa-weibo',
            '微信'      => 'fa-brands fa-weixin',
            'weixin'   => 'fa-brands fa-weixin',
            'qq'       => 'fa-brands fa-qq',
            '邮箱'      => 'fa-solid fa-envelope',
            'email'    => 'fa-solid fa-envelope',
            'mail'     => 'fa-solid fa-envelope',
            'telegram' => 'fa-brands fa-telegram',
            'x'        => 'fa-brands fa-x-twitter',
            'twitter'  => 'fa-brands fa-x-twitter',
            '知乎'      => 'fa-solid fa-graduation-cap',
            'zhihu'    => 'fa-solid fa-graduation-cap',
            'b站'       => 'fa-brands fa-bilibili',
            'bilibili' => 'fa-brands fa-bilibili',
            'rss'      => 'fa-solid fa-rss',
            '抖音'      => 'fa-solid fa-music',
            'douyin'   => 'fa-solid fa-music',
            '豆瓣'      => 'fa-solid fa-book',
            'douban'   => 'fa-solid fa-book',
            '今日头条' => 'fa-solid fa-newspaper',
            'toutiao'  => 'fa-solid fa-newspaper',
        ];

        $out = [];
        foreach (explode("\n", $raw) as $line) {
            $line = trim($line);
            if (!$line) continue;
            $parts = array_map('trim', explode('|', $line, 2));
            $name  = $parts[0];
            $url   = $parts[1] ?? '';
            $key   = strtolower($name);

            if ($key === 'rss' && !$url) $url = get_bloginfo('rss_url');
            if (!$url) continue;

            $icon = $map[$name] ?? $map[$key] ?? 'fa-solid fa-link';
            $out[] = ['icon' => $icon, 'title' => $name, 'url' => $url];
        }
        return $out;
    }
}

if (!function_exists('jinyu_footer_social')) {
    function jinyu_footer_social(): array
    {
        return jinyu_parse_social((string) jinyu_get_option('footer_social', ''));
    }
}

if (!function_exists('jinyu_header_social')) {
    /**
     * 页眉社交图标：优先用 header_social，未配置则复用页脚 footer_social
     */
    function jinyu_header_social(): array
    {
        $raw = trim((string) jinyu_get_option('header_social', ''));
        if ($raw) return jinyu_parse_social($raw);
        return jinyu_footer_social();
    }
}

/**
 * 为当前导航项补 `aria-current="page"`。
 * WP 核心只输出 current-menu-item / current_page_item 这两个 class，不带任何 ARIA 状态，
 * 屏幕阅读器无法获知「当前所在页」。此钩子属纯无障碍增强，不改动视觉表现。
 */
add_filter('nav_menu_link_attributes', function (array $atts, $item): array {
    $classes = isset($item->classes) && is_array($item->classes) ? $item->classes : [];
    if (in_array('current-menu-item', $classes, true) || in_array('current_page_item', $classes, true)) {
        $atts['aria-current'] = 'page';
    }
    return $atts;
}, 10, 2);

if (!function_exists('jinyu_default_nav')) {
    /**
     * 未设置主导航菜单时的兜底输出（wp_nav_menu 的 fallback_cb）
     */
    function jinyu_default_nav()
    {
        $items = [
            home_url('/')          => __('首页', JINYU),
            home_url('/archives/') => __('文章归档', JINYU),
        ];

        $cats = get_categories(['number' => 3, 'orderby' => 'count', 'order' => 'DESC']);
        foreach ($cats as $cat) {
            $items[get_category_link($cat->term_id)] = $cat->name;
        }

        echo '<ul id="jinyu-primary-nav" class="jinyu-nav">';
        foreach ($items as $url => $label) {
            printf(
                '<li><a href="%s">%s</a></li>',
                esc_url($url),
                esc_html($label)
            );
        }
        echo '</ul>';
    }
}

if (!function_exists('jinyu_get_post_likes')) {
    /**
     * 获取文章点赞数（存 post meta，避免污染 options 表）
     */
    function jinyu_get_post_likes($post_id = 0)
    {
        $post_id = $post_id ?: get_the_ID();
        return (int)get_post_meta($post_id, 'jinyu_likes', true);
    }
}

if (!function_exists('jinyu_is_liked')) {
    /**
     * 当前访客是否已点赞（已登录读 user meta，未登录读 cookie）
     */
    function jinyu_is_liked($post_id = 0)
    {
        $post_id = $post_id ?: get_the_ID();

        if (is_user_logged_in()) {
            return in_array((int)$post_id, jinyu_meta_ids(get_current_user_id(), 'jinyu_liked_posts'), true);
        }

        return !empty($_COOKIE['jinyu_liked_' . $post_id]);
    }
}

if (!function_exists('jinyu_meta_ids')) {
    /**
     * 读取「ID 列表」类 user meta（点赞/收藏/关注等），统一清洗：
     * - meta 不存在时 get_user_meta 返回 ''，(array)'' = ['']，intval 后是 [0]（假非空）
     * - 历史脏数据里可能混有 0，必须过滤，否则统计虚高、查询空转
     *
     * @return int[] 已去 0 / 去重 / 重建索引的 ID 列表
     */
    function jinyu_meta_ids(int $uid, string $key): array
    {
        if ($uid <= 0) return [];
        $raw = get_user_meta($uid, $key, true);
        if ($raw === '' || $raw === null || $raw === false) return [];
        $ids = array_map('intval', (array)$raw);
        return array_values(array_unique(array_filter($ids)));
    }
}

if (!function_exists('jinyu_get_user_favs')) {
    /**
     * 用户收藏的文章 ID 列表
     *
     * @param int $user_id 默认当前用户；0 且未登录时返回空数组
     */
    function jinyu_get_user_favs($user_id = 0)
    {
        $uid = $user_id ? (int)$user_id : get_current_user_id();
        return jinyu_meta_ids($uid, 'jinyu_fav_posts');
    }
}

if (!function_exists('jinyu_get_theme_mode')) {
    /**
     * 当前主题模式：dark / light / ''（空表示跟随系统）
     * 优先读取脚本写入的 cookie，保证服务端渲染与客户端一致，避免首屏闪烁。
     */
    function jinyu_get_theme_mode()
    {
        // 访客手动选择优先（cookie 由前端切换时写入），保证服务端首屏与客户端一致、避免闪烁；
        // 现所有模式前台均可切换，故覆盖对 light/dark/auto 一律生效。
        $cookie = isset($_COOKIE['jinyu-theme']) ? sanitize_key($_COOKIE['jinyu-theme']) : '';
        if (in_array($cookie, ['dark', 'light'], true)) {
            return $cookie;
        }
        $mode = jinyu_get_option('theme_mode', 'auto');
        // 兼容升级前残留的 'switch' 值（旧「跟随系统可切换」），统一视为跟随系统
        if ($mode === 'switch') {
            $mode = 'auto';
        }
        // auto 返回空（由 CSS prefers-color-scheme 决定），固定模式返回其值
        return ($mode === 'auto') ? '' : $mode;
    }
}

if (!function_exists('jinyu_html_attrs')) {
    /**
     * 输出 <html> 标签需要的属性（主题模式、灰度模式）
     */
    function jinyu_html_attrs()
    {
        $mode = jinyu_get_theme_mode();
        $out  = '';
        if ($mode) {
            $out .= ' data-theme="' . esc_attr($mode) . '"';
        }
        if (jinyu_is_checked('grey')) {
            $out .= ' class="jinyu-grey"';
        }
        echo $out;
    }
}

if (!function_exists('jinyu_cms_four_grid_items')) {
    /**
     * 取首页四宫格可用项：前 $limit 个「未隐藏且已设置图片」的项目。
     * 数据来自后台「CMS布局 → 首页四宫格列表」的拖拽配置（dynamic-list）。
     */
    function jinyu_cms_four_grid_items(int $limit = 4)
    {
        $list = jinyu_get_option('cms_four_grid_list');
        // 后台 dynamic-list 字段以 JSON 字符串落库（见 admin.js 的 syncDyn），
        // 此处兼容 JSON 字符串与数组两种形态，避免前台拿不到数据。
        if (is_string($list)) {
            $decoded = json_decode($list, true);
            $list = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($list) || count($list) <= 0) {
            return false;
        }
        $limit = max(1, (int) $limit);
        $ava = [];
        foreach ($list as $item) {
            if (count($ava) >= $limit || !is_array($item)) {
                continue;
            }
            $hidden = !empty($item['hide']) && filter_var($item['hide'], FILTER_VALIDATE_BOOLEAN);
            if ($hidden || empty($item['img'])) {
                continue;
            }
            $ava[] = [
                'title' => $item['title'] ?? '',
                'img'   => $item['img']   ?? '',
                'link'  => $item['link']  ?? '',
                'blank' => !empty($item['blank']) && filter_var($item['blank'], FILTER_VALIDATE_BOOLEAN),
            ];
        }
        return count($ava) > 0 ? $ava : false;
    }
}

if (!function_exists('jinyu_home_banner_post')) {
    /**
     * 首页首屏 Banner 文章：第一篇置顶文章。
     *
     * 必须由此函数统一判定，供 index.php（渲染 banner）与 pre_get_posts（主查询去重）
     * 共用同一份逻辑——两处各写一遍迟早漂移，会出现「排除了 A 却展示 B」的错位。
     *
     * 本函数不做 is_paged() 判断：该状态在 pre_get_posts 阶段尚未写入全局查询对象，
     * 由调用方各自判定（模板用 is_paged()，主查询用 $q->is_paged）。
     *
     * @return WP_Post|null 无可用置顶文章时返回 null
     */
    function jinyu_home_banner_post()
    {
        static $banner = false; // false = 尚未解析；null = 已解析且无结果
        if ($banner !== false) {
            return $banner;
        }

        $banner = null;
        $sticky = get_option('sticky_posts');
        if (!empty($sticky) && is_array($sticky)) {
            $candidate = get_post((int) $sticky[0]);
            if ($candidate && $candidate->post_status === 'publish' && !post_password_required($candidate)) {
                $banner = $candidate;
            }
        }
        return $banner;
    }
}
