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

if (!function_exists('jinyu_relative_luminance')) {
    /**
     * WCAG 相对亮度（0~1）：判断某颜色在暗底上是否够亮、可读。
     */
    function jinyu_relative_luminance($hex)
    {
        $rgb = jinyu_hex_to_rgb($hex);
        if (!$rgb) return 0;

        $linear = function ($c) {
            $s = $c / 255;
            return $s <= 0.03928 ? $s / 12.92 : pow(($s + 0.055) / 1.055, 2.4);
        };

        return 0.2126 * $linear($rgb[0]) + 0.7152 * $linear($rgb[1]) + 0.0722 * $linear($rgb[2]);
    }
}

if (!function_exists('jinyu_dark_variant')) {
    /**
     * 派生「暗色模式主色」：先按 20% 提亮（与主题内置暗色品牌的提亮幅度一致），
     * 若仍达不到可读亮度（例如主色被设成纯黑）则逐档继续提亮，避免主色在暗底上糊掉。
     */
    function jinyu_dark_variant($hex)
    {
        $out = jinyu_color_shade($hex, 0.2);
        for ($amount = 0.3; $amount <= 0.8 && jinyu_relative_luminance($out) < 0.35; $amount += 0.1) {
            $out = jinyu_color_shade($hex, $amount);
        }
        return $out;
    }
}

if (!function_exists('jinyu_dark_override_css')) {
    /**
     * 暗色模式覆写块。
     *
     * 必须存在的原因：tokens.less 的暗色令牌把 --jinyu-c-primary 写死成品牌橙，
     * 而 html[data-theme='dark'] 的选择器权重大于 :root，会用写死的橙色吃掉后台
     * 「主题主色」（蓝站点在暗色下变成橙色）。这里按配置主色重新派生暗色变体，
     * 选择器与 tokens.less 保持一致 —— 本块在主样式表之后输出，靠文档顺序取胜。
     *
     * 「纯黑」配色方案在此之上再叠一层背景/边框覆写。
     */
    function jinyu_dark_override_css()
    {
        $override = [];

        $primary = (string) jinyu_get_option('style_color_primary', '#FF6B35');
        $base_rgb = jinyu_hex_to_rgb($primary);
        if ($base_rgb) {
            $dark     = jinyu_dark_variant($primary);
            $dark_rgb = (array) jinyu_hex_to_rgb($dark);

            $override['--jinyu-c-primary']       = $dark;
            $override['--jinyu-c-primary-dark']  = strtolower($primary);
            $override['--jinyu-c-primary-light'] = 'rgba(' . implode(',', $base_rgb) . ',.14)';
            $override['--jinyu-c-primary-rgb']   = implode(',', $dark_rgb);
        }

        if (jinyu_get_option('dark_palette', 'default') === 'pureblack') {
            $override = array_merge($override, [
                '--jinyu-c-bg'            => '#000',
                '--jinyu-c-bg-card'       => '#0a0a0a',
                '--jinyu-c-bg-subtle'     => '#171717',
                '--jinyu-c-bg-input'      => '#000',
                '--jinyu-c-bg-code'       => '#050505',
                '--jinyu-c-bg-code-bar'   => '#121212',
                '--jinyu-c-bg-header'     => 'rgba(0,0,0,.72)',
                '--jinyu-c-bg-footer'     => '#000',
                '--jinyu-c-bg-mask'       => 'rgba(0,0,0,.85)',
                '--jinyu-c-border'        => 'rgba(255,255,255,.1)',
                '--jinyu-c-border-strong' => 'rgba(255,255,255,.18)',
                '--jinyu-c-border-footer' => '#1f1f1f',
            ]);
        }

        if (!$override) return '';

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

    $css = ':root{' . $root . '}' . jinyu_dark_override_css();

    echo '<style id="jinyu-dynamic-style"' . jinyu_csp_nonce_attr() . '>' . wp_strip_all_tags($css) . '</style>';
}

/**
 * 屏蔽前台 Admin Bar
 */
add_filter('show_admin_bar', function ($show) {
    if (jinyu_is_checked('hide_admin_bar') && !is_admin()) return false;
    return $show;
});
