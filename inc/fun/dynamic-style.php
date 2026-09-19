<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 动态样式：把后台「风格外观 / 全局设置」里的配置编译成 CSS 自定义属性注入 <head>。
 * 组件样式只读变量，不再散落各处的覆写规则。
 */

if (!function_exists('jinyu_hex_to_rgb')) {
    /**
     * #abc / #aabbcc → [r, g, b]
     */
    function jinyu_hex_to_rgb($hex)
    {
        $hex = ltrim((string)$hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (!preg_match('/^[0-9a-f]{6}$/i', $hex)) return null;

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }
}

if (!function_exists('jinyu_color_shade')) {
    /**
     * 按比例调整明度：amount > 0 变亮，< 0 变暗
     */
    function jinyu_color_shade($hex, $amount)
    {
        $rgb = jinyu_hex_to_rgb($hex);
        if (!$rgb) return $hex;

        $mix = function ($c) use ($amount) {
            $target = $amount > 0 ? 255 : 0;
            return (int)round($c + ($target - $c) * abs($amount));
        };

        return sprintf('#%02x%02x%02x', $mix($rgb[0]), $mix($rgb[1]), $mix($rgb[2]));
    }
}

if (!function_exists('jinyu_build_dynamic_vars')) {
    /**
     * 构建 CSS 变量数组（:root 作用域）
     */
    function jinyu_build_dynamic_vars()
    {
        $vars = [];

        // 主题主色
        $primary = jinyu_get_option('style_color_primary', '#FF6B35');
        if ($primary && jinyu_hex_to_rgb($primary)) {
            $rgb  = jinyu_hex_to_rgb($primary);
            $vars['--jinyu-c-primary']       = strtolower($primary);
            $vars['--jinyu-c-primary-dark']  = jinyu_color_shade($primary, -0.14);
            $vars['--jinyu-c-primary-light'] = 'rgba(' . implode(',', $rgb) . ',.1)';
            $vars['--jinyu-c-primary-rgb']   = implode(',', $rgb);
        }

        // 圆角
        $radius = (int)jinyu_get_option('style_radius', 6);
        $radius = max(0, min(16, $radius));
        $vars['--jinyu-radius-sm'] = round($radius * 0.5, 1) . 'px';
        $vars['--jinyu-radius-md'] = $radius . 'px';
        $vars['--jinyu-radius-lg'] = ($radius + 4) . 'px';

        // CMS 卡片列数
        $cols = (int)jinyu_get_option('post_card_cols', 2);
        if ($cols >= 2) {
            $vars['--jinyu-post-cols'] = min(4, $cols);
        }

        return $vars;
    }
}

if (!function_exists('jinyu_dark_palette_css')) {
    /**
     * 暗色配色方案的额外覆写（纯黑方案）
     */
    function jinyu_dark_palette_css()
    {
        if (jinyu_get_option('dark_palette', 'default') !== 'pureblack') return '';

        $override = [
            '--jinyu-c-bg'            => '#000',
            '--jinyu-c-bg-card'       => '#0a0a0a',
            '--jinyu-c-bg-subtle'     => '#171717',
            '--jinyu-c-bg-input'      => '#000',
            '--jinyu-c-bg-code'       => '#050505',
            '--jinyu-c-bg-code-bar'   => '#121212',
            '--jinyu-c-bg-header'     => 'rgba(0,0,0,.72)',
            '--jinyu-c-bg-footer'     => '#000',
            '--jinyu-c-border'        => 'rgba(255,255,255,.1)',
            '--jinyu-c-border-strong' => 'rgba(255,255,255,.18)',
        ];

        $body = '';
        foreach ($override as $k => $v) {
            $body .= $k . ':' . $v . ';';
        }

        return 'html[data-theme=\'dark\']{' . $body . '}'
            . '@media (prefers-color-scheme:dark){html:not([data-theme=\'light\']){' . $body . '}}';
    }
}

/**
 * 输出到 <head>，优先级高于主题样式表之后加载的内联样式
 */
add_action('wp_head', 'jinyu_dynamic_style', 8);
function jinyu_dynamic_style()
{
    $vars   = jinyu_build_dynamic_vars();
    $root   = '';
    foreach ($vars as $k => $v) {
        $root .= $k . ':' . $v . ';';
    }

    $css = ':root{' . $root . '}' . jinyu_dark_palette_css();

    echo '<style id="jinyu-dynamic-style">' . wp_strip_all_tags($css) . '</style>';
}

/**
 * 屏蔽前台 Admin Bar
 */
add_filter('show_admin_bar', function ($show) {
    if (jinyu_is_checked('hide_admin_bar') && !is_admin()) return false;
    return $show;
});
