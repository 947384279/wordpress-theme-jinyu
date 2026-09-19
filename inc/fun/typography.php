<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 中文自动排版（PHP 级）
 * 当开启「中英文自动加空格」时，在中文(汉字)与拉丁字母/数字边界插入空格，
 * 提升中英文混排可读性。保护 HTML 标签与 URL，避免破坏链接/属性。
 */

if (!function_exists('jinyu_cn_autospace')) {
    function jinyu_cn_autospace(string $content): string
    {
        if (!jinyu_is_checked('cn_autospace') || !is_string($content)) {
            return $content;
        }

        // 抽走受保护块：HTML 标签 + URL，避免破坏属性与链接
        $store = [];
        $content = preg_replace_callback(
            '#(<[^>]+>)|(https?://[^\s<]+)#i',
            function ($m) use (&$store) {
                $store[] = $m[0];
                return "\x00JINYU_PROTECT_" . (count($store) - 1) . "\x00";
            },
            $content
        );

        // 中文(汉字) 与 拉丁字母/数字 之间加空格
        $content = preg_replace('#(?<=\p{Han})(?=[A-Za-z0-9])#u', ' ', $content);
        $content = preg_replace('#(?<=[A-Za-z0-9])(?=\p{Han})#u', ' ', $content);

        // 还原受保护块
        $content = preg_replace_callback(
            '#\x00JINYU_PROTECT_(\d+)\x00#',
            function ($m) use ($store) {
                return $store[(int)$m[1]] ?? '';
            },
            $content
        );

        return $content;
    }
}

add_filter('the_content', 'jinyu_cn_autospace', 20);
add_filter('comment_text', 'jinyu_cn_autospace', 20);
