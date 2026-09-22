<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 广告位渲染挂载
 * 后台「广告位」分组设置的代码，通过钩子/过滤器真正输出到前台。
 * ad/global-top、ad/global-bottom、ad/comment-top 由对应模板 get_template_part 渲染；
 * ad_page_inner（文章中部广告）通过 the_content 过滤器注入到正文首段之后。
 */

if (!function_exists('jinyu_ad_in_content')) {
    /**
     * 将「文章中部广告」插入到单篇文章正文第一段之后。
     */
    function jinyu_ad_in_content($content)
    {
        if (!is_singular('post') || !in_the_loop() || is_admin()) {
            return $content;
        }
        $ad = jinyu_get_option('ad_page_inner', '');
        if (!$ad) {
            return $content;
        }
        if (function_exists('jinyu_ad_badge_wrap')) {
            $ad = jinyu_ad_badge_wrap($ad, 'page_inner');
        }
        $pos = strpos($content, '</p>');
        if ($pos !== false) {
            $content = substr($content, 0, $pos + 4) . "\n" . $ad . "\n" . substr($content, $pos + 4);
        } else {
            $content .= "\n" . $ad;
        }
        return $content;
    }
    add_filter('the_content', 'jinyu_ad_in_content');
}
