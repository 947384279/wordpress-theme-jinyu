<?php
/**
 * 头像 URL 代理（降级实现）。
 *
 * 背景：1.3.2 计划引入「本地图片代理」以规避防盗链 / referrer 泄漏，并对第三方头像
 * 做本地 WebP 转换。但配套的代理端点尚未实现，本文件在一次不完整部署中被写成空文件，
 * 导致 inc/fun/user.php 调用 jinyu_avatar_proxy_url() 时抛
 * “Call to undefined function” 致命错误 —— 未命中整页缓存的请求全部 500。
 *
 * 当前策略：**安全降级为恒等函数**。不做任何改写，头像 URL 原样输出。
 * 这样既立即消除 500，又不引入未经审计的对外请求端点（代理端点涉及 SSRF 风险，
 * 必须在白名单校验与 SSRF 防护到位后才能启用）。
 *
 * 待代理端点就绪后，把此处替换为真正的改写逻辑即可；调用点无需改动。
 *
 * @package Jinyu
 */

if ( ! function_exists( 'jinyu_avatar_proxy_url' ) ) {
	/**
	 * 头像 URL 代理改写。
	 *
	 * 目前为恒等实现：原样返回入参，不做任何网络请求。
	 *
	 * @param string $url 头像 URL。
	 * @return string 处理后的头像 URL。
	 */
	function jinyu_avatar_proxy_url( string $url ): string {
		return $url;
	}
}
