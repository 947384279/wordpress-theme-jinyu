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
     * 分页导航。开启「加载更多」时输出按钮，否则输出传统分页 + 页码直达。
     *
     * 全站唯一分页出口：首页/日期归档/分类/标签/搜索/作者/系列页都走这里，
     * 避免原生 the_posts_pagination() 与本函数并存导致样式与功能两套实现。
     *
     * @param array $args {
     *     @type bool $load_more 是否允许输出「加载更多」按钮。默认 true（首页/日期归档语义）；
     *                           分类等归档页传 false，保持经典分页不受该开关影响。
     * }
     */
    function jinyu_pagination($args = [])
    {
        global $wp_query;

        $total = isset($wp_query->max_num_pages) ? (int)$wp_query->max_num_pages : 1;
        if ($total <= 1) return;

        $load_more = !isset($args['load_more']) || $args['load_more'];

        if ($load_more && jinyu_is_checked('blog_show_load_more')) {
            $current = max(1, (int)get_query_var('paged'));
            printf(
                '<div class="jinyu-load-more-wrap"><button type="button" class="jinyu-load-more" data-page="%d" data-target=".jinyu-post-grid" data-total="%d">%s</button></div>',
                $current,
                $total,
                esc_html__('加载更多', 'jinyu')
            );
            return;
        }

        // base 必须携带 %#% 占位符，paginate_links 才能把页码替换进去。
        // 旧写法用 get_pagenum_link()（默认第 1 页的具体链接）做 base，
        // 不含占位符，导致所有页码都渲染成第一页 URL —— 点哪页都回第一页。
        $big       = 999999999;
        $current   = max(1, (int)(get_query_var('paged') ?: get_query_var('page')));
        // 未转义的 URL 供跳转框做模板；转义版交给 paginate_links（其内部会再 esc_url）。
        $raw       = get_pagenum_link($big);
        $base      = str_replace($big, '%#%', esc_url($raw));
        $jump_tpl  = str_replace($big, '__PAGE__', $raw);

        // 页码密度：手机端窄，只保留「首/末页 + 当前页」(mid_size=0 → ‹ 1 … 23 … 72 ›，
        // 共 7 项)，明显少于桌面，且保证与「跳转」按钮同处一行不换行；桌面端当前页左右各 2。
        // 用 wp_is_mobile() 在服务端判定（比纯 CSS 隐藏更彻底：窄屏根本不输出多余页码）。
        $is_mobile = function_exists('wp_is_mobile') && wp_is_mobile();
        $mid_size  = $is_mobile ? 0 : 2;
        $end_size  = 1;

        $links = paginate_links([
            'base'      => $base,
            'format'    => '?paged=%#%',
            'current'   => $current,
            'total'     => $total,
            'type'      => 'list',
            'mid_size'  => $mid_size,
            'end_size'  => $end_size,
            'prev_text' => '<i class="fa-solid fa-angle-left"></i>',
            'next_text' => '<i class="fa-solid fa-angle-right"></i>',
        ]);

        if (!$links) return;

        echo '<nav class="jinyu-pagination">' . $links;

        // 页码直达（hover 展开式）：默认只显示一个轻量「跳转 »」按钮，
        // 鼠标移入 / 键盘聚焦才展开输入框。data-base 复用 paginate_links 的链接模板
        // （页码位置换成 __PAGE__），由前端填值跳转，确保与分页链接同源、同格式（含搜索词等 query）。
        // 跳转是低频操作，折叠后可避免常显输入框稀释「当前页胶囊」这一唯一点。
        $chev_single = '<svg class="jinyu-jump-chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>';
        $chev_double = '<svg class="jinyu-jump-chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 6l6 6-6 6M13 6l6 6-6 6"/></svg>';
        printf(
            '<form class="jinyu-pagination-jump" data-base="%s" data-current="%d" data-total="%d">'
            . '<button type="button" class="jinyu-jump-btn">%s%s</button>'
            . '<span class="jinyu-jump-panel">'
            . '<input type="number" class="jinyu-page-jump-input" min="1" max="%d" step="1" inputmode="numeric" placeholder="%s" autocomplete="off" aria-label="%s">'
            . '<button type="submit" class="jinyu-jump-go" aria-label="%s">%s</button>'
            . '</span>'
            . '</form>',
            esc_attr($jump_tpl),
            $current,
            $total,
            esc_html__('跳转', 'jinyu'),
            $chev_single,
            $total,
            esc_attr__('页码', 'jinyu'),
            esc_attr(sprintf(__('输入 1 到 %d 之间的页码后跳转', 'jinyu'), $total)),
            esc_attr__('跳转', 'jinyu'),
            $chev_double
        );

        echo '</nav>';
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
        return sprintf(esc_html__('%d 分钟阅读', 'jinyu'), max(1, (int)ceil($count / 400)));
    }
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
        return number_format($count, 0, '.', ',') . ' ' . esc_html__('字', 'jinyu');
    }
}

if (!function_exists('jinyu_post_updated')) {
    /**
     * 「更新于 X」文案（列表卡片/可用于单篇）。
     * 仅当最后修改比发布晚 $min_days 天以上才返回，避免改个错别字就到处报「更新于」。
     *
     * @param int $post_id  文章 ID，留空取当前文章
     * @param int $min_days 触发阈值（天）
     * @return string 空字符串表示无需展示
     */
    function jinyu_post_updated($post_id = 0, int $min_days = 3): string
    {
        $post = get_post($post_id ?: get_the_ID());
        if (!$post) return '';

        $pub = (int) strtotime((string) $post->post_date);
        $mod = (int) strtotime((string) $post->post_modified);
        if (!$pub || !$mod || ($mod - $pub) < $min_days * DAY_IN_SECONDS) return '';

        $date = get_the_modified_date('Y-m-d', $post);
        return $date ? sprintf(esc_html__('更新于 %s', 'jinyu'), $date) : '';
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
        $html = '<nav class="jinyu-breadcrumbs" aria-label="' . esc_attr__('面包屑导航', 'jinyu') . '">';
        $html .= '<a class="jinyu-bread-home" href="' . esc_url(home_url('/')) . '"><i class="fa-solid fa-house" aria-hidden="true"></i>' . esc_html__('首页', 'jinyu') . '</a>';

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
            $html .= $sep . '<span ' . $cur . '>' . esc_html__('搜索:', 'jinyu') . ' ' . esc_html(get_search_query()) . '</span>';
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
            // Gitee 无 FA 免费品牌图标，用 simple-icons 官方路径（currentColor 跟随文字/悬停色）
            'gitee'    => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M11.984 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.016 0zm6.09 5.333c.328 0 .593.266.592.593v1.482a.594.594 0 0 1-.593.592H9.777c-.982 0-1.778.796-1.778 1.778v5.63c0 .327.266.592.593.592h5.63c.982 0 1.778-.796 1.778-1.778v-.296a.593.593 0 0 0-.592-.593h-4.15a.592.592 0 0 1-.592-.592v-1.482a.593.593 0 0 1 .593-.592h6.815c.327 0 .593.265.593.592v3.408a4 4 0 0 1-4 4H5.926a.593.593 0 0 1-.593-.593V9.778a4.444 4.444 0 0 1 4.445-4.444h8.296Z"/></svg>',
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
            // 知乎无 FA 免费品牌图标，用 simple-icons 官方路径
            '知乎'      => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M5.721 0C2.251 0 0 2.25 0 5.719V18.28C0 21.751 2.252 24 5.721 24h12.56C21.751 24 24 21.75 24 18.281V5.72C24 2.249 21.75 0 18.281 0zm1.964 4.078c-.271.73-.5 1.434-.68 2.11h4.587c.545-.006.445 1.168.445 1.171H9.384a58.104 58.104 0 01-.112 3.797h2.712c.388.023.393 1.251.393 1.266H9.183a9.223 9.223 0 01-.408 2.102l.757-.604c.452.456 1.512 1.712 1.906 2.177.473.681.063 2.081.063 2.081l-2.794-3.382c-.653 2.518-1.845 3.607-1.845 3.607-.523.468-1.58.82-2.64.516 2.218-1.73 3.44-3.917 3.667-6.497H4.491c0-.015.197-1.243.806-1.266h2.71c.024-.32.086-3.254.086-3.797H6.598c-.136.406-.158.447-.268.753-.594 1.095-1.603 1.122-1.907 1.155.906-1.821 1.416-3.6 1.591-4.064.425-1.124 1.671-1.125 1.671-1.125zM13.078 6h6.377v11.33h-2.573l-2.184 1.373-.401-1.373h-1.219zm1.313 1.219v8.86h.623l.263.937 1.455-.938h1.456v-8.86z"/></svg>',
            'zhihu'    => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M5.721 0C2.251 0 0 2.25 0 5.719V18.28C0 21.751 2.252 24 5.721 24h12.56C21.751 24 24 21.75 24 18.281V5.72C24 2.249 21.75 0 18.281 0zm1.964 4.078c-.271.73-.5 1.434-.68 2.11h4.587c.545-.006.445 1.168.445 1.171H9.384a58.104 58.104 0 01-.112 3.797h2.712c.388.023.393 1.251.393 1.266H9.183a9.223 9.223 0 01-.408 2.102l.757-.604c.452.456 1.512 1.712 1.906 2.177.473.681.063 2.081.063 2.081l-2.794-3.382c-.653 2.518-1.845 3.607-1.845 3.607-.523.468-1.58.82-2.64.516 2.218-1.73 3.44-3.917 3.667-6.497H4.491c0-.015.197-1.243.806-1.266h2.71c.024-.32.086-3.254.086-3.797H6.598c-.136.406-.158.447-.268.753-.594 1.095-1.603 1.122-1.907 1.155.906-1.821 1.416-3.6 1.591-4.064.425-1.124 1.671-1.125 1.671-1.125zM13.078 6h6.377v11.33h-2.573l-2.184 1.373-.401-1.373h-1.219zm1.313 1.219v8.86h.623l.263.937 1.455-.938h1.456v-8.86z"/></svg>',
            'b站'       => 'fa-brands fa-bilibili',
            'bilibili' => 'fa-brands fa-bilibili',
            'rss'      => 'fa-solid fa-rss',
            // 抖音无 FA 免费品牌图标；抖音即 TikTok 国内版，用 fa-tiktok 品牌标最贴近
            '抖音'      => 'fa-brands fa-tiktok',
            'douyin'   => 'fa-brands fa-tiktok',
            // 豆瓣无 FA 免费品牌图标，用 simple-icons 官方路径
            '豆瓣'      => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M.51 3.06h22.98V.755H.51V3.06Zm20.976 2.537v9.608h-2.137l-1.669 5.76H24v2.28H0v-2.28h6.32l-1.67-5.76H2.515V5.597h18.972Zm-5.066 9.608H7.58l1.67 5.76h5.501l1.67-5.76ZM18.367 7.9H5.634v5.025h12.733V7.9Z"/></svg>',
            'douban'   => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M.51 3.06h22.98V.755H.51V3.06Zm20.976 2.537v9.608h-2.137l-1.669 5.76H24v2.28H0v-2.28h6.32l-1.67-5.76H2.515V5.597h18.972Zm-5.066 9.608H7.58l1.67 5.76h5.501l1.67-5.76ZM18.367 7.9H5.634v5.025h12.733V7.9Z"/></svg>',
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

if (!function_exists('jinyu_social_icon_markup')) {
    /**
     * 输出社交图标标记。
     * - 以 `<svg` 开头的为内置可信 SVG（Gitee/知乎/豆瓣等无 FA 免费品牌图标的平台），直接输出、不转义；
     * - 其余为 Font Awesome class，包一层 <i> 并转义 class。
     */
    function jinyu_social_icon_markup(string $icon): string
    {
        if (strncmp($icon, '<svg', 4) === 0) {
            return $icon;
        }
        return '<i class="' . esc_attr($icon) . '" aria-hidden="true"></i>';
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
            home_url('/')          => __('首页', 'jinyu'),
            home_url('/archives/') => __('文章归档', 'jinyu'),
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
        $list = jinyu_get_option('home_four_grid_list');
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

