<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 资源优化
 * 内置常驻的前端资源优化（图片懒加载 / 自动补 alt / WebP 替换 / 脚本 defer / 去 jQuery Migrate / 去 Dashicons / 关古腾堡前台样式），无开关、默认常开。
 * 注：Emoji / wp-embed / Heartbeat 移除、HTML 压缩、整页缓存启停与有效期已迁至「性能优化中心（perf）」统一管控，此处不再重复实现。
 */

/* ==========================================================================
   资源优化：移除不必要的脚本 / 样式
   ========================================================================== */

// Emoji 脚本移除已迁入「性能优化中心」的 disable_emoji 开关统一管控（含 feed / 邮件过滤器），
// 不再在此硬编码，避免开关失效与重复实现。

// 主题内置：始终移除 jQuery Migrate（现代环境无需，老插件兼容问题极少）
add_action('wp_default_scripts', function ($scripts) {
    if (!isset($scripts->registered['jquery'])) return;
    $jq = $scripts->registered['jquery'];
    if (isset($jq->deps) && in_array('jquery-migrate', $jq->deps, true)) {
        $jq->deps = array_values(array_diff($jq->deps, ['jquery-migrate']));
    }
});

// 非管理员移除 Dashicons：仅后台需要，前台访客永不加载。
add_action('wp_enqueue_scripts', function () {
    if (!is_user_logged_in()) wp_deregister_style('dashicons');
});

// wp-embed 移除已迁入「性能优化中心」的 disable_embed 开关统一管控，此处不再重复。

// 禁用前台古腾堡样式：前台不使用区块样式时默认关闭。
add_action('wp_enqueue_scripts', function () {
    wp_dequeue_style('wp-block-library');
    wp_dequeue_style('wp-block-library-theme');
    wp_dequeue_style('wc-block-style');
}, 100);

// 禁用后台古腾堡（区块）编辑器：改用经典编辑器撰写文章/页面。
// 仅拦截「是否启用区块编辑器」判定，不影响已发布内容，也不影响主题自身的区块资源注册。
if (jinyu_is_checked('disable_gutenberg_editor')) {
    add_filter('use_block_editor_for_post_type', '__return_false');
}

// 主题脚本统一加 defer：均在 footer 输出且按依赖顺序加载，defer 不破坏执行顺序，
// 可彻底消除首屏解析阻塞（与样式表阻塞无关）。原「脚本延迟加载」开关废弃，默认常开。
add_filter('script_loader_tag', function ($tag, $handle) {
    if (strpos($handle, 'jinyu-') === 0 && strpos($tag, 'defer') === false) {
        $tag = str_replace(' src', ' defer src', $tag);
    }
    return $tag;
}, 10, 2);

// 前台 Heartbeat 移除已迁入「性能优化中心」的 disable_heartbeat 开关统一管控（前台 + 后台均由 perf 处理），
// 此处不再重复。

/* ==========================================================================
   内容校验：正文输出时统一处理外链与图片
   ========================================================================== */

add_filter('the_content', 'jinyu_content_filter', 20);
// 文本小工具 / 块小工具 HTML 内的图片也走 WebP 替换（与 the_content 对齐）
add_filter('widget_text', function ($t) { return jinyu_webp_replace_html_imgs($t); });
add_filter('widget_block_content', function ($t) { return jinyu_webp_replace_html_imgs($t); });
function jinyu_content_filter($content)
{
    if (!is_singular() || empty($content)) return $content;

    // 外部链接统一由「外链新窗口 + nofollow」(ext_link_target) 单一开关控制；
    // 原 ext_link_blank / ext_link_nofollow 为重复声明，已移除，避免关闭主开关仍被旧键强制开启。
    $ext_enabled = jinyu_is_checked('ext_link_target');
    $ext_blank   = $ext_enabled;
    $ext_nofollow= $ext_enabled;
    $go          = jinyu_is_checked('go_link_enable');
    $alt         = true; // 自动补 alt：主题图片缺 alt 一律补标题，利于 SEO（内置常开）
    $webp        = true; // WebP 替换：封面/卡片等主题图片输出自动替换为 WebP（内置常开）

    // 快速路径：正文里既无 <img> 又无需处理外链（go/新窗口/nofollow 全关）时，
    // 直接返回原文，避免每次渲染都对整段正文跑 DOMDocument 解析+重序列化
    // （DOM 重序列化会改写 HTML 实体/属性顺序/自闭合标签，纯文本正文完全没必要承担此开销）。
    $need_links = $ext_blank || $ext_nofollow || $go;
    $has_img    = stripos($content, '<img') !== false;
    $has_link   = $need_links && stripos($content, '<a ') !== false;
    if (!$has_img && !$has_link) {
        return $content;
    }

    $home = wp_parse_url(home_url(), PHP_URL_HOST);
    $doc = new DOMDocument();
    $prev = libxml_use_internal_errors(true);
    // 前置 charset meta，让 DOMDocument 按 UTF-8 解析中文（避免已废弃的 mb_convert_encoding）
    $doc->loadHTML('<meta charset="utf-8">' . $content, LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);

    // 外链处理（短链跳转 go 可独立生效，不依赖外链新窗口/nofollow 开关）
    if ($ext_blank || $ext_nofollow || $go) {
        foreach ($doc->getElementsByTagName('a') as $a) {
            $href = $a->getAttribute('href');
            if (!$href) continue;
            $host = wp_parse_url($href, PHP_URL_HOST);
            if ($host && $host !== $home) {
                if ($go && function_exists('jinyu_go_sign')) {
                    $a->setAttribute('href', home_url('/go/?url=' . urlencode($href) . '&sig=' . jinyu_go_sign($href)));
                }
                if ($ext_blank && $a->getAttribute('target') !== '_blank') $a->setAttribute('target', '_blank');
                $rel = $a->getAttribute('rel');
                $rels = array_filter(array_map('trim', explode(' ', $rel)));
                if ($ext_nofollow && !in_array('nofollow', $rels, true)) $rels[] = 'nofollow';
                if ($ext_blank && !in_array('noopener', $rels, true)) $rels[] = 'noopener';
                $a->setAttribute('rel', implode(' ', $rels));
            }
        }
    }

    // 图片处理：WebP 替换（始终）+ 懒加载 + 自动 alt + blur-up 淡入占位（受 $alt 控制）
    $title = get_the_title();
    foreach ($doc->getElementsByTagName('img') as $img) {
        $src = $img->getAttribute('src');
        // SVG / data-uri（头像、验证码、二维码等）不参与 WebP 与 blur-up
        $skipBlur = $src && (preg_match('/\.svg(\?|$)/i', $src) || strpos($src, 'data:') === 0);

        // WebP 替换：本地 jpg/png 附件图替换为同名 .webp（按需生成）
        if ($webp && $src && !$skipBlur) {
            $webpSrc = jinyu_img_to_webp_url($src);
            if ($webpSrc !== $src) {
                $img->setAttribute('src', $webpSrc);
                // srcset 同步替换每个候选 URL（保留尺寸描述符）
                $srcset = $img->getAttribute('srcset');
                if ($srcset) {
                    $newSet = preg_replace_callback('/(\S+\.(?:jpe?g|png))(?=\s|$|\s+\d+[wx])/i', function ($m) {
                        return jinyu_img_to_webp_url($m[1]);
                    }, $srcset);
                    if ($newSet !== $srcset) {
                        $img->setAttribute('srcset', $newSet);
                    }
                }
            }
        }

        if (!$alt) {
            continue;
        }
        if (!$img->getAttribute('loading')) $img->setAttribute('loading', 'lazy');
        if (!$img->getAttribute('alt')) $img->setAttribute('alt', $title);
        if ($skipBlur) {
            continue;
        }
        if (!$img->hasAttribute('decoding')) $img->setAttribute('decoding', 'async');
        $cl = $img->getAttribute('class');
        if (strpos($cl, 'jinyu-blur-img') === false) {
            $img->setAttribute('class', trim($cl . ' jinyu-blur-img'));
        }
        // 用缩略图作模糊占位（LQIP）；CDN / 外链图反查失败则降级为纯色占位
        if ($src && !$img->hasAttribute('data-ph')) {
            $aid = jinyu_url_to_postid($src);
            if ($aid) {
                $t = wp_get_attachment_image_src($aid, 'thumbnail');
                if ($t && !empty($t[0])) $img->setAttribute('data-ph', esc_url(jinyu_lqip_url($t[0]))); // 占位图改为 CDN 极小 LQIP（约0.3KB），避免回退原图拖慢加载
            }
        }
    }

    // 仅取 body 内部，避免输出 <html>/<head>/<meta> 等包裹
    $body = $doc->getElementsByTagName('body')->item(0);
    if ($body) {
        $out = '';
        foreach ($body->childNodes as $node) {
            $out .= $doc->saveHTML($node);
        }
        return $out;
    }
    // 解析异常导致 body 缺失时，原样返回未改动的原文，避免把整份 <html> 文档外壳注入正文
    return $content;
}

/* ==========================================================================
   性能：HTML 压缩（去除标签间空白与注释，仅前台整页生效，跳过后台/接口）
   ========================================================================== */
// 前台 HTML 压缩改由「性能优化中心」的 html_minify 开关管控（运行时读取，避免包含期依赖未定义函数）。
add_action('template_redirect', function () {
    if (is_admin()) {
        return;
    }
    if ( ! jinyu_perf_opt( 'html_minify', true ) ) {
        return;
    }
    if (wp_doing_ajax() || wp_is_json_request() || (defined('REST_REQUEST') && REST_REQUEST)) {
        return;
    }
    ob_start('jinyu_minify_html');
}, 5);

function jinyu_minify_html(string $html): string
{
    // 仅对完整 HTML 文档压缩，避免误伤 JSON / XML / 片段输出
    if (stripos($html, '<!doctype') === false && stripos($html, '<html') === false) {
        return $html;
    }

    // 先抽取受保护原始块，压缩后再还原，避免破坏 JS/CSS/排版与特定节点：
    // pre/textarea/script/style 内容不可折叠；noscript 含特殊解析（其原始形态为 <!--...-->），
    // 若留给注释剔除正则会被当 HTML 注释整段删掉，故一并保护。
    $raw = [];
    $html = preg_replace_callback(
        '#(<(pre|textarea|script|style|noscript)[^>]*>.*?</\2>)#is',
        function ($m) use (&$raw) {
            $raw[] = $m[0];
            return "\x00JINYU_RAW_" . (count($raw) - 1) . "\x00";
        },
        $html
    );

    // 去除 HTML 注释（保留 IE 条件注释）
    $html = preg_replace('#<!--(?!\[if).*?-->#s', '', $html);
    // 折叠标签间空白（换行/缩进）为单行衔接
    $html = preg_replace('/>\s+?</', '><', $html);

    // 还原受保护块
    $html = preg_replace_callback(
        '#\x00JINYU_RAW_(\d+)\x00#',
        function ($m) use ($raw) {
            return $raw[(int) $m[1]] ?? '';
        },
        $html
    );

    return trim($html);
}

/* ==========================================================================
   百度主动推送：统一由 inc/fun/baidu-push.php 处理（含「已推」去重标记 + 非阻塞）。
   此处不再重复注册 save_post，避免同一篇文章被推两次、且每次编辑都重复消耗百度每日配额。
   ========================================================================== */

/* ==========================================================================
   视频短代码 [jinyu_video] 已迁至配套插件 jinyu-theme-companion（inc/fun/short-code.php）。
   主题不再注册任何短代码，以符合 WordPress.org 主题库规范。
   ========================================================================== */

