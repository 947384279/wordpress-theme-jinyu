<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if (!function_exists('jinyu_widget_title')) {
    /**
     * 输出小工具标题，允许 FontAwesome 图标标签。
     */
    function jinyu_widget_title($title) {
        $allowed = [
            'i' => ['class' => true, 'aria-hidden' => true],
        ];
        return wp_kses($title, $allowed);
    }
}

if (!function_exists('jinyu_widget_on')) {
    /**
     * 读取小工具开关型字段。
     *
     * 从未保存过的新实例 $instance 为空数组，此时按 $default 处理，
     * 这样刚拖进侧栏的小工具就是「开」的，不需要先去后台点一圈。
     *
     * @param array|mixed $instance 小工具实例设置
     * @param string      $key      字段名
     * @param bool        $default  字段缺失时的默认值
     * @return bool
     */
    function jinyu_widget_on($instance, string $key, bool $default = true): bool
    {
        $instance = (array) $instance;
        return array_key_exists($key, $instance) ? (bool) $instance[$key] : $default;
    }
}

if (!function_exists('jinyu_tag_cloud_html')) {
    /**
     * 标签云标签的**唯一**渲染实现（侧栏默认卡与「金玉·标签云」小工具共用）。
     *
     * 不用 wp_tag_cloud()：flat 模式下核心不输出计数，且它给每个链接内联 font-size，
     * 主题必须用 !important 才能统一字号，反而把「热度分级」压死了。
     * 这里统一输出：<a class="[is-hot]"><span class=jinyu-tag-name><span class=jinyu-tag-count>，
     * 并把热度比（0~1）写进 --jinyu-tag-ratio，字号/配色分级全部交给 CSS。
     *
     * @param int $num 最多输出多少个标签（自动收敛到 5~50）
     * @return string 已逐字段转义的 HTML；无标签时返回空串
     */
    function jinyu_tag_cloud_html(int $num = 20): string
    {
        $tags = get_tags([
            'orderby'    => 'count',
            'order'      => 'DESC',
            'number'     => max(5, min(50, $num)),
            'hide_empty' => true,
        ]);
        if (!$tags) return '';

        $first = reset($tags);              // 已按 count DESC 排序，首项即最热
        $max   = max(1, (int) $first->count);

        $out = '';
        foreach ($tags as $t) {
            $ratio = min(1, $t->count / $max);
            $out .= '<a href="' . esc_url(get_tag_link($t)) . '"'
                . ($ratio >= .5 ? ' class="is-hot"' : '')
                . ' style="--jinyu-tag-ratio:' . round($ratio, 2) . '">'
                . '<span class="jinyu-tag-name">' . esc_html($t->name) . '</span>'
                . '<span class="jinyu-tag-count" aria-hidden="true">' . (int) $t->count . '</span></a>';
        }
        return $out;
    }
}

class Jinyu_Author_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct('jinyu_author', __('金玉·作者卡', JINYU), ['classname'=>'widget_jinyu_author','description'=>__('作者信息卡片', JINYU)]);
    }
    public function widget($args, $instance) {
        echo $args['before_widget'];
        echo '<div class="jinyu-author-card">';
        echo '<div class="jinyu-author-cover"></div>';
        echo '<div class="jinyu-author-avatar">' . self::avatar_html($instance) . '</div>';
        echo '<div class="jinyu-author-info">';
        echo '<div class="jinyu-author-name">' . esc_html($instance['name'] ?? get_bloginfo('name')) . '</div>';
        echo '<div class="jinyu-author-desc">' . esc_html($instance['desc'] ?? __('欢迎来到我的博客', JINYU)) . '</div>';
        if (!empty($instance['url'])) echo '<a class="jinyu-author-btn" href="' . esc_url($instance['url']) . '">' . esc_html($instance['btn'] ?? __('了解更多', JINYU)) . '</a>';
        echo '</div></div>';
        echo $args['after_widget'];
    }
    /**
     * 作者卡头像，优先级：
     *   1. 实例配置的图片地址（站点 Logo）
     *   2. 站点图标（外观 › 自定义 › 站点身份）——正方形品牌标记
     *   3. 后台「头像来源」= letter → 首字母占位 SVG（与评论/头部一致，不联网、永不破图）
     *   4. 其余来源走 Gravatar 协议（真实源站由 inc/fun/user.php 的全站过滤器决定），
     *      并挂 onerror 兜底首字母占位，避免头像服务不可达时留个破图
     *
     * 站点图标优于 header 的宽幅字标 logo：后者塞进圆里会变形。
     * 第 3 步的字母取**卡片名称**而非管理员 display_name——站点卡代表站点，与卡片标题一致。
     * 注意 data URI 必须 esc_attr（esc_url 协议白名单不含 data: 会清空成 src=""）。
     */
    private static function avatar_html($instance) {
        $name   = trim((string) ($instance['name'] ?? ''));
        $letter = jinyu_letter_avatar($name !== '' ? $name : (string) get_bloginfo('name'), 96);
        $attr   = ' alt="" width="96" height="96" loading="lazy" decoding="async"';

        // 1) 实例配置的图片
        $url = trim((string) ($instance['avatar'] ?? ''));
        if (preg_match('~^(https?:)?//~i', $url)) {
            return '<img src="' . esc_url($url) . '"' . $attr . '>';
        }
        // 2) 站点图标
        $icon = (string) get_site_icon_url(192);
        if (preg_match('~^(https?:)?//~i', $icon)) {
            return '<img src="' . esc_url($icon) . '"' . $attr . '>';
        }
        // 3) letter 模式：不依赖任何头像服务器
        if (jinyu_get_option('comment_avatar_src', 'gravatar') === 'letter') {
            return '<img src="' . esc_attr($letter) . '"' . $attr . '>';
        }
        // 4) 头像服务；取 192 让高分屏不糊，挂 onerror 兜底
        $src = (string) get_avatar_url((int) (get_the_author_meta('ID') ?: 1), ['size' => 192]);
        if ($src === '') {
            return '<img src="' . esc_attr($letter) . '"' . $attr . '>';
        }
        return '<img src="' . esc_url($src) . '" onerror="this.onerror=null;this.src=\''
            . esc_attr($letter) . '\'"' . $attr . '>';
    }
    public function form($instance) {
        $name = $instance['name'] ?? '';
        $desc = $instance['desc'] ?? '';
        $url  = $instance['url']  ?? '';
        $btn  = $instance['btn'] ?? __('了解更多', JINYU);
        $img  = $instance['avatar'] ?? '';
        echo "<p>" . esc_html__('名称', JINYU) . ": <input name='{$this->get_field_name('name')}' value='" . esc_attr($name) . "' class='widefat'></p>";
        echo "<p>" . esc_html__('描述', JINYU) . ": <textarea name='{$this->get_field_name('desc')}' class='widefat'>" . esc_textarea($desc) . "</textarea></p>";
        echo "<p>" . esc_html__('头像图片地址', JINYU) . ": <input name='{$this->get_field_name('avatar')}' value='" . esc_attr($img) . "' class='widefat' placeholder='https://'><br><span class='description'>" . esc_html__('留空则自动使用站点图标（外观 › 自定义 › 站点身份）；未设置时按全站「头像来源」显示', JINYU) . "</span></p>";
        echo "<p>" . esc_html__('链接', JINYU) . ": <input name='{$this->get_field_name('url')}' value='" . esc_attr($url) . "' class='widefat'></p>";
        echo "<p>" . esc_html__('按钮文字', JINYU) . ": <input name='{$this->get_field_name('btn')}' value='" . esc_attr($btn) . "' class='widefat'></p>";
    }
    public function update($new, $old) { return $new; }
}

class Jinyu_Notice_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct('jinyu_notice', __('金玉·公告', JINYU), ['classname'=>'widget_jinyu_notice','description'=>__('站点公告', JINYU)]);
    }
    public function widget($args, $instance) {
        echo $args['before_widget'];
        echo '<div class="jinyu-notice-widget">';
        echo $args['before_title'] . jinyu_widget_title($instance['title'] ?? __('<i class="fa-solid fa-bullhorn"></i> 公告', JINYU)) . $args['after_title'];
        echo '<div class="jinyu-notice-content">' . wp_kses_post($instance['content'] ?? '') . '</div>';
        echo '</div>';
        echo $args['after_widget'];
    }
    public function form($instance) {
        $title = $instance['title'] ?? __('<i class="fa-solid fa-bullhorn"></i> 公告', JINYU);
        $content = $instance['content'] ?? '';
        echo "<p>" . esc_html__('标题', JINYU) . ": <input name='{$this->get_field_name('title')}' value='" . esc_attr($title) . "' class='widefat'></p>";
        echo "<p>" . esc_html__('内容', JINYU) . ": <textarea name='{$this->get_field_name('content')}' rows='4' class='widefat'>" . esc_textarea($content) . "</textarea></p>";
    }
    public function update($new, $old) { return $new; }
}

class Jinyu_Posts_Hot_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct('jinyu_hot', __('金玉·热门文章', JINYU), ['classname'=>'widget_jinyu_hot','description'=>__('按浏览量排序', JINYU)]);
    }
    public function widget($args, $instance) {
        $title = $instance['title'] ?? __('<i class="fa-solid fa-fire"></i> 热门', JINYU);
        $num   = (int)($instance['num'] ?? 5);
        echo $args['before_widget'];
        echo $args['before_title'] . jinyu_widget_title($title) . $args['after_title'];
        // 数据源与侧栏默认卡一致（见 inc/fun/related.php: jinyu_get_hot_posts）
        $posts = jinyu_get_hot_posts($num);
        echo '<ul class="jinyu-hot-list">';
        if ($posts) {
            foreach ($posts as $i => $p) {
                echo '<li class="jinyu-hot-item">';
                echo '<span class="jinyu-hot-rank jinyu-rank-' . min(3, $i + 1) . '">' . ($i + 1) . '</span>';
                echo '<div class="jinyu-hot-info">';
                echo '<div class="jinyu-hot-title"><a href="' . esc_url(get_permalink($p)) . '">' . get_the_title($p) . '</a></div>';
                if (jinyu_show_views()) {
                    echo '<div class="jinyu-hot-views"><i class="fa-regular fa-eye" aria-hidden="true"></i> ' . (int) jinyu_get_post_views($p->ID) . '</div>';
                }
                echo '</div></li>';
            }
        } else {
            echo '<li class="jinyu-hot-item"><span class="jinyu-hot-rank">1</span>';
            echo '<div class="jinyu-hot-info"><div class="jinyu-hot-title">' . esc_html__('暂无热门文章', JINYU) . '</div></div></li>';
        }
        echo '</ul>';
        echo $args['after_widget'];
    }
    public function form($instance) {
        $title = $instance['title'] ?? __('<i class="fa-solid fa-fire"></i> 热门', JINYU);
        $num   = $instance['num'] ?? 5;
        echo "<p>" . esc_html__('标题', JINYU) . ": <input name='{$this->get_field_name('title')}' value='" . esc_attr($title) . "' class='widefat'></p>";
        echo "<p>" . esc_html__('数量', JINYU) . ": <input type='number' name='{$this->get_field_name('num')}' value='" . esc_attr($num) . "' class='widefat' min='1' max='10'></p>";
    }
    public function update($new, $old) { return $new; }
}

class Jinyu_Tag_Cloud_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct('jinyu_tags', __('金玉·标签云', JINYU), ['classname'=>'widget_jinyu_tags','description'=>__('标签云', JINYU)]);
    }
    public function widget($args, $instance) {
        $title = $instance['title'] ?? __('<i class="fa-solid fa-tags"></i> 标签', JINYU);
        $html  = jinyu_tag_cloud_html(max(5, min(50, (int)($instance['num'] ?? 20))));
        if ($html === '') return;   // 无标签不输出空卡片

        echo $args['before_widget'];
        echo $args['before_title'] . jinyu_widget_title($title) . $args['after_title'];
        echo '<div class="jinyu-tag-cloud">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- jinyu_tag_cloud_html() 内已逐字段转义
        echo $args['after_widget'];
    }
    public function form($instance) {
        $title = $instance['title'] ?? __('<i class="fa-solid fa-tags"></i> 标签', JINYU);
        $num   = $instance['num'] ?? 20;
        echo "<p>" . esc_html__('标题', JINYU) . ": <input name='{$this->get_field_name('title')}' value='" . esc_attr($title) . "' class='widefat'></p>";
        echo "<p>" . esc_html__('数量', JINYU) . ": <input type='number' name='{$this->get_field_name('num')}' value='" . esc_attr($num) . "' class='widefat' min='5' max='50'></p>";
    }
    public function update($new, $old) { return $new; }
}

class Jinyu_Archive_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct('jinyu_archive', __('金玉·归档', JINYU), ['classname'=>'widget_jinyu_archive','description'=>__('按月归档', JINYU)]);
    }
    public function widget($args, $instance) {
        $title = $instance['title'] ?? __('<i class="fa-regular fa-calendar-days"></i> 归档', JINYU);
        $limit = (int)($instance['limit'] ?? 12);
        echo $args['before_widget'];
        echo $args['before_title'] . jinyu_widget_title($title) . $args['after_title'];
        echo '<ul class="jinyu-widget-list">';
        wp_get_archives(['type'=>'monthly','limit'=>$limit,'show_post_count'=>1]);
        echo '</ul>';
        echo $args['after_widget'];
    }
    public function form($instance) {
        $title = $instance['title'] ?? __('<i class="fa-regular fa-calendar-days"></i> 归档', JINYU);
        $limit = $instance['limit'] ?? 12;
        echo "<p>" . esc_html__('标题', JINYU) . ": <input name='{$this->get_field_name('title')}' value='" . esc_attr($title) . "' class='widefat'></p>";
        echo "<p>" . esc_html__('数量', JINYU) . ": <input type='number' name='{$this->get_field_name('limit')}' value='" . esc_attr($limit) . "' class='widefat' min='3' max='36'></p>";
    }
    public function update($new, $old) { return $new; }
}

class Jinyu_Recent_Comments_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct('jinyu_recent_comments', __('金玉·最新评论', JINYU), ['classname'=>'widget_jinyu_recent_comments','description'=>__('显示最新评论及对应文章', JINYU)]);
    }
    public function widget($args, $instance) {
        $title = $instance['title'] ?? __('<i class="fa-regular fa-comment-dots"></i> 最新评论', JINYU);
        $num   = (int)($instance['num'] ?? 5);
        echo $args['before_widget'];
        echo $args['before_title'] . jinyu_widget_title($title) . $args['after_title'];
        // 与侧栏默认卡共用渲染函数（inc/fun/comment.php），结构契约见该函数注释
        $list = jinyu_recent_comments_list($num);
        if ($list) {
            echo $list; // phpcs:ignore WordPress.Security.EscapeOutput -- 内部函数已逐字段转义
        } else {
            echo '<p class="jinyu-widget-empty">' . esc_html__('暂无评论', JINYU) . '</p>';
        }
        echo $args['after_widget'];
    }
    public function form($instance) {
        $title = $instance['title'] ?? __('<i class="fa-regular fa-comment-dots"></i> 最新评论', JINYU);
        $num   = $instance['num'] ?? 5;
        echo "<p>" . esc_html__('标题', JINYU) . ": <input name='{$this->get_field_name('title')}' value='" . esc_attr($title) . "' class='widefat'></p>";
        echo "<p>" . esc_html__('数量', JINYU) . ": <input type='number' name='{$this->get_field_name('num')}' value='" . esc_attr($num) . "' class='widefat' min='1' max='15'></p>";
    }
    public function update($new, $old) { return $new; }
}

class Jinyu_Random_Posts_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct('jinyu_random', __('金玉·随机文章', JINYU), ['classname'=>'widget_jinyu_random','description'=>__('随机推荐文章', JINYU)]);
    }
    public function widget($args, $instance) {
        $title = $instance['title'] ?? __('<i class="fa-solid fa-shuffle"></i> 随机文章', JINYU);
        $num   = (int)($instance['num'] ?? 5);
        echo $args['before_widget'];
        echo $args['before_title'] . jinyu_widget_title($title) . $args['after_title'];

        // 取最近 100 篇 ID（按日期倒序，走索引、无 filesort），PHP 端打乱后截取 $num 条。
        // 彻底去除 orderby=rand 的全表排序；TTL 内所有访客共享同一份「稳定随机」结果。
        $pool = jinyu_cached_post_ids('random_posts_' . $num, 5 * MINUTE_IN_SECONDS, [
            'post_type'           => 'post',
            'posts_per_page'      => 100,
            'orderby'             => 'date',
            'order'               => 'DESC',
            'ignore_sticky_posts' => true,
        ]);
        $ids  = $pool;
        shuffle($ids);
        $ids  = array_slice($ids, 0, $num);
        $posts = jinyu_hydrate_posts($ids);
        if ($posts) {
            echo '<ul class="jinyu-widget-list jinyu-random-list">';
            foreach ($posts as $p) {
                echo '<li><a href="' . esc_url(get_permalink($p->ID)) . '">' . esc_html(get_the_title($p->ID)) . '</a></li>';
            }
            echo '</ul>';
        }
        echo $args['after_widget'];
    }
    public function form($instance) {
        $title = $instance['title'] ?? __('<i class="fa-solid fa-shuffle"></i> 随机文章', JINYU);
        $num   = $instance['num'] ?? 5;
        echo "<p>" . esc_html__('标题', JINYU) . ": <input name='{$this->get_field_name('title')}' value='" . esc_attr($title) . "' class='widefat'></p>";
        echo "<p>" . esc_html__('数量', JINYU) . ": <input type='number' name='{$this->get_field_name('num')}' value='" . esc_attr($num) . "' class='widefat' min='1' max='10'></p>";
    }
    public function update($new, $old) { return $new; }
}

class Jinyu_Search_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct('jinyu_search', __('金玉·搜索', JINYU), ['classname'=>'widget_jinyu_search','description'=>__('站内搜索框', JINYU)]);
    }
    public function widget($args, $instance) {
        $title = $instance['title'] ?? __('<i class="fa-solid fa-magnifying-glass"></i> 搜索', JINYU);
        echo $args['before_widget'];
        echo $args['before_title'] . jinyu_widget_title($title) . $args['after_title'];
        echo '<form class="jinyu-search-widget" role="search" method="get" action="' . esc_url(home_url('/')) . '">';
        echo '<input type="search" name="s" placeholder="' . esc_attr__('搜索…', JINYU) . '" aria-label="' . esc_attr__('搜索', JINYU) . '">';
        echo '<button type="submit" aria-label="' . esc_attr__('搜索', JINYU) . '"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></button>';
        echo '</form>';
        echo $args['after_widget'];
    }
    public function form($instance) {
        $title = $instance['title'] ?? __('<i class="fa-solid fa-magnifying-glass"></i> 搜索', JINYU);
        echo "<p>" . esc_html__('标题', JINYU) . ": <input name='{$this->get_field_name('title')}' value='" . esc_attr($title) . "' class='widefat'></p>";
    }
    public function update($new, $old) { return $new; }
}

class Jinyu_Categories_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct('jinyu_categories', __('金玉·分类目录', JINYU), ['classname'=>'widget_jinyu_categories','description'=>__('文章分类列表', JINYU)]);
    }
    public function widget($args, $instance) {
        $title = $instance['title'] ?? __('<i class="fa-regular fa-folder-open"></i> 分类', JINYU);
        $hide  = !empty($instance['hide_empty']);
        echo $args['before_widget'];
        echo $args['before_title'] . jinyu_widget_title($title) . $args['after_title'];
        $cats = get_categories(['hide_empty'=>$hide]);
        if ($cats) {
            echo '<ul class="jinyu-widget-list jinyu-cat-list">';
            foreach ($cats as $c) {
                echo '<li><a href="' . esc_url(get_category_link($c->term_id)) . '">' . esc_html($c->name) . '</a>';
                echo '<span class="jinyu-cat-count">' . (int)$c->count . '</span></li>';
            }
            echo '</ul>';
        }
        echo $args['after_widget'];
    }
    public function form($instance) {
        $title = $instance['title'] ?? __('<i class="fa-regular fa-folder-open"></i> 分类', JINYU);
        $hide  = $instance['hide_empty'] ?? 1;
        echo "<p>" . esc_html__('标题', JINYU) . ": <input name='{$this->get_field_name('title')}' value='" . esc_attr($title) . "' class='widefat'></p>";
        echo "<p><label><input type='checkbox' name='{$this->get_field_name('hide_empty')}' value='1'" . checked($hide, 1, false) . "> 隐藏空分类</label></p>";
    }
    public function update($new, $old) { return $new; }
}

class Jinyu_Links_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct('jinyu_links', __('金玉·友情链接', JINYU), ['classname'=>'widget_jinyu_links','description'=>__('展示站点的友情链接', JINYU)]);
    }
    public function widget($args, $instance) {
        $title = $instance['title'] ?? __('<i class="fa-solid fa-link"></i> 友情链接', JINYU);
        $num   = (int)($instance['num'] ?? 10);
        echo $args['before_widget'];
        echo $args['before_title'] . jinyu_widget_title($title) . $args['after_title'];
        $links = jinyu_get_links();
        if ($links) {
            echo '<ul class="jinyu-widget-list jinyu-links-list">';
            foreach (array_slice($links, 0, $num) as $l) {
                $url = get_post_meta($l->ID, 'jy_link_url', true);
                if (!$url) continue;
                echo '<li><a href="' . esc_url($url) . '" target="_blank" rel="noopener nofollow">' . esc_html($l->post_title) . '</a></li>';
            }
            echo '</ul>';
        } else {
            echo '<p class="jinyu-widget-empty">' . esc_html__('暂无友情链接', JINYU) . '</p>';
        }
        echo $args['after_widget'];
    }
    public function form($instance) {
        $title = $instance['title'] ?? __('<i class="fa-solid fa-link"></i> 友情链接', JINYU);
        $num   = $instance['num'] ?? 10;
        echo "<p>" . esc_html__('标题', JINYU) . ": <input name='{$this->get_field_name('title')}' value='" . esc_attr($title) . "' class='widefat'></p>";
        echo "<p>" . esc_html__('数量', JINYU) . ": <input type='number' name='{$this->get_field_name('num')}' value='" . esc_attr($num) . "' class='widefat' min='1' max='30'></p>";
    }
    public function update($new, $old) { return $new; }
}

class Jinyu_Stats_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct('jinyu_stats', __('金玉·站点统计', JINYU), ['classname'=>'widget_jinyu_stats','description'=>__('文章/页面/评论/分类/标签统计', JINYU)]);
    }
    public function widget($args, $instance) {
        $title = $instance['title'] ?? __('<i class="fa-solid fa-chart-simple"></i> 站点统计', JINYU);
        echo $args['before_widget'];
        echo $args['before_title'] . jinyu_widget_title($title) . $args['after_title'];
        $stats = [
            __('文章', JINYU) => (int) wp_count_posts('post')->publish,
            __('页面', JINYU) => (int) wp_count_posts('page')->publish,
            __('评论', JINYU) => (int) wp_count_comments()->approved,
            __('分类', JINYU) => (int) wp_count_terms('category'),
            __('标签', JINYU) => (int) wp_count_terms('post_tag'),
        ];
        echo '<ul class="jinyu-widget-list jinyu-stats-list">';
        foreach ($stats as $k => $v) {
            echo '<li><span>' . esc_html($k) . '</span><b>' . $v . '</b></li>';
        }
        echo '</ul>';
        echo $args['after_widget'];
    }
    public function form($instance) {
        $title = $instance['title'] ?? __('<i class="fa-solid fa-chart-simple"></i> 站点统计', JINYU);
        echo "<p>" . esc_html__('标题', JINYU) . ": <input name='{$this->get_field_name('title')}' value='" . esc_attr($title) . "' class='widefat'></p>";
    }
    public function update($new, $old) { return $new; }
}

class Jinyu_Related_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct('jinyu_related', __('金玉·相关文章', JINYU), ['classname'=>'widget_jinyu_related','description'=>__('当前文章的相关推荐（仅在文章页显示）', JINYU)]);
    }
    public function widget($args, $instance) {
        if (!is_singular('post')) return;
        $post_id = get_the_ID();
        if (!$post_id) return;
        $title = $instance['title'] ?? __('<i class="fa-solid fa-thumbs-up"></i> 相关文章', JINYU);
        $num   = (int)($instance['num'] ?? 5);
        // 复用文章页「相关文章」同一份缓存 ID 列表（type=tags），侧栏不再单跑 tax_query。
        $posts = jinyu_hydrate_posts(jinyu_get_related_post_ids($post_id, $num, 'tags'));
        if (!$posts) return;
        echo $args['before_widget'];
        echo $args['before_title'] . jinyu_widget_title($title) . $args['after_title'];
        echo '<ul class="jinyu-widget-list">';
        foreach ($posts as $p) {
            echo '<li><a href="' . esc_url(get_permalink($p->ID)) . '">' . esc_html(get_the_title($p->ID)) . '</a></li>';
        }
        echo '</ul>';
        echo $args['after_widget'];
    }
    public function form($instance) {
        $title = $instance['title'] ?? __('<i class="fa-solid fa-thumbs-up"></i> 相关文章', JINYU);
        $num   = $instance['num'] ?? 5;
        echo "<p>" . esc_html__('标题', JINYU) . ": <input name='{$this->get_field_name('title')}' value='" . esc_attr($title) . "' class='widefat'></p>";
        echo "<p>" . esc_html__('数量', JINYU) . ": <input type='number' name='{$this->get_field_name('num')}' value='" . esc_attr($num) . "' class='widefat' min='1' max='10'></p>";
    }
    public function update($new, $old) { return $new; }
}

class Jinyu_Menu_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct('jinyu_menu', __('金玉·自定义菜单', JINYU), ['classname'=>'widget_jinyu_menu','description'=>__('渲染指定的导航菜单', JINYU)]);
    }
    public function widget($args, $instance) {
        $title = $instance['title'] ?? '';
        $menu  = $instance['nav_menu'] ?? '';
        if (!$menu) return;
        echo $args['before_widget'];
        if ($title) echo $args['before_title'] . jinyu_widget_title($title) . $args['after_title'];
        wp_nav_menu(['menu'=>$menu,'container'=>false,'menu_class'=>'jinyu-menu-widget','depth'=>1,'fallback_cb'=>false]);
        echo $args['after_widget'];
    }
    public function form($instance) {
        $title = $instance['title'] ?? '';
        $menu  = $instance['nav_menu'] ?? '';
        $menus = wp_get_nav_menus();
        echo "<p>" . esc_html__('标题', JINYU) . ": <input name='{$this->get_field_name('title')}' value='" . esc_attr($title) . "' class='widefat'></p>";
        echo "<p>" . esc_html__('菜单', JINYU) . ": <select name='{$this->get_field_name('nav_menu')}' class='widefat'>";
        echo '<option value="">' . esc_html__('— 选择菜单 —', JINYU) . '</option>';
        foreach ($menus as $m) echo '<option value="' . $m->term_id . '"' . selected($menu, $m->term_id, false) . '>' . esc_html($m->name) . '</option>';
        echo '</select></p>';
    }
    public function update($new, $old) { return $new; }
}

class Jinyu_Hot_Comment_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct('jinyu_hot_comment', __('金玉·热评文章', JINYU), ['classname'=>'widget_jinyu_hot_comment','description'=>__('按评论数排序的热门文章', JINYU)]);
    }
    public function widget($args, $instance) {
        $title = $instance['title'] ?? __('<i class="fa-regular fa-comment-dots"></i> 热评文章', JINYU);
        $num   = (int)($instance['num'] ?? 5);
        $days  = (int)($instance['days'] ?? 0);
        echo $args['before_widget'];
        echo $args['before_title'] . jinyu_widget_title($title) . $args['after_title'];

        // 结果集缓存：按评论数排序 + 可能存在的 date_query 不必每次请求重跑。
        $cache_key = 'hot_comment_' . $num . '_' . $days;
        $ids = jinyu_cached_post_ids($cache_key, 10 * MINUTE_IN_SECONDS, [
            'post_type'              => 'post',
            'posts_per_page'         => $num,
            'ignore_sticky_posts'    => true,
            'orderby'                => 'comment_count',
            'order'                  => 'DESC',
            'update_post_term_cache' => false,
            'date_query'             => $days > 0 ? [['after' => ($days - 1) . ' days ago']] : [],
        ]);
        $posts = jinyu_hydrate_posts($ids);
        // 复用排行榜结构（.jinyu-hot-*），与热门文章视觉一致
        echo '<ul class="jinyu-hot-list jinyu-hot-comment-list">';
        if ($posts) {
            $i = 0;
            foreach ($posts as $p) {
                $i++;
                echo '<li class="jinyu-hot-item">';
                echo '<span class="jinyu-hot-rank jinyu-rank-' . min(3, $i) . '">' . $i . '</span>';
                echo '<div class="jinyu-hot-info">';
                echo '<div class="jinyu-hot-title"><a href="' . esc_url(get_permalink($p->ID)) . '">' . get_the_title($p->ID) . '</a></div>';
                echo '<div class="jinyu-hot-views"><i class="fa-regular fa-comment" aria-hidden="true"></i> ' . (int) get_comments_number($p->ID) . '</div>';
                echo '</div></li>';
            }
        } else {
            echo '<li class="jinyu-hot-item"><span class="jinyu-hot-rank">1</span>';
            echo '<div class="jinyu-hot-info"><div class="jinyu-hot-title">' . esc_html__('暂无评论', JINYU) . '</div></div></li>';
        }
        echo '</ul>';
        echo $args['after_widget'];
    }
    public function form($instance) {
        $title = $instance['title'] ?? __('<i class="fa-regular fa-comment-dots"></i> 热评文章', JINYU);
        $num   = $instance['num'] ?? 5;
        $days  = $instance['days'] ?? 0;
        echo "<p>" . esc_html__('标题', JINYU) . ": <input name='{$this->get_field_name('title')}' value='" . esc_attr($title) . "' class='widefat'></p>";
        echo "<p>" . esc_html__('数量', JINYU) . ": <input type='number' name='{$this->get_field_name('num')}' value='" . esc_attr($num) . "' class='widefat' min='1' max='10'></p>";
        echo "<p>" . esc_html__('仅近 N 天（0 = 不限）', JINYU) . ": <input type='number' name='{$this->get_field_name('days')}' value='" . esc_attr($days) . "' class='widefat' min='0' max='365'></p>";
    }
    public function update($new, $old) { return $new; }
}

class Jinyu_Reader_Wall_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct('jinyu_reader_wall', __('金玉·读者墙', JINYU), ['classname'=>'widget_jinyu_reader_wall','description'=>__('展示近期评论读者的头像墙', JINYU)]);
    }
    public function widget($args, $instance) {
        $title = $instance['title'] ?? __('<i class="fa-solid fa-users"></i> 读者墙', JINYU);
        $num   = (int)($instance['num'] ?? 20);
        echo $args['before_widget'];
        echo $args['before_title'] . jinyu_widget_title($title) . $args['after_title'];
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT comment_author, comment_author_email, comment_author_url
             FROM {$wpdb->comments}
             WHERE comment_approved = '1' AND comment_author_email <> ''
             ORDER BY comment_ID DESC LIMIT %d",
            $num
        ));
        if ($rows) {
            // 头像与「最新评论」同源：后台开启首字母模式时走离线首字母（Gravatar 被墙时不再破图），
            // 否则回落 Gravatar。避免读者墙与同站其它卡（最新评论/最近加入）的头像表现不一致。
            $letter_mode = jinyu_get_option('comment_avatar_src', 'gravatar') === 'letter';
            echo '<div class="jinyu-reader-wall">';
            foreach ($rows as $r) {
                if ($letter_mode) {
                    $avatar = '<img class="jinyu-avatar-img" src="' . esc_attr(jinyu_letter_avatar((string) $r->comment_author, 48)) . '" width="48" height="48" alt="">';
                } else {
                    $avatar = get_avatar($r->comment_author_email, 48, '', '', ['class' => 'jinyu-avatar-img']);
                }
                $name   = $r->comment_author;
                if (!empty($r->comment_author_url)) {
                    echo '<a class="jinyu-reader-avatar" href="' . esc_url($r->comment_author_url) . '" target="_blank" rel="noopener nofollow" title="' . esc_attr($name) . '">' . $avatar . '</a>';
                } else {
                    echo '<span class="jinyu-reader-avatar" title="' . esc_attr($name) . '">' . $avatar . '</span>';
                }
            }
            echo '</div>';
        } else {
            echo '<p class="jinyu-widget-empty">' . esc_html__('暂无读者', JINYU) . '</p>';
        }
        echo $args['after_widget'];
    }
    public function form($instance) {
        $title = $instance['title'] ?? __('<i class="fa-solid fa-users"></i> 读者墙', JINYU);
        $num   = $instance['num'] ?? 20;
        echo "<p>" . esc_html__('标题', JINYU) . ": <input name='{$this->get_field_name('title')}' value='" . esc_attr($title) . "' class='widefat'></p>";
        echo "<p>" . esc_html__('数量', JINYU) . ": <input type='number' name='{$this->get_field_name('num')}' value='" . esc_attr($num) . "' class='widefat' min='1' max='60'></p>";
    }
    public function update($new, $old) { return $new; }
}

class Jinyu_Recent_Viewed_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct('jinyu_recent_viewed', __('金玉·最近浏览', JINYU), ['classname'=>'widget_jinyu_recent_viewed','description'=>__('基于本地存储的浏览足迹（匿名可用）', JINYU)]);
    }
    public function widget($args, $instance) {
        $title = $instance['title'] ?? __('<i class="fa-regular fa-clock"></i> 最近浏览', JINYU);
        echo $args['before_widget'];
        echo $args['before_title'] . jinyu_widget_title($title) . $args['after_title'];
        // 内容由 jinyu.js 的 recentViewed 模块按 localStorage 填充
        echo '<div class="jinyu-recent-viewed" data-jinyu-recent></div>';
        echo $args['after_widget'];
    }
    public function form($instance) {
        $title = $instance['title'] ?? __('<i class="fa-regular fa-clock"></i> 最近浏览', JINYU);
        echo "<p>" . esc_html__('标题', JINYU) . ": <input name='{$this->get_field_name('title')}' value='" . esc_attr($title) . "' class='widefat'></p>";
    }
    public function update($new, $old) { return $new; }
}

if (!function_exists('jinyu_widget_lines')) {
    /**
     * 解析小工具后台的多行配置，每行「值|值2|值3」，空行忽略。
     * 「图文推荐位」与「每日一句」共用同一套写法，用户学一次就够。
     *
     * @return array<int, array<int, string>>
     */
    function jinyu_widget_lines($raw)
    {
        $rows = [];
        foreach (preg_split('/\r\n|\r|\n/', (string) $raw) as $line) {
            if (trim($line) === '') continue;
            $rows[] = array_map('trim', explode('|', $line));
        }
        return $rows;
    }
}

class Jinyu_Gallery_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct('jinyu_gallery', __('金玉·图文推荐位', JINYU), ['classname'=>'widget_jinyu_gallery','description'=>__('图片推荐 / 广告位；手动填图或自动取分类缩略图', JINYU)]);
    }

    public function widget($args, $instance) {
        $title  = $instance['title'] ?? __('<i class="fa-regular fa-images"></i> 推荐', JINYU);
        $source = ($instance['source'] ?? 'manual') === 'cat' ? 'cat' : 'manual';
        $cols   = max(1, min(2, (int)($instance['cols'] ?? 1)));
        $items  = $source === 'cat'
            ? $this->items_from_posts((int)($instance['cat'] ?? 0), max(1, min(8, (int)($instance['num'] ?? 4))))
            : $this->items_from_manual($instance['items'] ?? '');

        if (!$items) return;   // 没内容就不输出空卡片

        echo $args['before_widget'];
        echo $args['before_title'] . jinyu_widget_title($title) . $args['after_title'];
        echo '<div class="jinyu-gallery jinyu-gallery-cols-' . $cols . '">';
        foreach ($items as $it) {
            if ($it['url'] !== '') {
                echo '<a class="jinyu-gallery-item" href="' . esc_url($it['url']) . '"' . ($it['blank'] ? ' target="_blank" rel="noopener nofollow"' : '') . '>';
            } else {
                echo '<span class="jinyu-gallery-item">';
            }
            echo '<img src="' . esc_url(jinyu_img_to_webp_url($it['img'])) . '" alt="' . esc_attr($it['text']) . '" loading="lazy" decoding="async">';
            if ($it['text'] !== '') echo '<span class="jinyu-gallery-caption">' . esc_html($it['text']) . '</span>';
            echo $it['url'] !== '' ? '</a>' : '</span>';
        }
        echo '</div>';
        echo $args['after_widget'];
    }

    /** 手动模式：每行「图片地址|跳转链接|标题」，后两项可省 */
    private function items_from_manual($raw) {
        $items = [];
        foreach (jinyu_widget_lines($raw) as $row) {
            if (empty($row[0])) continue;
            $items[] = [
                'img'   => $row[0],
                'url'   => $row[1] ?? '',
                'text'  => $row[2] ?? '',
                'blank' => true,
            ];
        }
        return $items;
    }

    /** 自动模式：取分类下文章的封面（jinyu_get_post_cover 自带兜底图，不会破图） */
    private function items_from_posts($cat, $num) {
        $q = new WP_Query([
            'post_type'              => 'post',
            'posts_per_page'         => $num,
            'cat'                    => $cat,
            'ignore_sticky_posts'    => true,
            'no_found_rows'          => true,
            'update_post_term_cache' => false,
        ]);
        $items = [];
        foreach ($q->posts as $p) {
            $items[] = [
                'img'   => jinyu_get_post_cover($p->ID, 'medium'),
                'url'   => get_permalink($p),
                'text'  => get_the_title($p),
                'blank' => false,
            ];
        }
        return $items;
    }

    public function form($instance) {
        $title  = $instance['title'] ?? __('<i class="fa-regular fa-images"></i> 推荐', JINYU);
        $source = $instance['source'] ?? 'manual';
        $items  = $instance['items'] ?? '';
        $cat    = (int)($instance['cat'] ?? 0);
        $num    = $instance['num'] ?? 4;
        $cols   = $instance['cols'] ?? 1;
        echo "<p>" . esc_html__('标题', JINYU) . ": <input name='{$this->get_field_name('title')}' value='" . esc_attr($title) . "' class='widefat'></p>";
        echo "<p>" . esc_html__('图片来源', JINYU) . ":<br>";
        echo "<label><input type='radio' name='{$this->get_field_name('source')}' value='manual'" . checked($source, 'manual', false) . "> " . esc_html__('手动填写', JINYU) . "</label> ";
        echo "<label><input type='radio' name='{$this->get_field_name('source')}' value='cat'" . checked($source, 'cat', false) . "> " . esc_html__('自动取分类缩略图', JINYU) . "</label></p>";
        echo "<p>" . esc_html__('手动列表（每行：图片地址|跳转链接|标题）', JINYU) . ":<br>";
        echo "<textarea name='{$this->get_field_name('items')}' rows='5' class='widefat' placeholder='https://a.com/1.jpg|https://a.com|活动入口'>" . esc_textarea($items) . "</textarea></p>";
        echo "<p>" . esc_html__('自动模式：分类', JINYU) . ": <select name='{$this->get_field_name('cat')}' class='widefat'>";
        echo '<option value="0">' . esc_html__('— 全部分类（按最新）—', JINYU) . '</option>';
        foreach (get_categories(['hide_empty' => false]) as $c) {
            echo '<option value="' . (int) $c->term_id . '"' . selected($cat, $c->term_id, false) . '>' . esc_html($c->name) . '</option>';
        }
        echo "</select></p>";
        echo "<p>" . esc_html__('自动模式：数量', JINYU) . ": <input type='number' name='{$this->get_field_name('num')}' value='" . esc_attr($num) . "' class='widefat' min='1' max='8'></p>";
        echo "<p>" . esc_html__('每行几张', JINYU) . ": <input type='number' name='{$this->get_field_name('cols')}' value='" . esc_attr($cols) . "' class='widefat' min='1' max='2'></p>";
    }

    public function update($new, $old) {
        return [
            // 复用同一份白名单：标题允许 <i> 图标标签（与前台 jinyu_widget_title 一致）
            'title'  => jinyu_widget_title($new['title'] ?? ''),
            'source' => ($new['source'] ?? 'manual') === 'cat' ? 'cat' : 'manual',
            'items'  => sanitize_textarea_field($new['items'] ?? ''),
            'cat'    => (int)($new['cat'] ?? 0),
            'num'    => max(1, min(8, (int)($new['num'] ?? 4))),
            'cols'   => max(1, min(2, (int)($new['cols'] ?? 1))),
        ];
    }
}

class Jinyu_Hitokoto_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct('jinyu_hitokoto', __('金玉·每日一句', JINYU), ['classname'=>'widget_jinyu_hitokoto','description'=>__('随机显示一条语录；不依赖外部 API', JINYU)]);
    }

    /** 内置语录库（均为公有领域古诗文），后台留空时使用 */
    public static function default_quotes() {
        return implode("\n", [
            '纸上得来终觉浅，绝知此事要躬行。|陆游',
            '不积跬步，无以至千里。|荀子',
            '千里之行，始于足下。|老子',
            '合抱之木，生于毫末。|老子',
            '锲而不舍，金石可镂。|荀子',
            '博观而约取，厚积而薄发。|苏轼',
            '路漫漫其修远兮，吾将上下而求索。|屈原',
            '学而不思则罔，思而不学则殆。|论语',
            '临渊羡鱼，不如退而结网。|汉书',
            '山不厌高，海不厌深。|曹操',
        ]);
    }

    public function widget($args, $instance) {
        $title = $instance['title'] ?? __('<i class="fa-solid fa-quote-left"></i> 每日一句', JINYU);
        $raw   = trim((string)($instance['quotes'] ?? ''));
        $rows  = jinyu_widget_lines($raw !== '' ? $raw : self::default_quotes());
        if (!$rows) return;

        $n = count($rows);
        // daily：按站点本地日期取模，全天固定同一条；random：每次请求随机。
        // 用取模而不是 mt_srand()，避免污染同请求内其它代码的随机序列。
        $idx = ($instance['mode'] ?? 'daily') === 'random'
            ? wp_rand(0, $n - 1)
            : abs(crc32(current_time('Ymd'))) % $n;

        $text = $rows[$idx][0];
        $from = $rows[$idx][1] ?? '';

        echo $args['before_widget'];
        echo $args['before_title'] . jinyu_widget_title($title) . $args['after_title'];
        echo '<blockquote class="jinyu-hitokoto">';
        echo '<p class="jinyu-hitokoto-text">' . esc_html($text) . '</p>';
        if ($from !== '') echo '<cite class="jinyu-hitokoto-from">' . esc_html($from) . '</cite>';
        echo '</blockquote>';
        echo $args['after_widget'];
    }

    public function form($instance) {
        $title  = $instance['title'] ?? __('<i class="fa-solid fa-quote-left"></i> 每日一句', JINYU);
        $quotes = $instance['quotes'] ?? '';
        $mode   = $instance['mode'] ?? 'daily';
        echo "<p>" . esc_html__('标题', JINYU) . ": <input name='{$this->get_field_name('title')}' value='" . esc_attr($title) . "' class='widefat'></p>";
        echo "<p>" . esc_html__('切换方式', JINYU) . ":<br>";
        echo "<label><input type='radio' name='{$this->get_field_name('mode')}' value='daily'" . checked($mode, 'daily', false) . "> " . esc_html__('每天固定一条', JINYU) . "</label> ";
        echo "<label><input type='radio' name='{$this->get_field_name('mode')}' value='random'" . checked($mode, 'random', false) . "> " . esc_html__('每次刷新随机', JINYU) . "</label></p>";
        echo "<p>" . esc_html__('语录（每行：内容|出处；出处可省。留空用内置语录）', JINYU) . ":<br>";
        echo "<textarea name='{$this->get_field_name('quotes')}' rows='6' class='widefat' placeholder='静水流深。|佚名'>" . esc_textarea($quotes) . "</textarea></p>";
    }

    public function update($new, $old) {
        return [
            'title'  => jinyu_widget_title($new['title'] ?? ''),
            'mode'   => ($new['mode'] ?? 'daily') === 'random' ? 'random' : 'daily',
            'quotes' => sanitize_textarea_field($new['quotes'] ?? ''),
        ];
    }
}

/* ==================== 金玉·访客信息卡（实时，经 Ajax 取回访客自身数据） ==================== */
if (!class_exists('Jinyu_Visitor_Widget')) {
    class Jinyu_Visitor_Widget extends WP_Widget
    {
        public function __construct()
        {
            parent::__construct('jinyu_visitor', __('金玉·访客信息', JINYU), [
                'classname'   => 'widget_jinyu_visitor',
                'description' => __('实时展示当前访客的 IP / 系统 / 浏览器 / 归属地（经 Ajax 取回，不进缓存）', JINYU),
            ]);
        }

        public function widget($args, $instance)
        {
            $title = $instance['title'] ?? __('<i class="fa-solid fa-user-astronaut"></i> 访客信息', JINYU);
            echo $args['before_widget'];
            echo $args['before_title'] . jinyu_widget_title($title) . $args['after_title'];
            ?>
            <div class="jinyu-visitor" data-jinyu-live="visitor">
                <div class="jinyu-visitor-hero">
                    <span class="jinyu-visitor-avatar">
                        <svg class="jinyu-visitor-illus" viewBox="0 0 64 64" aria-hidden="true">
                            <circle cx="32" cy="22" r="11" />
                            <path d="M12 56c0-11 9-18 20-18s20 7 20 18" />
                        </svg>
                    </span>
                    <span class="jinyu-visitor-id">
                        <span class="jinyu-visitor-hello" data-vis="greet"><?php esc_html_e('你好，朋友', JINYU); ?></span>
                        <span class="jinyu-visitor-loc" data-vis="loc" hidden></span>
                    </span>
                </div>
                <dl class="jinyu-visitor-rows">
                    <div class="jinyu-visitor-row"><dt><i class="fa-solid fa-globe" aria-hidden="true"></i><?php esc_html_e('IP', JINYU); ?></dt><dd data-vis="ip">—</dd></div>
                    <div class="jinyu-visitor-row"><dt><i class="fa-solid fa-display" aria-hidden="true"></i><?php esc_html_e('系统', JINYU); ?></dt><dd data-vis="os">—</dd></div>
                    <div class="jinyu-visitor-row"><dt><i class="fa-solid fa-window-maximize" aria-hidden="true"></i><?php esc_html_e('浏览器', JINYU); ?></dt><dd data-vis="browser">—</dd></div>
                </dl>
                <p class="jinyu-visitor-tip"><?php esc_html_e('以上为本次访问的信息，仅你可见', JINYU); ?></p>
            </div>
            <?php
            echo $args['after_widget'];
        }

        public function form($instance)
        {
            $title = $instance['title'] ?? '';
            ?>
            <p>
                <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php esc_html_e('标题', JINYU); ?></label>
                <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" value="<?php echo esc_attr($title); ?>">
            </p>
            <?php
        }

        public function update($new, $old)
        {
            return ['title' => jinyu_widget_title($new['title'] ?? '')];
        }
    }
}

/* ==================== 金玉·最近加入 ==================== */
if (!class_exists('Jinyu_Newcomers_Widget')) {
    class Jinyu_Newcomers_Widget extends WP_Widget
    {
        public function __construct()
        {
            parent::__construct('jinyu_newcomers', __('金玉·最近加入', JINYU), [
                'classname'   => 'widget_jinyu_newcomers',
                'description' => __('展示最近注册会员或近期评论者', JINYU),
            ]);
        }

        public function widget($args, $instance)
        {
            $title  = $instance['title'] ?? __('<i class="fa-solid fa-user-plus"></i> 最近加入', JINYU);
            $source = $instance['source'] ?? 'commenters';
            $num    = max(1, min(20, (int) ($instance['num'] ?? 5)));
            $tpl    = $instance['tpl'] ?? '{name} 加入了网站';
            echo $args['before_widget'];
            echo $args['before_title'] . jinyu_widget_title($title) . $args['after_title'];

            $items = [];
            if ($source === 'users') {
                foreach (get_users(['orderby' => 'registered', 'order' => 'DESC', 'number' => $num]) as $u) {
                    $items[] = ['id' => $u->ID, 'name' => $u->display_name, 'ts' => (int) strtotime($u->user_registered)];
                }
            } else {
                $seen = [];
                foreach (get_comments(['status' => 'approve', 'type' => 'comment', 'number' => $num * 6, 'no_found_rows' => true]) as $c) {
                    $key = $c->comment_author_email ?: $c->comment_author;
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $items[] = ['id' => 0, 'name' => $c->comment_author, 'email' => $c->comment_author_email, 'ts' => (int) strtotime($c->comment_date)];
                    if (count($items) >= $num) {
                        break;
                    }
                }
            }

            if ($items) {
                echo '<ul class="jinyu-newcomers">';
                foreach ($items as $it) {
                    // 注册会员有 uid，走 Gravatar / 主题自定义头像；
                    // 评论者是匿名访客，头像服务不可靠（常见空灰图），统一用首字母占位：不外呼、永不破图、视觉统一。
                    if ($it['id']) {
                        $avatar = get_avatar($it['id'], 40, '', '', ['class' => 'jinyu-avatar-img']);
                    } else {
                        $avatar = '<img class="jinyu-avatar-img" src="' . esc_attr(jinyu_letter_avatar($it['name'], 40)) . '" width="40" height="40" alt="">';
                    }
                    $name = esc_html($it['name']);
                    $text = str_replace(['{name}', '{date}'], [$name, esc_html(wp_date('Y-m-d', $it['ts']))], esc_html($tpl));
                    echo '<li class="jinyu-newcomers-item">';
                    echo '<div class="jinyu-newcomers-ava">' . $avatar . '</div>';
                    echo '<div class="jinyu-newcomers-body">';
                    echo '<div class="jinyu-newcomers-name">' . $name . '</div>';
                    echo '<div class="jinyu-newcomers-text">' . $text . '</div>';
                    echo '</div></li>';
                }
                echo '</ul>';
            } else {
                echo '<p class="jinyu-widget-empty">' . esc_html__('暂无数据', JINYU) . '</p>';
            }
            echo $args['after_widget'];
        }

        public function form($instance)
        {
            $title  = $instance['title'] ?? '';
            $source = $instance['source'] ?? 'commenters';
            $num    = (int) ($instance['num'] ?? 5);
            $tpl    = $instance['tpl'] ?? '{name} 加入了网站';
            ?>
            <p>
                <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php esc_html_e('标题', JINYU); ?></label>
                <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" value="<?php echo esc_attr($title); ?>">
            </p>
            <p>
                <label for="<?php echo esc_attr($this->get_field_id('source')); ?>"><?php esc_html_e('数据来源', JINYU); ?></label>
                <select class="widefat" id="<?php echo esc_attr($this->get_field_id('source')); ?>" name="<?php echo esc_attr($this->get_field_name('source')); ?>">
                    <option value="commenters" <?php selected($source, 'commenters'); ?>><?php esc_html_e('近期评论者', JINYU); ?></option>
                    <option value="users" <?php selected($source, 'users'); ?>><?php esc_html_e('注册会员', JINYU); ?></option>
                </select>
            </p>
            <p>
                <label for="<?php echo esc_attr($this->get_field_id('num')); ?>"><?php esc_html_e('显示数量', JINYU); ?></label>
                <input class="widefat" id="<?php echo esc_attr($this->get_field_id('num')); ?>" name="<?php echo esc_attr($this->get_field_name('num')); ?>" type="number" min="1" max="20" value="<?php echo esc_attr($num); ?>">
            </p>
            <p>
                <label for="<?php echo esc_attr($this->get_field_id('tpl')); ?>"><?php esc_html_e('文案模板', JINYU); ?></label>
                <input class="widefat" id="<?php echo esc_attr($this->get_field_id('tpl')); ?>" name="<?php echo esc_attr($this->get_field_name('tpl')); ?>" type="text" value="<?php echo esc_attr($tpl); ?>">
                <span class="jinyu-widget-hint"><?php esc_html_e('可用占位符：{name} 名称、{date} 日期', JINYU); ?></span>
            </p>
            <?php
        }

        public function update($new, $old)
        {
            return [
                'title'  => jinyu_widget_title($new['title'] ?? ''),
                'source' => ($new['source'] ?? 'commenters') === 'users' ? 'users' : 'commenters',
                'num'    => max(1, min(20, (int) ($new['num'] ?? 5))),
                'tpl'    => sanitize_text_field($new['tpl'] ?? '{name} 加入了网站'),
            ];
        }
    }
}

/* ==================== 金玉·网站概况（运行时长） ==================== */
if (!class_exists('Jinyu_Uptime_Widget')) {
    class Jinyu_Uptime_Widget extends WP_Widget
    {
        public function __construct()
        {
            parent::__construct('jinyu_uptime', __('金玉·网站概况', JINYU), [
                'classname'   => 'widget_jinyu_uptime',
                'description' => __('展示站点已稳定运行的天 / 时 / 分 / 秒', JINYU),
            ]);
        }

        public function widget($args, $instance)
        {
            $title = $instance['title'] ?? __('<i class="fa-solid fa-server"></i> 网站概况', JINYU);
            $since = (int) ($instance['since'] ?? 0);
            if ($since <= 0) {
                $since = jinyu_site_since();
            }
            echo $args['before_widget'];
            echo $args['before_title'] . jinyu_widget_title($title) . $args['after_title'];
            if ($since) {
                ?>
                <div class="jinyu-uptime" data-since="<?php echo esc_attr($since); ?>">
                    <div class="jinyu-uptime-grid">
                        <div class="jinyu-uptime-cell"><b class="jinyu-uptime-num" data-up="d">0</b><span class="jinyu-uptime-label"><?php esc_html_e('天', JINYU); ?></span></div>
                        <div class="jinyu-uptime-cell"><b class="jinyu-uptime-num" data-up="h">0</b><span class="jinyu-uptime-label"><?php esc_html_e('时', JINYU); ?></span></div>
                        <div class="jinyu-uptime-cell"><b class="jinyu-uptime-num" data-up="m">0</b><span class="jinyu-uptime-label"><?php esc_html_e('分', JINYU); ?></span></div>
                        <div class="jinyu-uptime-cell"><b class="jinyu-uptime-num" data-up="s">0</b><span class="jinyu-uptime-label"><?php esc_html_e('秒', JINYU); ?></span></div>
                    </div>
                    <p class="jinyu-uptime-foot"><?php echo esc_html(sprintf(__('自 %s 已稳定运行', JINYU), wp_date('Y-m-d', $since))); ?></p>
                </div>
                <?php
            } else {
                echo '<p class="jinyu-widget-empty">' . esc_html__('暂无建站时间数据', JINYU) . '</p>';
            }
            echo $args['after_widget'];
        }

        public function form($instance)
        {
            $title = $instance['title'] ?? '';
            $since = $instance['since'] ?? '';
            ?>
            <p>
                <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php esc_html_e('标题', JINYU); ?></label>
                <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" value="<?php echo esc_attr($title); ?>">
            </p>
            <p>
                <label for="<?php echo esc_attr($this->get_field_id('since')); ?>"><?php esc_html_e('建站日期（留空自动取最早文章）', JINYU); ?></label>
                <input class="widefat" id="<?php echo esc_attr($this->get_field_id('since')); ?>" name="<?php echo esc_attr($this->get_field_name('since')); ?>" type="date" value="<?php echo esc_attr($since); ?>">
            </p>
            <?php
        }

        public function update($new, $old)
        {
            $since = trim($new['since'] ?? '');
            return [
                'title' => jinyu_widget_title($new['title'] ?? ''),
                'since' => $since ? jinyu_parse_date_to_ts($since) : 0,
            ];
        }
    }
}

/* ==================== 金玉·站点性能（实时心跳） ==================== */
if (!class_exists('Jinyu_Perf_Widget')) {
    class Jinyu_Perf_Widget extends WP_Widget
    {
        public function __construct()
        {
            parent::__construct('jinyu_perf', __('金玉·站点性能', JINYU), [
                'classname'   => 'widget_jinyu_perf',
                'description' => __('实时展示站点响应耗时 / 数据库查询 / 内存占用心跳', JINYU),
            ]);
        }

        public function widget($args, $instance)
        {
            $title = $instance['title'] ?? __('<i class="fa-solid fa-gauge-high"></i> 站点性能', JINYU);
            echo $args['before_widget'];
            echo $args['before_title'] . jinyu_widget_title($title) . $args['after_title'];
            ?>
            <div class="jinyu-perf" data-jinyu-live="perf">
                <div class="jinyu-perf-head">
                    <span class="jinyu-perf-dot" data-perf="dot"></span>
                    <span class="jinyu-perf-status" data-perf="status"><?php esc_html_e('采集中', JINYU); ?></span>
                </div>
                <svg class="jinyu-perf-spark" viewBox="0 0 100 32" preserveAspectRatio="none" aria-hidden="true">
                    <polygon class="jinyu-perf-area" data-perf="area" points="" />
                    <polyline class="jinyu-perf-line" data-perf="spark" fill="none" points="" />
                </svg>
                <div class="jinyu-perf-grid">
                    <div class="jinyu-perf-cell"><b data-perf="ms">—</b><span><?php esc_html_e('响应耗时', JINYU); ?></span></div>
                    <div class="jinyu-perf-cell"><b data-perf="q">—</b><span><?php esc_html_e('查询', JINYU); ?></span></div>
                    <div class="jinyu-perf-cell"><b data-perf="mem">—</b><span><?php esc_html_e('内存', JINYU); ?></span></div>
                </div>
            </div>
            <?php
            echo $args['after_widget'];
        }

        public function form($instance)
        {
            $title = $instance['title'] ?? '';
            ?>
            <p>
                <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php esc_html_e('标题', JINYU); ?></label>
                <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" value="<?php echo esc_attr($title); ?>">
            </p>
            <?php
        }

        public function update($new, $old)
        {
            return ['title' => jinyu_widget_title($new['title'] ?? '')];
        }
    }
}

/* ==================== 金玉·时钟 ==================== */
if (!class_exists('Jinyu_Clock_Widget')) {
    class Jinyu_Clock_Widget extends WP_Widget
    {
        public function __construct()
        {
            parent::__construct('jinyu_clock', __('金玉·时钟', JINYU), [
                'classname'   => 'widget_jinyu_clock',
                'description' => __('显示站点本地时间的可爱时钟', JINYU),
            ]);
        }

        public function widget($args, $instance)
        {
            $title = $instance['title'] ?? __('<i class="fa-regular fa-clock"></i> 本站时间', JINYU);
            echo $args['before_widget'];
            echo $args['before_title'] . jinyu_widget_title($title) . $args['after_title'];
            ?>
            <div class="jinyu-clock" data-jinyu-live="clock">
                <span class="jinyu-clock-glow" aria-hidden="true"></span>
                <div class="jinyu-clock-face" aria-hidden="true">
                    <span class="jinyu-clock-eye jinyu-clock-eye-l"><i class="jinyu-clock-pupil"></i></span>
                    <span class="jinyu-clock-eye jinyu-clock-eye-r"><i class="jinyu-clock-pupil"></i></span>
                    <span class="jinyu-clock-mouth"></span>
                </div>
                <div class="jinyu-clock-time">
                    <span class="jinyu-clock-hm" data-clock="hm">--:--</span>
                    <span class="jinyu-clock-sec" data-clock="sec" aria-hidden="true">--</span>
                </div>
                <div class="jinyu-clock-meta">
                    <span data-clock="date">----</span>
                    <i class="jinyu-clock-sep" aria-hidden="true"></i>
                    <span data-clock="week"></span>
                </div>
                <span class="jinyu-clock-sweep" aria-hidden="true"><i></i></span>
            </div>
            <?php
            echo $args['after_widget'];
        }

        public function form($instance)
        {
            $title = $instance['title'] ?? '';
            ?>
            <p>
                <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php esc_html_e('标题', JINYU); ?></label>
                <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" value="<?php echo esc_attr($title); ?>">
            </p>
            <?php
        }

        public function update($new, $old)
        {
            return ['title' => jinyu_widget_title($new['title'] ?? '')];
        }
    }
}

/* ───────────────────── 邮件订阅小工具 ───────────────────── */
if (!class_exists('Jinyu_Subscribe_Widget')) {
    class Jinyu_Subscribe_Widget extends WP_Widget
    {
        public function __construct()
        {
            parent::__construct(
                'jinyu_subscribe_widget',
                '金玉 · 邮件订阅',
                ['description' => __('渲染邮件订阅表单（依赖「销售与变现 › 邮件订阅」开关）', JINYU)]
            );
        }

        public function widget($args, $instance)
        {
            $title = $instance['title'] ?? '';
            $html = jinyu_subscribe_form_html('widget');
            if (!$html) {
                return;
            }
            echo $args['before_widget'];
            if ($title) {
                echo $args['before_title'] . esc_html($title) . $args['after_title'];
            }
            echo $html;
            echo $args['after_widget'];
        }

        public function form($instance)
        {
            $title = $instance['title'] ?? '';
            ?>
            <p>
                <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php esc_html_e('标题（留空用后台设置）', JINYU); ?></label>
                <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" value="<?php echo esc_attr($title); ?>">
            </p>
            <?php
        }

        public function update($new, $old)
        {
            return ['title' => jinyu_widget_title($new['title'] ?? '')];
        }
    }
}

add_action('widgets_init', function(){
    register_widget('Jinyu_Recent_Viewed_Widget');
    register_widget('Jinyu_Author_Widget');
    register_widget('Jinyu_Notice_Widget');
    register_widget('Jinyu_Posts_Hot_Widget');
    register_widget('Jinyu_Tag_Cloud_Widget');
    register_widget('Jinyu_Archive_Widget');
    register_widget('Jinyu_Recent_Comments_Widget');
    register_widget('Jinyu_Random_Posts_Widget');
    register_widget('Jinyu_Search_Widget');
    register_widget('Jinyu_Categories_Widget');
    register_widget('Jinyu_Links_Widget');
    register_widget('Jinyu_Stats_Widget');
    register_widget('Jinyu_Related_Widget');
    register_widget('Jinyu_Menu_Widget');
    register_widget('Jinyu_Hot_Comment_Widget');
    register_widget('Jinyu_Reader_Wall_Widget');
    register_widget('Jinyu_Gallery_Widget');
    register_widget('Jinyu_Hitokoto_Widget');
    register_widget('Jinyu_Visitor_Widget');
    register_widget('Jinyu_Newcomers_Widget');
    register_widget('Jinyu_Uptime_Widget');
    register_widget('Jinyu_Perf_Widget');
    register_widget('Jinyu_Clock_Widget');
    register_widget('Jinyu_Subscribe_Widget');
});
