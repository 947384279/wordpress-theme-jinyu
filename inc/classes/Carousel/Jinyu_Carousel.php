<?php

namespace Jinyu\Carousel;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 首页轮播数据来源（OOP 实现，供主题过程式代码调用）
 */
class Jinyu_Carousel
{
    /**
     * 获取轮播幻灯片
     * 手动指定优先；否则取最新且含封面的文章。
     *
     * @return array<int,array{title:string,image:string,url:string}>
     */
    public function getSlides(): array
    {
        $cached = jinyu_cache_get('carousel');
        if (is_array($cached)) {
            return $cached;
        }

        $slides = [];
        $manual = trim((string) jinyu_get_option('home_carousel_manual', ''));
        if ($manual !== '') {
            foreach (explode("\n", $manual) as $line) {
                $line = trim($line);
                if (!$line) continue;
                $parts = array_map('trim', explode('|', $line, 3));
                if (count($parts) < 2) continue;
                $slides[] = [
                    'title' => $parts[0],
                    'image' => jinyu_img_to_webp_url($parts[1]),
                    'url'   => $parts[2] ?? '',
                    'srcset' => jinyu_get_url_srcset($parts[1]),
                ];
            }
        }

        // 仅在未配置手动轮播时走自动查询；结果缓存 10 分钟，避免每次请求重跑。
        if (empty($slides)) {
            $count = max(1, (int) jinyu_get_option('home_carousel_count', 5));
            $query = new \WP_Query([
                'posts_per_page'      => $count,
                'meta_key'            => '_thumbnail_id',
                'ignore_sticky_posts' => true,
                'no_found_rows'       => true,
                'post_type'           => 'post',
            ]);

            if ($query->have_posts()) {
                foreach ($query->posts as $p) {
                    $slides[] = [
                        'title' => get_the_title($p),
                        'image' => jinyu_get_post_cover($p->ID, 'large'),
                        'url'   => get_permalink($p),
                        'srcset' => jinyu_get_post_cover_srcset($p->ID),
                    ];
                }
            }
            \wp_reset_postdata();
        }

        jinyu_cache_set('carousel', $slides, 10 * MINUTE_IN_SECONDS);
        return $slides;
    }
}
