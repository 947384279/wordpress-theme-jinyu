<?php
/**
 * 金玉·每日一句 语料库加载与供给层。
 *
 * 设计原则（面向「主题要发布、他人也能用」）：
 *  - 默认完全本地：从 inc/data/quotes.php 读取主题包，离线可用、零外部依赖、隐私安全。
 *  - 可扩展：add_filter('jinyu_hitokoto_packs', ...) 可追加 / 替换语料包（子主题或 mu-plugin）。
 *  - 可选远程：定义常量 JINYU_HITOKOTO_REMOTE（URL，返回相同结构的 JSON）即改为远程拉取，
 *    带 transient 缓存（1 天）且远程失败自动回落本地。默认不启用，绝不写死任何外部域名。
 */

if (!function_exists('jinyu_hitokoto_load_packs')) {
    function jinyu_hitokoto_load_packs() {
        static $packs = null;
        if ($packs !== null) {
            return $packs;
        }

        $local = [];
        $file  = JINYU_ABS_DIR . '/inc/data/quotes.php';
        if (is_readable($file)) {
            $data = include $file;
            if (is_array($data)) {
                $local = $data;
            }
        }

        // 可选远程源（默认关闭）。远程成功则优先，失败回落本地。
        if (defined('JINYU_HITOKOTO_REMOTE') && filter_var(JINYU_HITOKOTO_REMOTE, FILTER_VALIDATE_URL)) {
            $cache_key = 'jinyu_hitokoto_remote';
            $remote    = get_transient($cache_key);
            if ($remote === false) {
                $resp = wp_remote_get(JINYU_HITOKOTO_REMOTE, ['timeout' => 5, 'ssl_verify' => true]);
                if (!is_wp_error($resp) && wp_remote_retrieve_response_code($resp) === 200) {
                    $body = json_decode(wp_remote_retrieve_body($resp), true);
                    if (is_array($body)) {
                        $remote = $body;
                        set_transient($cache_key, $remote, DAY_IN_SECONDS);
                    }
                }
            }
            $packs = is_array($remote) ? $remote : $local;
        } else {
            $packs = $local;
        }

        // 允许开发者覆写语料包
        $packs = apply_filters('jinyu_hitokoto_packs', $packs);
        return $packs;
    }
}

if (!function_exists('jinyu_hitokoto_pool')) {
    /**
     * 取出指定分类的语录池（扁平为 [内容, 出处] 数组）。
     * @param string $category 'all' 或某个分类键
     * @return array<int,array{0:string,1:string}>
     */
    function jinyu_hitokoto_pool($category = 'all') {
        $packs = jinyu_hitokoto_load_packs();

        if ($category !== 'all' && isset($packs[$category]) && is_array($packs[$category])) {
            return array_values(array_filter($packs[$category], static function ($r) {
                return is_array($r) && isset($r[0]) && $r[0] !== '';
            }));
        }

        $all = [];
        foreach ($packs as $rows) {
            if (!is_array($rows)) {
                continue;
            }
            foreach ($rows as $r) {
                if (is_array($r) && isset($r[0]) && $r[0] !== '') {
                    $all[] = $r;
                }
            }
        }
        return $all;
    }
}

if (!function_exists('jinyu_hitokoto_categories')) {
    /** 分类下拉选项：键 => 显示名 */
    function jinyu_hitokoto_categories() {
        $packs = jinyu_hitokoto_load_packs();
        $map   = ['all' => __('全部', 'jinyu')];
        foreach ($packs as $key => $rows) {
            $map[$key] = __(jinyu_hitokoto_cat_label($key), 'jinyu');
        }
        return $map;
    }
}

if (!function_exists('jinyu_hitokoto_cat_label')) {
    /** 分类键 → 中文标签（远程/自定义键未知时回退原键） */
    function jinyu_hitokoto_cat_label($key) {
        $labels = [
            'motivational' => '励志奋斗',
            'reading'      => '读书求知',
            'self'         => '修身律己',
            'optimism'     => '豁达乐观',
            'people'       => '处世为人',
            'nature'       => '自然诗意',
        ];
        return $labels[$key] ?? $key;
    }
}
