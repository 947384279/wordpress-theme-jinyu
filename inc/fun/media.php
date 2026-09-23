<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ─────────────────────────────────────────────────────────────
// 图片优化：WebP 交付（存储无关 · 双通道）
// 通道 A（首选）：云端即时转码。后端支持时在 CDN 原图 URL 后追加转码指令
//   （又拍云 !/format/webp、阿里云 ?x-oss-process=image/format,webp、
//   腾讯云/七牛 ?imageMogr2/format/webp），由 CDN 边缘产出 WebP，零源站负担。
// 通道 B（兜底）：后端不支持即时转码时，本地 GD 生成 WebP 并推送至存储（复用
//   云端存储适配器）走 CDN；无存储则源站直出。全程失败回退原图，绝不裂图。
// URL 替换统一收口在 jinyu_img_to_webp_url()，仅处理本站 uploads 内 jpg/png。
// ─────────────────────────────────────────────────────────────

if (!function_exists('jinyu_generate_webp_file')) {
    /**
     * 由 jpg/png 源文件生成同尺寸 WebP（质量 80），带原子锁防止并发重复生成。
     *
     * @param string $src 源文件绝对路径
     * @param string $dst 目标 .webp 绝对路径
     * @return bool 是否成功产出
     */
    function jinyu_generate_webp_file($src, $dst)
    {
        $lock = $dst . '.lock';
        $lf = @fopen($lock, 'x');
        if (!$lf) {
            // 已有锁：超过 60s 视为异常残留，清理后重试一次；否则让当次请求回退原图
            if ((int) @filemtime($lock) < time() - 60) {
                @unlink($lock);
                $lf = @fopen($lock, 'x');
            }
            if (!$lf) return false;
        }

        $ok = false;
        try {
            $info = @getimagesize($src);
            if ($info) {
                switch ($info[2]) {
                    case IMAGETYPE_JPEG: $img = @imagecreatefromjpeg($src); break;
                    case IMAGETYPE_PNG:  $img = @imagecreatefrompng($src);  break;
                    default: $img = null;
                }
                if ($img) {
                    // 调色板图（PNG8）必须先转真彩，否则 imagewebp 输出异常
                    if (function_exists('imagepalettetotruecolor')) {
                        imagepalettetotruecolor($img);
                    }
                    if (function_exists('imagealphablending')) {
                        imagealphablending($img, true);
                        imagesavealpha($img, true);
                    }
                    $ok = (bool) @imagewebp($img, $dst, 80);
                    imagedestroy($img);
                    // GD 偶发产出 0 字节文件，视为失败并清除
                    if ($ok && (int) @filesize($dst) < 64) {
                        @unlink($dst);
                        $ok = false;
                    }
                }
            }
        } catch (\Throwable $e) {
            $ok = false;
        }

        fclose($lf);
        @unlink($lock);
        return $ok;
    }
}

/* ─────────────────────────────────────────────────────────────
 * WebP 交付策略（存储无关 · 双通道）
 * 通道 A：云端即时转码（首选）。后端支持时在 CDN 原图 URL 后追加转码指令，
 *   由 CDN 边缘直接产出 WebP。零源站 GD、零推送、命中边缘缓存，最优。
 * 通道 B：本地 GD 生成 WebP 并推送至存储（复用 云端存储适配器）走 CDN；
 *   无存储则源站直出（push 模式 CDN 不会自动同步 webp）。
 * 全程错误安全：任何环节失败一律回退原图，绝不裂图。
 * ───────────────────────────────────────────────────────────── */

if (!function_exists('jinyu_webp_transform_suffix')) {
    /**
     * 当前存储后端支持的「即时转 WebP」指令后缀；不支持或加速域名未配置则返回 ''。
     * 仅当存储加速域名已填写且后端已知时生效。
     */
    function jinyu_webp_transform_suffix()
    {
        $provider = (string) jinyu_get_option('storage_provider', '');
        $domain   = trim((string) jinyu_get_option('storage_domain', ''));
        if ($domain === '') {
            return '';
        }
        switch ($provider) {
            case 'upyun':
                return '!/format/webp';
            case 'aliyun':
                return '?x-oss-process=image/format,webp';
            case 'tencent':
            case 'qiniu':
                return '?imageMogr2/format/webp';
            default:
                return '';
        }
    }
}

if (!function_exists('jinyu_webp_storage_cdn_url')) {
    /**
     * 由上传相对路径拼出存储 CDN 原图 URL（不含转码指令）。
     * @param string $rel 如 /2026/09/x.jpg
     */
    function jinyu_webp_storage_cdn_url($rel)
    {
        $domain = rtrim((string) jinyu_get_option('storage_domain', ''), '/');
        $prefix = trim((string) jinyu_get_option('storage_prefix', ''), '/');
        if ($domain === '') {
            return '';
        }
        return $domain . ($prefix !== '' ? '/' . $prefix : '') . $rel;
    }
}

if (!function_exists('jinyu_webp_onthefly_cdn_url')) {
    /**
     * 由上传相对路径拼出「即时转 WebP」的 CDN URL（通道 A 产出）。
     * @param string $rel 如 /2026/09/x.jpg
     */
    function jinyu_webp_onthefly_cdn_url($rel)
    {
        $suffix = jinyu_webp_transform_suffix();
        if ($suffix === '') {
            return '';
        }
        $base = jinyu_webp_storage_cdn_url($rel);
        return $base === '' ? '' : $base . $suffix;
    }
}

if (!function_exists('jinyu_webp_onthefly_confirmed')) {
    /**
     * 一次性自检：确认即时转码确实返回 image/webp，防止后端配置被改动导致裂图。
     * 结果按「服务商+指令」缓存 12h；网络异常时 fail-open（假定可用，避免误关 WebP）。
     * @param string $sample_cdn_url 用于自检的样本 CDN 原图 URL
     */
    function jinyu_webp_onthefly_confirmed($sample_cdn_url)
    {
        $suffix = jinyu_webp_transform_suffix();
        if ($suffix === '') {
            return false;
        }
        $provider = (string) jinyu_get_option('storage_provider', '');
        $key      = 'otf_' . $provider . '_' . md5($suffix);
        $cached   = jinyu_cache_get($key, null);
        if ($cached !== null) {
            return (bool) $cached;
        }
        $ok = true; // fail-open：网络抖动不应误关 WebP
        if ($sample_cdn_url !== '') {
            $r = wp_remote_head($sample_cdn_url . $suffix, array('timeout' => 5, 'sslverify' => true));
            if (!is_wp_error($r)) {
                $code = (int) wp_remote_retrieve_response_code($r);
                $ct   = wp_remote_retrieve_header($r, 'content-type');
                $ok   = ($code === 200 && stripos((string) $ct, 'webp') !== false);
            }
        }
        jinyu_cache_set($key, $ok ? 1 : 0, 12 * HOUR_IN_SECONDS);
        return $ok;
    }
}

if (!function_exists('jinyu_webp_push_to_storage')) {
    /**
     * 通道 B：把本地生成的 .webp 推送到存储后端，返回其 CDN URL；失败返回 ''。
     * @param string $local_webp 本地 .webp 绝对路径
     * @param string $rel_webp   上传相对路径（含 .webp 后缀），如 /2026/09/x.webp
     */
    function jinyu_webp_push_to_storage($local_webp, $rel_webp)
    {
        // 云存储由配套插件提供；未安装时跳过（主题自带本地 WebP 仍可用）
        if (!function_exists('jinyu_is_storage_enabled') || !function_exists('jinyu_storage_config') || !jinyu_is_storage_enabled()) {
            return '';
        }
        $cfg = jinyu_storage_config();
        $ad  = Jinyu_Storage_Factory::make($cfg);
        if (!$ad) {
            return '';
        }
        $prefix = rtrim($cfg['prefix'], '/') . '/';
        $key    = $prefix . ltrim($rel_webp, '/');
        if (!$ad->put($local_webp, $key)) {
            return '';
        }
        $domain = rtrim((string) jinyu_get_option('storage_domain', ''), '/');
        return $domain . '/' . $key;
    }
}

if (!function_exists('jinyu_img_to_webp_url')) {
    /**
     * 将本站 uploads 内的 jpg/png URL 映射为 WebP 交付 URL。
     * 优先云端即时转码（通道 A），否则本地生成+推送/源站（通道 B），失败回退原图。
     *
     * @param string $url 图片 URL（可能为本地或 CDN 形式）
     * @return string WebP 交付 URL 或原 URL
     */
    function jinyu_img_to_webp_url($url)
    {
        if (empty($url) || !is_string($url)) {
            return $url;
        }

        $up = wp_get_upload_dir();
        if (empty($up['baseurl']) || empty($up['basedir'])) {
            return $url;
        }

        // 兼容 CDN：主题 cdn_url 与存储加速域名(storage_domain+prefix) 都可能改写附件 URL，
        // 须统一还原回本地 baseurl 才能映射到本地文件。
        $roots = array();
        $cdn_theme = trim((string) jinyu_get_option('cdn_url', ''));
        if ($cdn_theme) {
            $roots[] = rtrim($cdn_theme, '/');
        }
        if (($sd = trim((string) jinyu_get_option('storage_domain', ''))) !== '') {
            $sp = trim((string) jinyu_get_option('storage_prefix', ''), '/');
            $roots[] = rtrim($sd, '/') . ($sp !== '' ? '/' . $sp : '');
        }
        $local_url = $url;
        foreach ($roots as $root) {
            if ($root && strpos($url, $root) === 0) {
                $local_url = $up['baseurl'] . substr($url, strlen($root));
                break;
            }
        }

        // 非本站上传目录（外链 / 主题资源 / SVG / GIF）→ 不处理
        if (strpos($local_url, $up['baseurl']) !== 0) {
            return $url;
        }

        $path = preg_replace('/[?#].*$/', '', $local_url);
        if (!preg_match('/\.(jpe?g|png)$/i', $path)) {
            return $url;
        }

        $rel = substr($path, strlen($up['baseurl'])); // 如 /2026/09/x.png
        $src = $up['basedir'] . wp_normalize_path($rel);
        if (!is_file($src)) {
            return $url;
        }

        // ── 通道 A：云端即时转码（首选，零源站 GD / 零推送）──
        $otf = jinyu_webp_onthefly_cdn_url($rel);
        if ($otf !== '' && jinyu_webp_onthefly_confirmed(jinyu_webp_storage_cdn_url($rel))) {
            return $otf;
        }

        // ── 通道 B：本地生成（预算上限防首访超时），再推送存储或源站分发 ──
        if (!extension_loaded('gd') || !function_exists('imagewebp')) {
            return $url;
        }
        $dst    = preg_replace('/\.(jpe?g|png)$/i', '.webp', $src);
        $dstRel = preg_replace('/\.(jpe?g|png)$/i', '.webp', $rel);

        // 单次请求内同步生成的 WebP 数量上限：存量多图首访时若全量即时生成，
        // 会串行跑大量 GD 转换导致请求超时 / CPU 突刺。超出预算回退原图，
        // 后续请求（预算重置）继续补齐。
        static $budget = 80;
        if (!is_file($dst)) {
            if ($budget <= 0) {
                return $url;
            }
            $budget--;
            jinyu_generate_webp_file($src, $dst);
            if (!is_file($dst)) {
                return $url;
            }
        }

        // 已配置存储 → 推上云走 CDN；否则源站直出（push 模式 CDN 不会自动同步 webp）
        $pushed = jinyu_webp_push_to_storage($dst, $dstRel);
        if ($pushed !== '') {
            return $pushed;
        }
        return preg_replace('/\.(jpe?g|png)$/i', '.webp', $path);
    }
}

// 上传时同步生成 WebP 副本（是否替换输出 URL 由 jinyu_img_to_webp_url 统一决定）
add_filter('wp_handle_upload', function($result) {
    if (empty($result['file']) || !extension_loaded('gd') || !function_exists('imagewebp')) return $result;
    if (!preg_match('/\.(jpe?g|png)$/i', $result['file'])) return $result;

    $dst = preg_replace('/\.(jpe?g|png)$/i', '.webp', $result['file']);
    if (!is_file($dst)) {
        jinyu_generate_webp_file($result['file'], $dst);
    }
    return $result;
});

// CDN URL 替换
add_filter('wp_get_attachment_url', function($url) {
    $cdn = jinyu_get_option('cdn_url', '');
    if ($cdn && strpos($url, wp_upload_dir()['baseurl']) === 0) {
        return str_replace(wp_upload_dir()['baseurl'], rtrim($cdn, '/'), $url);
    }
    return $url;
});

// 图片懒加载增强 + blur-up 淡入占位
// 给所有 WP 媒体图（缩略图/相册/附件输出）追加 .jinyu-blur-img 类，
// 并用 thumbnail 尺寸小图作 data-ph 模糊占位（前端 blur-up 淡入）。
add_filter('wp_get_attachment_image_attributes', function($attrs, $attachment, $size) {
    if (is_admin()) {
        return $attrs;
    }
    $cl = isset($attrs['class']) ? $attrs['class'] : '';
    if (strpos($cl, 'jinyu-blur-img') === false) {
        $attrs['class'] = trim($cl . ' jinyu-blur-img');
    }
    if (empty($attrs['decoding'])) {
        $attrs['decoding'] = 'async';
    }
    // 附件图 src 同步替换为 WebP（logo、原生相册等走此处；外链/SVG 自动跳过）
    if (!empty($attrs['src'])) {
        $w = jinyu_img_to_webp_url($attrs['src']);
        if ($w !== $attrs['src']) {
            $attrs['src'] = $w;
        }
    }
    $t = wp_get_attachment_image_src($attachment->ID, 'thumbnail');
    if (!empty($t[0])) {
        $attrs['data-ph'] = $t[0];
    }
    return $attrs;
}, 10, 3);

if (!function_exists('jinyu_webp_replace_html_imgs')) {
    /**
     * 对一段 HTML 内所有 <img> 执行 WebP URL 替换（src + srcset），供 the_content 之外的
     * HTML 输出点复用：广告代码块、文本/块小工具、短代码等。SVG / data-uri 自动跳过。
     */
    function jinyu_webp_replace_html_imgs($html) {
        if (empty($html)) return $html;
        if (!extension_loaded('gd') || !function_exists('imagewebp')) return $html;
        if (stripos($html, '<img') === false) return $html;

        $doc = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<meta charset="utf-8">' . $html, LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        foreach ($doc->getElementsByTagName('img') as $img) {
            $src = $img->getAttribute('src');
            if (!$src || preg_match('/\.svg(\?|$)/i', $src) || strpos($src, 'data:') === 0) {
                continue;
            }
            $webpSrc = jinyu_img_to_webp_url($src);
            if ($webpSrc !== $src) {
                $img->setAttribute('src', $webpSrc);
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

        $body = $doc->getElementsByTagName('body')->item(0);
        if ($body) {
            $out = '';
            foreach ($body->childNodes as $node) {
                $out .= $doc->saveHTML($node);
            }
            return $out;
        }
        return $html;
    }
}
