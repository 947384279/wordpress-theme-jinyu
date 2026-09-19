<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 第三方整页缓存兼容性检测。
 *
 * 主题自带整页缓存（inc/fun/page-cache.php）与 WP Super Cache / WP Rocket 等
 * 「整页 HTML 缓存」插件是同一层能力。两者并存会：失效逻辑各自独立不同步
 * （一边清了一边没清 → 访客看到陈旧页）、ob_start 嵌套行为不可预测。
 *
 * 更关键的是：第三方整页缓存按自身 TTL 固化 HTML，不认识主题的 wp_nonce_tick()
 * 机制，会把过期的 jinyu_front nonce 发给访客，导致前端 AJAX 失效。
 *
 * 因此检测到已知整页缓存插件时，主题内建缓存自动「让位」（serve/capture 早退），
 * 由插件独占 HTML 缓存层；主题侧通过 nonce 懒加载（inc/fun/nonce.php）保证
 * 即便 HTML 被任意长 TTL 缓存，前端 nonce 依然新鲜。
 */

if ( ! function_exists( 'jinyu_has_external_page_cache' ) ) {
	/**
	 * 是否启用了已知的整页 HTML 缓存插件。
	 *
	 * @return bool
	 */
	function jinyu_has_external_page_cache(): bool {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$known = [
			'wp-super-cache/wp-cache.php',
			'w3-total-cache/w3-total-cache.php',
			'wp-rocket/wp-rocket.php',
			'cache-enabler/cache-enabler.php',
			'comet-cache/comet-cache.php',
			'comet-cache-pro/comet-cache-pro.php',
			'nitropack/nitropack.php',
			'wp-fastest-cache/wpFastestCache.php',
			'litespeed-cache/litespeed-cache.php',
			'hyper-cache/plugin.php',
			'swift-performance-lite/plugin.php',
			'swift-performance/performance.php',
		];

		foreach ( $known as $plugin ) {
			if ( is_plugin_active( $plugin ) ) {
				return true;
			}
		}
		return false;
	}
}

// 后台提示：检测到第三方整页缓存时，告知管理员主题已自动让位（不报错，仅信息）。
add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( ! jinyu_has_external_page_cache() ) {
		return;
	}
	if ( ! jinyu_is_checked( 'page_cache_enable' ) ) {
		return;
	}
	echo '<div class="notice notice-info is-dismissible"><p>'
		. esc_html__( '检测到第三方整页缓存插件：金玉主题的内建整页缓存已自动让位，避免两层缓存冲突与内容不同步。前端 AJAX（点赞 / 评论 / AI 对话 / 登录）通过 nonce 懒加载保持正常，无需额外操作。', JINYU )
		. '</p></div>';
} );
