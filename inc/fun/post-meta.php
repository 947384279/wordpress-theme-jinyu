<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if (!function_exists('jinyu_get_post_cover')) {
    /**
     * 取文章封面 URL
     *
     * @param int    $post_id 文章 ID
     * @param string $size    请求的尺寸（仅对特色图/可回溯附件的图生效）
     * @param bool   $webp    是否输出 WebP（SEO 元信息传 false，保证爬虫兼容）
     */
    function jinyu_get_post_cover($post_id = 0, $size = 'medium', $webp = true)
    {
        global $post;
        $pid = $post_id ?: (is_object($post) ? $post->ID : 0);
        if (!$pid) return '';

        // 请求内 memo：同一请求里卡片常对同文章取不同尺寸（主图 + 模糊占位图），
        // 附件对象经 the_posts 批量预热后，这里直接返回缓存结果，避免重复函数调用。
        static $memo = [];
        $mkey = $pid . '|' . $size . '|' . ($webp ? 1 : 0);
        if (array_key_exists($mkey, $memo)) {
            return $memo[$mkey];
        }

        // 跨请求缓存：封面“来源解析”结果（原始 URL + 附件 ID）落对象缓存/transient，
        // 避免每页重复跑 get_post_meta / 拉正文 / attachment_url_to_postid；
        // WebP 与降采样仍由 jinyu_cover_url 在渲染时即时处理（is_file 开销极小）。
        $cache_key = 'cover_src_' . $pid . '_' . $size;
        $resolved  = jinyu_cache_get($cache_key);
        if (!is_array($resolved) || !isset($resolved['u'])) {
            $resolved = jinyu_resolve_cover_source($pid, $size);
            jinyu_cache_set($cache_key, $resolved, HOUR_IN_SECONDS);
        }

        // 死图自动兜底：外链封面（含存储加速域名的远程图）探测为死图时，
        // 回退到类目封面并重写跨请求缓存，避免下一请求重复探测。
        if (!empty($resolved['u']) && jinyu_cover_is_external($resolved['u']) && jinyu_external_cover_dead($resolved['u'])) {
            $resolved = ['u' => jinyu_category_cover_url($pid), 'a' => 0];
            jinyu_cache_set($cache_key, $resolved, HOUR_IN_SECONDS);
        }

        $out = jinyu_cover_url($resolved['u'], $webp, $resolved['a']);
        $memo[$mkey] = $out;
        return $out;
    }

    if (!function_exists('jinyu_get_post_cover_srcset')) {
        /**
         * 为文章封面构建响应式 srcset（移动端按 DPR/视口择优选图，省带宽、提锐度）。
         * 仅对“可回溯到本地附件”的封面生效；外链 / 默认占位图返回空串，交单 src 兜底。
         *
         * 去重逻辑：按尺寸升序收集候选，仅当 URL 与前一条不同时保留——
         * 内容首图会被统一降采样到 large，多尺寸会退化成同 URL，必须去重，否则生成重复候选。
         *
         * @param int  $post_id
         * @param bool $webp 是否转 WebP（与 jinyu_get_post_cover 一致）
         * @return string srcset 属性值，如 "u 300w, u 768w, u 1024w"；候选不足 2 个返回 ''
         */
        function jinyu_get_post_cover_srcset($post_id = 0, $webp = true)
        {
            global $post;
            $pid = $post_id ?: (is_object($post) ? $post->ID : 0);
            if (!$pid) return '';

            // 复用封面来源解析（带跨请求缓存）拿附件 ID；无附件（外链/默认图）直接返回空
            $resolved = jinyu_resolve_cover_source($pid, 'large');
            $aid = (int) ($resolved['a'] ?? 0);
            if ($aid <= 0) {
                // 内容首图可能是 CDN 域名，resolve_cover_source 未做「CDN→本地」还原，
                // attachment_url_to_postid 会落空；回退到 URL 反查（已含 本地还原），覆盖上传目录内图。
                $u = $resolved['u'] ?? '';
                if ($u) {
                    $fb = jinyu_get_url_srcset($u, $webp);
                    if ($fb !== '') return $fb;
                }
                return '';
            }

            $sizes = ['medium', 'medium_large', 'large'];
            $parts = [];
            $last  = '';
            foreach ($sizes as $sz) {
                $src = wp_get_attachment_image_src($aid, $sz);
                if (empty($src[0]) || empty($src[1])) continue;
                $url = $webp ? jinyu_img_to_webp_url($src[0]) : $src[0];
                if ($url === $last) continue; // 去重（内容首图降级后多尺寸同 URL）
                $parts[] = $url . ' ' . (int) $src[1] . 'w';
                $last    = $url;
            }
            return count($parts) >= 2 ? implode(', ', $parts) : '';
        }
    }

    if (!function_exists('jinyu_get_url_srcset')) {
        /**
         * 为任意“本站上传图”URL 构建响应式 srcset（用于仅有 URL 的场景，如手动轮播）。
         * 外链 / 非上传目录 / 无法反查到附件的图返回空串，交单 src 兜底。
         * 逻辑与 jinyu_get_post_cover_srcset 一致：按尺寸升序、URL 去重、逐条转 WebP。
         *
         * @param string $url
         * @param bool   $webp
         * @return string
         */
        function jinyu_get_url_srcset($url, $webp = true)
        {
            if (empty($url) || !is_string($url)) return '';
            $clean = jinyu_strip_transform_suffix($url);
            $up    = wp_get_upload_dir();
            $base  = !empty($up['baseurl']) ? $up['baseurl'] : '';
            if (!$base) return '';

            // 兼容 CDN：把 CDN 域名还原回本地 baseurl 以便 attachment_url_to_postid 命中
            $roots = [$base];
            $cdn   = trim((string) jinyu_get_option('cdn_url', ''));
            if ($cdn) $roots[] = rtrim($cdn, '/');
            if (($sd = trim((string) jinyu_get_option('storage_domain', ''))) !== '') {
                $sp = trim((string) jinyu_get_option('storage_prefix', ''), '/');
                $roots[] = rtrim($sd, '/') . ($sp !== '' ? '/' . $sp : '');
            }
            $local = $clean;
            foreach ($roots as $root) {
                if ($root && strpos($clean, $root) === 0) {
                    $local = $base . substr($clean, strlen($root));
                    break;
                }
            }
            if (strpos($local, $base) !== 0) return ''; // 非本站上传图
            if (!function_exists('attachment_url_to_postid')) return '';
            $aid = (int) attachment_url_to_postid($local);
            if ($aid <= 0) return '';

            $sizes = ['medium', 'medium_large', 'large'];
            $parts = [];
            $last  = '';
            foreach ($sizes as $sz) {
                $src = wp_get_attachment_image_src($aid, $sz);
                if (empty($src[0]) || empty($src[1])) continue;
                $u = $webp ? jinyu_img_to_webp_url($src[0]) : $src[0];
                if ($u === $last) continue;
                $parts[] = $u . ' ' . (int) $src[1] . 'w';
                $last    = $u;
            }
            return count($parts) >= 2 ? implode(', ', $parts) : '';
        }
    }

    if (!function_exists('jinyu_category_cover_url')) {
        /**
         * 取文章所属分类的默认封面（类目兜底图）。
         *
         * 每个类目在 assets/img/cat-cover/<slug>-<n>.svg 预生成了 3 张风格统一的图，
         * 按文章 ID 取模稳定选择，保证同一篇文章每次取到同一张（不抖动）。
         * 无分类 / 类目无预生成图时回退主题内置默认缩略图。
         *
         * @param int $post_id
         * @return string 封面 URL
         */
        function jinyu_category_cover_url($post_id = 0)
        {
            global $post;
            $pid = $post_id ?: (is_object($post) ? $post->ID : 0);
            $fallback = get_theme_file_uri('assets/img/default-thumb.jpg');
            if (!$pid) {
                return $fallback;
            }

            $cats = get_the_category($pid);
            $slug = '';
            if (!empty($cats) && !is_wp_error($cats)) {
                $slug = $cats[0]->slug;
            }
            if (!$slug) {
                return $fallback;
            }

            $n   = ($pid % 3) + 1; // 1..3，按文章 ID 稳定
            $rel = 'assets/img/cat-cover/' . $slug . '-' . $n . '.svg';
            if (!file_exists(get_theme_file_path($rel))) {
                return $fallback;
            }
            return get_theme_file_uri($rel);
        }
    }

    if (!function_exists('jinyu_cover_is_external')) {
        /**
         * 判断封面 URL 是否为「外链图」（非本站上传目录 / 非主题资源）。
         * 外链图才会做死图探测；本站图不可能"死"，跳过以免浪费请求。
         * 注意：存储加速域名（如 cdn.xxx）托管的图也算外链——它们本质是远程 HTTP 资源，同样可能下线。
         */
        function jinyu_cover_is_external($url)
        {
            if (!is_string($url) || !preg_match('#^https?://#i', $url)) return false;
            if (preg_match('/\.(svg|gif)$/i', $url)) return false; // 主题类目封面 / 动图，不探测
            $up   = wp_get_upload_dir();
            $base = !empty($up['baseurl']) ? $up['baseurl'] : '';
            if ($base && strpos($url, $base) === 0) return false;  // 本站上传图
            if (strpos($url, home_url()) === 0) return false;      // 主题资源 / 默认图
            return true;
        }
    }

    if (!function_exists('jinyu_external_cover_dead')) {
        /**
         * 探测外链封面是否已死（裂图）。结果按 URL 缓存 12h（transient），同请求内 memo。
         *
         * 判定（与 2026-09-19 全站审计口径一致）：
         * - 用 GET + Range 头探测（部分图床拒 HEAD 返 405，会造成假阳性）；
         * - 404/410/403 → dead（403 防盗链在浏览器里同样裂图，按死图算）；
         * - 网络错误 / 429 / 5xx → fail-open 视为活图，避免误杀。
         */
        function jinyu_external_cover_dead($url)
        {
            static $memo = [];
            if (array_key_exists($url, $memo)) return $memo[$url];

            $key   = 'jinyu_cov_dead_' . md5($url);
            $cache = get_transient($key);
            if ($cache !== false) {
                $memo[$url] = ($cache === '1');
                return $memo[$url];
            }

            $dead = false;

            // SSRF 防护：URL 来自文章自定义字段（作者级可写），探测前必须校验。
            // 仅允许 http/https 公网地址；主机解析到内网/保留段（127.0.0.1、10.x、192.168.x、169.254.x 等）
            // 一律不发起请求（fail-open 视为活图，不误杀）。注：重定向目标不在本函数控制内，
            // 依赖 WP HTTP 层限制，此处按图片探测的低风险场景接受。
            $parts = wp_parse_url($url);
            $scheme = strtolower((string) ($parts['scheme'] ?? ''));
            $host = (string) ($parts['host'] ?? '');
            if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
                return false;
            }
            // 主机为字面 IP 时直接校验；域名则解析后校验（gethostbyname 仅 IPv4，
            // 纯 IPv6 主机解析不到 A 记录时按失败处理 → fail-open 不误杀）。
            $ip = filter_var($host, FILTER_VALIDATE_IP)
                ? $host
                : gethostbyname($host);
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $memo[$url] = false;
                return false;
            }

            $res  = wp_remote_get($url, [
                'timeout'     => 3,
                'redirection' => 3,
                'headers'     => [
                    'Range'      => 'bytes=0-1023',
                    'Referer'    => home_url('/'),
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
                ],
            ]);
            if (!is_wp_error($res)) {
                $code = (int) wp_remote_retrieve_response_code($res);
                if ($code === 404 || $code === 410 || $code === 403) {
                    $dead = true;
                }
            }

            set_transient($key, $dead ? '1' : '0', 12 * HOUR_IN_SECONDS);
            $memo[$url] = $dead;
            return $dead;
        }
    }

    if (!function_exists('jinyu_resolve_cover_source')) {
        /**
         * 解析文章封面的“原始来源”（不做 WebP / 降采样），结果可跨请求缓存。
         * 返回 ['u' => 原始封面 URL, 'a' => 已知附件 ID(无则 0)]。
         */
        function jinyu_resolve_cover_source($pid, $size)
        {
            $src = '';
            $aid = 0;

            // 1. 特色图
            if (has_post_thumbnail($pid)) {
                $tid   = get_post_thumbnail_id($pid);
                $thumb = wp_get_attachment_image_src($tid, $size);
                if ($thumb && !empty($thumb[0])) {
                    $src = $thumb[0];
                    $aid = $tid;
                }
            }

            // 2. 缓存正文第一张图（提取时一并存下附件 ID，渲染时跳过反向查找）
            if ($src === '') {
                $cached = get_post_meta($pid, '_jinyu_cover_from_content', true);
                if ($cached) {
                    $aid = (int) get_post_meta($pid, '_jinyu_cover_attach_id', true);
                    // 旧文章首次解析时回填附件 ID（一次性，仅本站上传图），回填后预热钩子即可批量加载；
                    // 外链/CDN 封面无法反查到附件，跳过以免徒增查询且必然返回 0
                    if ($aid === 0) {
                        $up   = wp_get_upload_dir();
                        $base = !empty($up['baseurl']) ? $up['baseurl'] : '';
                        if ($base && strpos($cached, $base) === 0 && function_exists('attachment_url_to_postid')) {
                            $aid = (int) attachment_url_to_postid($cached);
                            if ($aid) {
                                update_post_meta($pid, '_jinyu_cover_attach_id', $aid);
                            }
                        }
                    }
                    $src = $cached;
                }
            }

            // 3. 从正文提取第一张 <img>
            if ($src === '') {
                $content = get_post_field('post_content', $pid);
                if ($content && preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $content, $m)) {
                    $url = $m[1];
                    if (!preg_match('/\.(gif|svg)$/i', $url)) {
                        $aid = function_exists('attachment_url_to_postid') ? (int) attachment_url_to_postid($url) : 0;
                        update_post_meta($pid, '_jinyu_cover_from_content', $url);
                        if ($aid) {
                            update_post_meta($pid, '_jinyu_cover_attach_id', $aid);
                        }
                        $src = $url;
                    }
                }
            }

            // 4. 兜底默认缩略图（设置中指定）
            if ($src === '') {
                $df = jinyu_get_option('default_thumbnail', '');
                if ($df) {
                    $src = $df;
                }
            }

            // 4.5 类目兜底：无图文章按所属分类取预生成的类目封面（assets/img/cat-cover/<slug>-<n>.svg）
            if ($src === '') {
                $src = jinyu_category_cover_url($pid);
            }

            // 5. 最终兜底：主题内置默认缩略图（七彩云渐变，assets/img/default-thumb.jpg）
            if ($src === '') {
                $src = get_theme_file_uri('assets/img/default-thumb.jpg');
            }

            return ['u' => $src, 'a' => $aid];
        }
    }
}

if (!function_exists('jinyu_cover_url')) {
    /**
     * 封面 URL 统一出口：
     * - 本站上传目录内的 jpg/png 尝试替换为 WebP（按需生成，见 media.php）；
     * - 正文提取的原始大图先回溯附件，降级为 large 中间尺寸，避免原图直出。
     *
     * @param string $url           原始封面 URL
     * @param bool   $webp          是否允许 WebP 替换
     * @param int    $attachment_id 已知附件 ID（特色图场景由调用方透传），
     *                              可跳过 attachment_url_to_postid 冗余反查
     */
    function jinyu_cover_url($url, $webp = true, $attachment_id = 0)
    {
        if (empty($url) || !is_string($url)) return $url;

        // 原图降采样：能找到对应附件时改取 large 中间尺寸（1024px）
        // 仅对「本站上传目录内、且未带尺寸后缀」的图回退降采样；
        // 外链/CDN/主题资源/已带尺寸后缀的图直接跳过 attachment_url_to_postid，
        // 否则每次渲染都多查一次且对站外图必然返回 0（纯浪费）。
        static $memo = [];
        $clean = jinyu_strip_transform_suffix($url);

        // 以上传目录 baseurl 判定“本站上传图”，兼容自定义上传路径与 CDN 域名；
        // 旧的硬编码 '/wp-content/uploads/' 会在自定义上传目录或 CDN 下误判为站外，跳过降采样与原图直出。
        $up   = wp_get_upload_dir();
        $base = !empty($up['baseurl']) ? $up['baseurl'] : '';
        // 兼容 CDN：同 jinyu_img_to_webp_url，识别主题 cdn_url 与存储加速域名(storage_domain+prefix)，
        // 把 CDN URL 还原回本地 baseurl 以便 attachment_url_to_postid 命中、做 large 降采样。
        $roots = [];
        $cdn_theme = trim((string) jinyu_get_option('cdn_url', ''));
        if ($cdn_theme) {
            $roots[] = rtrim($cdn_theme, '/');
        }
        if (($sd = trim((string) jinyu_get_option('storage_domain', ''))) !== '') {
            $sp = trim((string) jinyu_get_option('storage_prefix', ''), '/');
            $roots[] = rtrim($sd, '/') . ($sp !== '' ? '/' . $sp : '');
        }
        $local_url = $clean;
        foreach ($roots as $root) {
            if ($root && strpos($clean, $root) === 0) {
                $local_url = $base . substr($clean, strlen($root));
                break;
            }
        }

        $is_local_upload = $base && strpos($local_url, $base) === 0;
        if ($is_local_upload && preg_match('/-\d+x\d+\./', $clean) === 0 && function_exists('attachment_url_to_postid')) {
            if (!array_key_exists($clean, $memo)) {
                // 已有附件 ID 直接复用，避免反查；否则用本地 URL 反查（CDN URL 无法命中 meta）
                $aid = $attachment_id > 0 ? (int) $attachment_id : attachment_url_to_postid($local_url);
                $memo[$clean] = '';
                if ($aid) {
                    $l = wp_get_attachment_image_src($aid, 'large');
                    if (!empty($l[0]) && basename($l[0]) !== basename($clean)) {
                        $memo[$clean] = $l[0];
                    }
                }
            }
            if ($memo[$clean] !== '') {
                $url = $memo[$clean];
            }
        }

        return $webp ? jinyu_img_to_webp_url($url) : $url;
    }
}

// 发布/更新时预提取正文首图作封面兜底，避免前台渲染时写库与拉全文（仅在无特色图时提取）
add_action('save_post', function ($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;
    if (get_post_status($post_id) !== 'publish') return;
    if (has_post_thumbnail($post_id)) return;

    $content = get_post_field('post_content', $post_id);
    if ($content && preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $content, $m)) {
        $url = $m[1];
        if (!preg_match('/\.(gif|svg)$/i', $url)) {
            $aid = function_exists('attachment_url_to_postid') ? (int) attachment_url_to_postid($url) : 0;
            update_post_meta($post_id, '_jinyu_cover_from_content', $url);
            if ($aid) {
                update_post_meta($post_id, '_jinyu_cover_attach_id', $aid);
            }
        }
    }
}, 20, 1);

if (!function_exists('jinyu_placeholder')) {
    function jinyu_placeholder($w = 400, $h = 250)
    {
        $primary = esc_attr(jinyu_get_option('style_color_primary', '#FF6B35'));
        return "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='{$w}' height='{$h}'><rect width='100%25' height='100%25' fill='{$primary}' fill-opacity='0.12'/><text x='50%25' y='50%25' font-family='sans-serif' font-size='20' fill='{$primary}' fill-opacity='0.55' text-anchor='middle' dominant-baseline='middle'>JINYU</text></svg>";
    }
}

if (!function_exists('jinyu_get_post_views')) {
    function jinyu_get_post_views($post_id = 0)
    {
        global $post;
        $pid = $post_id ?: (isset($post) ? $post->ID : 0);
        if (!$pid) return 0;
        $count = (int) get_post_meta($pid, 'jinyu_views', true);
        return $count;
    }
}

if (!function_exists('jinyu_show_views')) {
    /**
     * 是否展示浏览量（后台「全局设置 › 隐藏文章浏览量」）。
     * 所有输出浏览量的模板都走这个判定，避免开关只藏一处。
     */
    function jinyu_show_views(): bool
    {
        return !jinyu_is_checked('hide_post_views');
    }
}

add_action('wp_head', 'jinyu_auto_increment_views');
function jinyu_auto_increment_views()
{
    if (is_single() && !is_admin()) {
        global $post;
        if (!$post) return;
        $pid = $post->ID;
        // 冷却秒数：后台「全局设置 › 同一 IP 浏览量冷却秒数」
        // 按 IP + 文章做瞬时冷却，同一 IP 在冷却期内重复刷新不重复计数（防狂刷）
        $wait = max(1, (int) jinyu_get_option('views_wait_seconds', 10));
        $key  = 'jinyu_vw_' . md5(($_SERVER['REMOTE_ADDR'] ?? '') . '|' . $pid);
        if (!get_transient($key)) {
            set_transient($key, 1, $wait);
            add_action('shutdown', function () use ($pid) {
                $count = (int) get_post_meta($pid, 'jinyu_views', true);
                update_post_meta($pid, 'jinyu_views', $count + 1);
            });
        }
    }
}