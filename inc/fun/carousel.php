<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 首页轮播数据提供
 * 手动指定优先；否则取最新且含封面的文章。
 */

if (!function_exists('jinyu_get_carousel_slides')) {
    /**
     * 获取首页轮播幻灯片（委托 OOP 类 Jinyu\Carousel\Jinyu_Carousel 实现）
     */
    function jinyu_get_carousel_slides(): array
    {
        static $cache = null;
        if ($cache !== null) return $cache;
        $cache = (new \Jinyu\Carousel\Jinyu_Carousel())->getSlides();
        return $cache;
    }
}
