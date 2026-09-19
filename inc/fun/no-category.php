<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 去除分类链接中的 /category/ 前缀。
 * 仅在后台「SEO → 去除分类前缀」开启时，本文件才会被 core.php 引入，
 * 从而改写分类重写规则；关闭后 WP 默认规则自动恢复，互不污染。
 *
 * WP 7.x 兼容：去掉旧版 version_compare('3.4') 分支（早已无意义），
 * 直接写入 extra_permastructs['category']['struct']。
 */

add_action('load-themes.php', 'jinyu_no_category_base_flush');
add_action('created_category', 'jinyu_no_category_base_flush');
add_action('edited_category', 'jinyu_no_category_base_flush');
add_action('delete_category', 'jinyu_no_category_base_flush');
add_action('admin_init', 'jinyu_no_category_base_maybe_flush');
add_action('init', 'jinyu_no_category_base_permastruct');
add_filter('category_rewrite_rules', 'jinyu_no_category_base_rewrite_rules');
add_filter('query_vars', 'jinyu_no_category_base_query_vars');
add_filter('request', 'jinyu_no_category_base_request');
add_filter('category_link', 'jinyu_no_category_base_link', 10, 2);

function jinyu_no_category_base_flush(): void
{
    flush_rewrite_rules();
}

// 开启后首次进入后台自动刷新一次重写规则（避免手动去固定链接页保存）
function jinyu_no_category_base_maybe_flush(): void
{
    if (false === get_transient('jinyu_no_cat_rules_ok')) {
        flush_rewrite_rules();
        set_transient('jinyu_no_cat_rules_ok', 1, HOUR_IN_SECONDS);
    }
}

function jinyu_no_category_base_permastruct(): void
{
    global $wp_rewrite;
    if (empty($wp_rewrite->extra_permastructs['category'])) {
        return;
    }
    $wp_rewrite->extra_permastructs['category']['struct'] = '%category%';
}

function jinyu_no_category_base_rewrite_rules(array $category_rewrite): array
{
    $category_rewrite = [];
    $categories = get_categories(['hide_empty' => false]);
    foreach ($categories as $category) {
        $nicename = $category->slug;
        if ($category->parent != 0) {
            $nicename = get_category_parents($category->parent, false, '/', true) . $nicename;
        }
        $category_rewrite['(' . $nicename . ')/(?:feed/)?(feed|rdf|rss|rss2|atom)/?$'] = 'index.php?category_name=$matches[1]&feed=$matches[2]';
        $category_rewrite['(' . $nicename . ')/page/?([0-9]{1,})/?$'] = 'index.php?category_name=$matches[1]&paged=$matches[2]';
        $category_rewrite['(' . $nicename . ')/?$'] = 'index.php?category_name=$matches[1]';
    }
    $base = trim(get_option('category_base') ?: 'category', '/');
    if ($base) {
        $category_rewrite[$base . '/(.*)$'] = 'index.php?category_redirect=$matches[1]';
    }
    return $category_rewrite;
}

function jinyu_no_category_base_query_vars(array $vars): array
{
    $vars[] = 'category_redirect';
    return $vars;
}

function jinyu_no_category_base_request(array $vars)
{
    if (isset($vars['category_redirect'])) {
        $link = trailingslashit(home_url()) . user_trailingslashit($vars['category_redirect'], 'category');
        status_header(301);
        header('Location: ' . $link);
        exit;
    }
    return $vars;
}

function jinyu_no_category_base_link(string $link, int $term_id): string
{
    $base = trim(get_option('category_base') ?: 'category', '/');
    $search = $base ? '/' . $base . '/' : '/category/';
    return str_replace($search, '/', $link);
}
