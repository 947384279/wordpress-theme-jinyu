<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 轻量级 User-Agent 解析器（自包含，无第三方依赖）
 *
 * 从 UA 字符串中提取浏览器名称/版本、操作系统平台。
 * 对齐 donatj/PhpUserAgent 核心能力，但用纯正则实现，
 * 避免引入外部库。图标使用内联 SVG（不依赖 FontAwesome）。
 */

if (!function_exists('jinyu_parse_ua')) {
    /**
     * 解析 User-Agent 字符串
     *
     * @param  string|null $ua  UA 字符串，null 时取 $_SERVER['HTTP_USER_AGENT']
     * @return array{browser:string, version:string|null, platform:string|null}
     */
    function jinyu_parse_ua($ua = null)
    {
        if ($ua === null) {
            $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        }

        $result = [
            'browser'  => '',
            'version'  => null,
            'platform' => null,
        ];

        if (!$ua) {
            return $result;
        }

        // ── 平台检测 ──
        if (preg_match('/\(([^)]+)\)/', $ua, $parent)) {
            $p = $parent[1];

            // 优先级排序（高优先级先匹配）
            $platforms = [
                'Windows Phone' => 'Windows Phone',
                'Windows NT 10\.0' => 'Windows 10',
                'Windows NT 6\.\d+' => 'Windows',
                'Android' => 'Android',
                'iPhone' => 'iPhone',
                'iPad' => 'iPad',
                '(?:CrOS|Chrome OS)' => 'Chrome OS',
                'Macintosh|Mac OS X' => 'macOS',
                'Linux' => 'Linux',
                'FreeBSD' => 'FreeBSD',
            ];

            foreach ($platforms as $pattern => $label) {
                if (preg_match('/' . $pattern . '/i', $p)) {
                    $result['platform'] = $label;
                    break;
                }
            }
        }

        // ── 浏览器检测 ──
        $browsers = [
            // (模式 => [显示名, 版本提取正则])
            'MicroMessenger'      => ['Weixin', '/MicroMessenger\/([\d.]+)/'],
            'QQBrowser'           => ['QQ Browser', '/QQBrowser\/([\d.]+)/'],
            'UCBrowser'           => ['UC Browser', '/UCBrowser\/([\d.]+)/'],
            'SamsungBrowser'      => ['Samsung Internet', '/SamsungBrowser\/([\d.]+)/'],
            'Edg(?:e)?/'          => ['Edge', '/Edg(?:e)?\/([\d.]+)/'],
            'OPR/'                => ['Opera', '/OPR\/([\d.]+)/'],
            'Vivaldi/'            => ['Vivaldi', '/Vivaldi\/([\d.]+)/'],
            'Firefox/'            => ['Firefox', '/Firefox\/([\d.]+)/'],
            'CriOS/'              => ['Chrome (iOS)', '/CriOS\/([\d.]+)/'],
            'Chrome/'             => ['Chrome', '/Chrome\/([\d.]+)/'],
            'Safari/'             => ['Safari', '/Version\/([\d.]+)/'],  // Safari 版本在 Version 后
            'Trident/'            => ['IE', '/rv:([\d.]+)/'],
            'MSIE '               => ['IE', '/MSIE\s+([\d.]+)/'],
        ];

        // 用 ~ 作分隔符：模式里含 /（Edg/ Chrome/ Safari/ …），若用 / 当分隔符
        // 会拼出 /Edg(?:e)?//i 这种非法正则，preg_match 直接返回 false 静默失败。
        foreach ($browsers as $pattern => $info) {
            if (preg_match('~' . $pattern . '~i', $ua)) {
                $result['browser'] = $info[0];
                if (preg_match($info[1], $ua, $ver)) {
                    $result['version'] = $ver[1];
                }
                break;
            }
        }

        return $result;
    }
}

// ─────────────────────────────────────────────
//  图标映射（返回 SVG 内联 HTML，零外部依赖）
// ─────────────────────────────────────────────

if (!function_exists('jinyu_ua_icon')) {
    /**
     * 根据 browser/platform 名称返回对应的 SVG 图标 HTML
     *
     * @param  string $name  浏览器或平台名称
     * @param  string $type  'browser' | 'platform'
     * @return string       SVG HTML 或空字符串
     */
    function jinyu_ua_icon($name, $type = 'browser')
    {
        $name  = trim($name);
        $icons = _jinyu_ua_icons();
        $key   = strtolower(str_replace([' ', '.', '(', ')'], '', $name));

        if (isset($icons[$type][$key])) {
            return '<span class="jinyu-ua-icon" title="' . esc_attr($name) . '" aria-hidden="true">' . $icons[$type][$key] . '</span>';
        }

        return '';
    }

    /**
     * 返回全部 SVG 图标定义（内部使用）
     *
     * @return array<string, array<string, string>>
     */
    function _jinyu_ua_icons()
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        // 简洁的几何 SVG，16x16 viewBox，currentColor 继承文字色
        $c = 'currentColor';

        $cache = [
            'browser' => [
                'chrome'    => "<svg viewBox='0 0 24 24' width='16' height='16' fill='$c'><circle cx='12' cy='12' r='10' opacity='.15'/><path d='M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z'/></svg>",
                'firefox'   => "<svg viewBox='0 0 24 24' width='16' height='16' fill='$c'><circle cx='12' cy='12' r='10' opacity='.15'/><path d='M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8 0-3.47 2.23-6.42 5.34-7.52A5.97 5.97 0 0 0 8 9c0 3.31 2.69 6 6 6 .34 0 .67-.03 1-.08C14.13 17.07 13.13 18 12 18zm5.29-4.71l-1.29-1.29c.33-.79.5-1.64.5-2.5 0-1.95-.94-3.68-2.39-4.77L12 6l-2 2 1 1c-.55.28-1 .73-1.28 1.27L8.71 8.29C9.68 7.11 11.25 6.44 13 6.23V4h-2c-3.31 0-6 2.69-6 6 0 1.09.29 2.12.8 3.01L4.93 15.88C4.34 14.72 4 13.4 4 12c0-4.08 3.05-7.44 7-7.93V2C6.48 2 2 6.48 2 12s4.48 10 10 10c2.76 0 5.26-1.12 7.07-2.93l-1.78-1.78z'/></svg>",
                'edge'      => "<svg viewBox='0 0 24 24' width='16' height='16' fill='$c'><path d='M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z' opacity='.15'/><path d='M12 4c-4.41 0-8 3.59-8 8 0 1.85.63 3.55 1.69 4.9L12 12V4zm0 8l-4.9 5.56C8.45 19.37 10.14 20 12 20c3.86 0 7.07-2.73 7.81-6.36L12 12z' opacity='.7'/><path d='M17.81 13.64C17.36 16.54 14.96 18.8 12 18.8c-1.58 0-3.04-.57-4.17-1.52L12 13v-1h5.81z'/></svg>",
                'safari'    => "<svg viewBox='0 0 24 24' width='16' height='16' fill='$c'><circle cx='12' cy='12' r='10' stroke='$c' stroke-width='1.5' fill='none'/><polygon points='15.5,8.5 9.5,14.5 8.5,13.5 14.5,7.5' fill='$c'/><circle cx='12' cy='12' r='2.5' fill='none' stroke='$c' stroke-width='1'/></svg>",
                'opera'     => "<svg viewBox='0 0 24 24' width='16' height='16' fill='$c'><ellipse cx='12' cy='13' rx='8' ry='7' opacity='.15'/><path d='M12 6C7.58 6 4 9.13 4 13s3.58 7 8 7 8-3.13 8-7-3.58-7-8-7zm0 12c-3.31 0-6-2.24-6-5s2.69-5 6-5 6 2.24 6 5-2.69 5-6 5z'/></svg>",
                'ie'        => "<svg viewBox='0 0 24 24' width='16' height='16' fill='$c'><circle cx='12' cy='12' r='10' opacity='.15'/><path d='M4 12c0-3.87 2.75-7.1 6.42-7.84L4.5 16H4c0-1.38.28-2.69.78-3.89L4 12zm8-8c2.82 0 5.32 1.43 6.77 3.61L12 10 7.23 7.61C8.32 5.99 10.06 5 12 5zM4.5 16l6.92-11.84C5.85 5.38 4 8.5 4 12h.5zm15-4c0 4.42-3.58 8-8 8-2.21 0-4.21-.9-5.66-2.34L12 14l5.66 3.66C16.21 19.1 14.21 20 12 20c-4.42 0-8-3.58-8-8h15.5z'/></svg>",
                'weixin'    => "<svg viewBox='0 0 24 24' width='16' height='16' fill='$c'><path d='M8.5 12c-.83 0-1.5-.67-1.5-1.5S7.67 9 8.5 9s1.5.67 1.5 1.5S9.33 12 8.5 12zm7 0c-.83 0-1.5-.67-1.5-1.5S14.67 9 15.5 9s1.5.67 1.5 1.5S16.33 12 15.5 12zM12 2C6.48 2 2 6.48 2 12c0 1.83.5 3.55 1.37 5.03L2 22l5.09-1.56C8.67 21.45 10.29 22 12 22c5.52 0 10-4.48 10-10S17.52 2 12 2z' opacity='.15'/><path d='M15.5 9c.83 0 1.5.67 1.5 1.5S16.33 12 15.5 12 14 11.33 14 10.5 14.67 9 15.5 9zM8.5 9c.83 0 1.5.67 1.5 1.5S9.33 12 8.5 12 7 11.33 7 10.5 7.67 9 8.5 9z'/></svg>",
                'qqbrowser' => "<svg viewBox='0 0 24 24' width='16' height='16' fill='$c'><circle cx='12' cy='12' r='10' opacity='.15'/><ellipse cx='12' cy='13' rx='6' ry='5' fill='none' stroke='$c' stroke-width='1.5'/><circle cx='9.5' cy='12' r='1.5' fill='$c'/><circle cx='14.5' cy='12' r='1.5' fill='$c'/></svg>",
                'ucbrowser' => "<svg viewBox='0 0 24 24' width='16' height='16' fill='$c'><rect x='3' y='5' width='18' height='14' rx='3' opacity='.15' fill='none' stroke='$c' stroke-width='1.5'/><path d='M7 12h10M12 7v10' stroke='$c' stroke-width='1.5' stroke-linecap='round'/></svg>",
                'samsunginternet' => "<svg viewBox='0 0 24 24' width='16' height='16' fill='$c'><ellipse cx='12' cy='12' rx='10' ry='7' opacity='.15' fill='none' stroke='$c' stroke-width='1.5'/><path d='M12 7v10M7 12h10' stroke='$c' stroke-width='1.5' stroke-linecap='round'/></svg>",
                'vivaldi'   => "<svg viewBox='0 0 24 24' width='16' height='16' fill='$c'><circle cx='12' cy='12' r='10' opacity='.15'/><path d='M12 2a10 10 0 00-3.22 19.46c.28-.74.49-1.53.6-2.35A7 7 0 1112 5V2z'/><circle cx='14' cy='10' r='3' opacity='.5'/></svg>",
                'chromeios' => "<svg viewBox='0 0 24 24' width='16' height='16' fill='$c'><circle cx='12' cy='12' r='10' opacity='.15'/><path d='M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93z' opacity='.7'/></svg>",
            ],
            'platform' => [
                'windows'    => "<svg viewBox='0 0 24 24' width='16' height='16' fill='$c'><path d='M3 5v14l8-1.1V6.1L3 5zm9 12.7L21 19V5l-9 1.1v11.6z' opacity='.7'/></svg>",
                'windows10'  => "<svg viewBox='0 0 24 24' width='16' height='16' fill='$c'><path d='M3 5v7h8V5.1L3 5zm9 0V12h8V5l-8 1.1zM3 13v7l8-1.1V13H3zm9 0v6.9L20 20v-7h-8z' opacity='.7'/></svg>",
                'windowsphone'=> "<svg viewBox='0 0 24 24' width='16' height='16' fill='$c'><rect x='6' y='2' width='12' height='20' rx='2' fill='none' stroke='$c' stroke-width='1.5'/><line x1='10' y1='19' x2='14' y2='19' stroke='$c' stroke-width='1.5' stroke-linecap='round'/></svg>",
                'macos'      => "<svg viewBox='0 0 24 24' width='16' height='16' fill='$c'><path d='M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.8-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z' opacity='.7'/></svg>",
                'iphone'     => "<svg viewBox='0 0 24 24' width='16' height='16' fill='$c'><rect x='7' y='2' width='10' height='20' rx='2' fill='none' stroke='$c' stroke-width='1.5'/><line x1='10' y1='18' x2='14' y2='18' stroke='$c' stroke-width='1.5' stroke-linecap='round'/></svg>",
                'ipad'       => "<svg viewBox='0 0 24 24' width='16' height='16' fill='$c'><rect x='5' y='3' width='14' height='18' rx='2' fill='none' stroke='$c' stroke-width='1.5'/><circle cx='12' cy='18' r='1' fill='$c'/></svg>",
                'android'    => "<svg viewBox='0 0 24 24' width='16' height='16' fill='$c'><path d='M17.6 9.48l1.84-3.18c.16-.31.04-.69-.26-.85-.29-.15-.65-.06-.83.22l-1.88 3.24a11.5 11.5 0 00-8.94 0L5.65 5.67c-.19-.29-.54-.38-.83-.22-.31.16-.42.54-.27.85L6.4 9.48A10.78 10.78 0 001 18h22a10.78 10.78 0 00-5.4-8.52zM7 15.25a1.25 1.25 0 110-2.5 1.25 1.25 0 010 2.5zm10 0a1.25 1.25 0 110-2.5 1.25 1.25 0 010 2.5z' opacity='.7'/></svg>",
                'linux'      => "<svg viewBox='0 0 24 24' width='16' height='16' fill='$c'><circle cx='9' cy='10' r='1.5' fill='$c'/><circle cx='15' cy='10' r='1.5' fill='$c'/><path d='M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8 0-1.48.41-2.86 1.12-4.06l1.66 1.66A5.98 5.98 0 006 12c0 3.31 2.69 6 6 6 1.1 0 2.13-.3 3.01-.82l1.66 1.66A7.95 7.95 0 0112 20z' opacity='.15'/><path d='M12 6c-3.31 0-6 2.69-6 6 0 1.1.3 2.13.82 3.01l1.66-1.66A3.97 3.97 0 0112 10c2.21 0 4 1.79 4 4 0 1.1-.45 2.1-1.17 2.83l1.66 1.66A5.98 5.98 0 0018 12c0-3.31-2.69-6-6-6z' opacity='.7'/></svg>",
                'chromeos'   => "<svg viewBox='0 0 24 24' width='16' height='16' fill='$c'><circle cx='12' cy='12' r='10' opacity='.15'/><path d='M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8 0-1.85.63-3.55 1.69-4.9L12 12V4z' opacity='.5'/></svg>",
                'freebsd'   => "<svg viewBox='0 0 24 24' width='16' height='16' fill='$c'><path d='M7.5 4l-4 4 9 9 4-4-9-9zm2 1l6 6-1.5 1.5-6-6L9.5 5z' opacity='.7'/></svg>",
            ],
        ];

        return $cache;
    }
}
