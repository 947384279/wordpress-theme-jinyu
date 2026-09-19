<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 中文友好的描述截断：按字符数（CJK 记 1），超出加省略号。
if ( ! function_exists( 'jinyu_truncate_desc' ) ) {
	function jinyu_truncate_desc( $text, $len = 150 ) {
		$text = wp_strip_all_tags( $text );
		$text = trim( preg_replace( '/\s+/', ' ', $text ) );
		if ( function_exists( 'mb_strimwidth' ) ) {
			return mb_strimwidth( $text, 0, $len, '…', 'UTF-8' );
		}
		$cut = mb_substr( $text, 0, $len, 'UTF-8' );
		return $cut . ( mb_strlen( $text, 'UTF-8' ) > $len ? '…' : '' );
	}
}

// 从正文提取首段纯文本，作为 meta description 的兜底来源（防止回退到全站统一描述）。
if ( ! function_exists( 'jinyu_first_para_text' ) ) {
	function jinyu_first_para_text( $content ) {
		$content = preg_replace( '/\[[^\]]+\]/', '', (string) $content );
		if ( preg_match( '/<p[^>]*>(.*?)<\/p>/is', $content, $m ) ) {
			$text = $m[1];
		} else {
			$parts = preg_split( '/\n\s*\n/', strip_tags( $content ), 2 );
			$text  = $parts[0] ?? '';
		}
		return trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $text ) ) );
	}
}

if (!jinyu_is_checked('seo_open')) return;

// 站点已装主流 SEO 插件时让位，避免与插件重复输出 description / og / twitter 标签
if (
    defined('WPSEO_VERSION')        // Yoast SEO
    || defined('RANK_MATH_VERSION') // Rank Math
    || defined('AIOSEO_VERSION')    // All in One SEO
    || defined('SEOPRESS_VERSION')  // SEOPress
    || class_exists('The_SEO_Framework\\Load') // TSF
) {
    return;
}

// 主题 SEO 接管 canonical：移除 WP 核心的 rel_canonical，避免同一页输出两个 canonical
remove_action('wp_head', 'rel_canonical');

add_action('wp_head', 'jinyu_seo_meta', 1);
function jinyu_seo_meta()
{
    // 规范链接（canonical）：消除分页归档 / 搜索 ?s= / 追踪参数等造成的重复内容，
    // 避免权重被稀释。仅在主题内置 SEO 启用且未装主流 SEO 插件时输出（与下方 meta 同一让位逻辑）。
    if (!is_404()) {
        $canonical = '';
        if (is_singular()) {
            $canonical = get_permalink();
        } elseif (is_front_page() || is_home()) {
            $canonical = home_url('/');
        } elseif (is_category() || is_tag() || is_tax()) {
            $canonical = get_term_link(get_queried_object());
        } elseif (is_author()) {
            $canonical = get_author_posts_url(get_queried_object_id());
        } elseif (is_post_type_archive()) {
            $canonical = get_post_type_archive_link(get_post_type());
        } elseif (is_search()) {
            $canonical = home_url('/?s=' . rawurlencode(get_search_query()));
        }
        if ($canonical && !is_wp_error($canonical)) {
            echo '<link rel="canonical" href="' . esc_url($canonical) . '">' . PHP_EOL;
        }
    }

    $desc = jinyu_get_option('seo_desc', '');
    $keys = jinyu_get_option('seo_keywords', '');

    if (is_singular()) {
        global $post;
        $custom_desc = get_post_meta(get_the_ID(), 'jinyu_seo_desc', true);
        if ($custom_desc) {
            $desc = $custom_desc;
        } elseif (!empty($post->post_excerpt)) {
            $desc = wp_strip_all_tags($post->post_excerpt);
        } else {
            // 兜底：自动取正文首段，避免回退到全站统一描述导致所有文章摘要雷同
            $desc = jinyu_first_para_text($post->post_content);
        }
        $desc = jinyu_truncate_desc($desc);

        $tags = wp_get_post_tags(get_the_ID(), ['fields' => 'names']);
        if (!empty($tags)) $keys = implode(',', $tags);
        $custom_keys = get_post_meta(get_the_ID(), 'jinyu_seo_keys', true);
        if ($custom_keys) $keys = $custom_keys;
    } elseif (is_category()) {
        $cat_id     = get_queried_object_id();
        $cat_keys   = get_term_meta($cat_id, 'jinyu_seo_cat_keywords', true);
        $cat_desc   = get_term_meta($cat_id, 'jinyu_seo_cat_desc', true);
        if ($cat_keys) {
            $keys = $cat_keys;
        }
        if ($cat_desc) {
            $desc = $cat_desc;
        } elseif (category_description($cat_id)) {
            $desc = category_description($cat_id);
        }
    } elseif (is_tag()) {
        $desc = tag_description() ?: $desc;
    } elseif (is_author()) {
        $author = get_queried_object();
        if ($author && !empty($author->description)) $desc = $author->description;
    }

    if ($desc) echo '<meta name="description" content="' . esc_attr($desc) . '">' . PHP_EOL;
    if ($keys) echo '<meta name="keywords" content="' . esc_attr($keys) . '">' . PHP_EOL;

    if (is_singular()) {
        $title = get_the_title();
        $cover = jinyu_get_post_cover(get_the_ID(), 'medium', false);
        $site_name = jinyu_get_option('og_site_name', '');
        if (!$site_name) $site_name = get_bloginfo('name');
        echo '<meta property="og:title" content="' . esc_attr($title) . '">' . PHP_EOL;
        echo '<meta property="og:type" content="article">' . PHP_EOL;
        echo '<meta property="og:url" content="' . esc_url(get_permalink()) . '">' . PHP_EOL;
        echo '<meta property="og:site_name" content="' . esc_attr($site_name) . '">' . PHP_EOL;
        echo '<meta property="og:locale" content="zh_CN">' . PHP_EOL;
        if ($desc) echo '<meta property="og:description" content="' . esc_attr($desc) . '">' . PHP_EOL;
        if ($cover) echo '<meta property="og:image" content="' . esc_url($cover) . '">' . PHP_EOL;
        if (jinyu_is_checked('twitter_card_enable')) {
            echo '<meta name="twitter:card" content="summary_large_image">' . PHP_EOL;
            echo '<meta name="twitter:title" content="' . esc_attr($title) . '">' . PHP_EOL;
            if ($desc) echo '<meta name="twitter:description" content="' . esc_attr($desc) . '">' . PHP_EOL;
            if ($cover) echo '<meta name="twitter:image" content="' . esc_url($cover) . '">' . PHP_EOL;
        }
    }

}

// GEO 可发现性：在 <head> 主动提示 /llms.txt 位置（新近约定，部分 AI 工具会读取）。
// 独立于 SEO 插件让位逻辑——即便装了 Yoast/RankMath，GEO 提示仍应保留。
add_action('wp_head', 'jinyu_llms_head_link', 2);
function jinyu_llms_head_link()
{
    if (!jinyu_is_checked('llms_enable')) {
        return;
    }
    echo '<link rel="llms.txt" href="' . esc_url(home_url('/llms.txt')) . '">' . PHP_EOL;
}

// GEO 兜底：显式允许主流 AI 爬虫的规则已写入站点根静态 robots.txt（由 Web 服务器直出），
// 故此处不再挂载 robots_txt 过滤器——虚拟 robots 已被静态文件架空、永不执行，挂载只会误
// 导维护者以为 WP 控制 robots.txt。如需调整 AI 放行清单，直接改根目录 robots.txt。

// 归档页（分类/标签/作者/日期/自定义文章类型）自定义标题，利于 SEO 与可读性。
// 仅在主题内置 SEO 启用时生效（已安装 Yoast/RankMath 等插件时由插件接管标题）。
add_filter('document_title_parts', 'jinyu_archive_title_parts');
function jinyu_archive_title_parts($parts)
{
    if (is_admin()) return $parts;

    if (is_category() || is_tag() || is_tax() || is_author() || is_date() || is_post_type_archive()) {
        if (is_category()) {
            $parts['title'] = sprintf(__('%s 分类', JINYU), single_term_title('', false));
        } elseif (is_tag()) {
            $parts['title'] = sprintf(__('%s 标签', JINYU), single_term_title('', false));
        } elseif (is_tax()) {
            $parts['title'] = single_term_title('', false);
        } elseif (is_author()) {
            $parts['title'] = sprintf(__('%s 的全部文章', JINYU), get_the_author_meta('display_name'));
        } elseif (is_date()) {
            $parts['title'] = get_the_archive_title('', false);
        } elseif (is_post_type_archive()) {
            $parts['title'] = post_type_archive_title('', false);
        }
        // 分页（第 N 页）由 WordPress 自动追加到标题，无需处理
    }
    return $parts;
}