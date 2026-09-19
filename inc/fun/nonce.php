<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 前端 nonce 懒加载端点。
 *
 * 主题自带的整页缓存 / 第三方整页缓存 / CDN 边缘缓存都会把渲染好的 HTML 固化，
 * 而 HTML 内联的 wp_create_nonce('jinyu_front') 会以 12h 为一个 tick 轮换。
 * 若把真实 nonce 写死在缓存页里，跨 tick 后访客拿到的就是过期 nonce，
 * 点赞 / AI 对话 / 评论 / 订阅 / 登录等 AJAX 会被 check_ajax_referer 打回 -1。
 *
 * 解法：HTML 里只放占位 nonce，页面加载后由前端 JS 走本端点拉取「当下有效」的 nonce。
 * 这样无论哪一层缓存、无论 TTL 多长，nonce 永远是新鲜的。端点本身不进任何页面缓存
 * （nopriv + no-cache 头），且 nonce 本就是应下发给客户端的令牌，无泄露风险。
 */
function jinyu_ajax_front_nonce() {
	// 严禁被任何整页缓存命中
	nocache_headers();
	wp_send_json( [ 'nonce' => wp_create_nonce( 'jinyu_front' ) ] );
}
add_action( 'wp_ajax_nopriv_jinyu_nonce', 'jinyu_ajax_front_nonce' );
add_action( 'wp_ajax_jinyu_nonce', 'jinyu_ajax_front_nonce' );
