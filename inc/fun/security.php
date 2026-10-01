<?php
/**
 * 安全加固（兼容壳）
 *
 * 实际的安全逻辑（XML-RPC / REST API 关闭、版本号隐藏、登录防暴破、客户端真实 IP 获取、
 * 通用 IP 速率限制）已迁至配套插件 jinyu-theme-companion（inc/fun/theme-compat.php）。
 *
 * 主题自 1.x 起为纯呈现层，此处仅保留委托壳：插件启用时转发到 jinyu_companion_* 真实实现；
 * 插件缺失时回落为「不锁定 / 直取 REMOTE_ADDR」，确保 AJAX 等调用点优雅降级、不致命。
 *
 * @package         WordPress
 * @subpackage      Jinyu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'jinyu_client_ip' ) ) {
	/**
	 * 客户端真实 IP（委托给配套插件；缺失时直取 REMOTE_ADDR）。
	 *
	 * @return string
	 */
	function jinyu_client_ip(): string {
		return function_exists( 'jinyu_companion_client_ip' )
			? jinyu_companion_client_ip()
			: ( isset( $_SERVER['REMOTE_ADDR'] ) ? trim( (string) $_SERVER['REMOTE_ADDR'] ) : '0.0.0.0' ); /* phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- 输入在 jinyu_ajax_guard/委托处已 wp_unslash+sanitize，WPCS 追不到 */
	}
}

if ( ! function_exists( 'jinyu_rate_limit_check' ) ) {
	/**
	 * 通用 IP 速率限制（委托给配套插件；缺失时一律放行，不阻断正常请求）。
	 *
	 * @param string $action  动作标识
	 * @param int    $max     时间窗口内允许的最大请求数
	 * @param int    $seconds 时间窗口（秒）
	 * @return bool true=放行，false=已超限
	 */
	function jinyu_rate_limit_check( string $action, int $max = 10, int $seconds = 60 ): bool {
		return function_exists( 'jinyu_companion_rate_limit_check' )
			? jinyu_companion_rate_limit_check( $action, $max, $seconds )
			: true;
	}
}
