<?php
/**
 * 安全能力（呈现层的兜底实现 + 需求广播）
 *
 * 主题不接入任何外部安全服务，但自带最小可用的兜底，并允许实现方整体接管：
 *   - 客户端真实 IP：默认直取 REMOTE_ADDR（TCP 对端，不可伪造）；
 *     站点确有反向代理时，由实现方通过 jinyu_client_ip 过滤器返回真实 IP。
 *   - 速率限制：主题自带 transient 计数（IP 维度 + 站点总闸），
 *     实现方可通过 jinyu_rate_limit_check 过滤器接管（返回 false 即超限）。
 *
 * 契约方向：主题 apply_filters 广播 → 实现方 add_filter 响应，双方互不引用对方符号。
 * 插件缺席时主题仍有真实防护（限流照常生效），而非「一律放行」。
 *
 * @package         WordPress
 * @subpackage      Jinyu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'jinyu_client_ip' ) ) {
	/**
	 * 客户端 IP。默认 REMOTE_ADDR；实现方可经 jinyu_client_ip 过滤器返回穿透代理后的真实 IP。
	 *
	 * @return string
	 */
	function jinyu_client_ip(): string {
		$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? trim( (string) $_SERVER['REMOTE_ADDR'] ) : ''; /* phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REMOTE_ADDR 由 Web 服务器写入，非用户输入 */
		$ip     = (string) apply_filters( 'jinyu_client_ip', '' !== $remote ? $remote : '0.0.0.0' );
		return '' !== $ip ? $ip : '0.0.0.0';
	}
}

if ( ! function_exists( 'jinyu_rate_limit_check' ) ) {
	/**
	 * IP 速率限制。
	 *
	 * 主题自带 transient 计数兜底（不依赖任何插件），实现方可经 jinyu_rate_limit_check
	 * 过滤器整体接管（返回 false 即超限），接管后主题的计数不再生效。
	 *
	 * ⚠️ 为什么必须有主题兜底：早前实现是 `apply_filters( ..., true, ... )`——插件缺席时
	 * 恒返回 true，于是登录、找回密码、投票、评论等 7 处调用点的限流全部形同虚设，
	 * 裸装主题即可无限制暴破/轰炸。现在改为「插件可接管，但缺席时主题自己拦」。
	 *
	 * 计数用 transient（对象缓存可用时走内存，天然支持「站点级总闸」）：
	 * 同一 IP 在 $seconds 窗口内请求第 $max+1 次即拒绝。
	 *
	 * @param string $action  动作标识。
	 * @param int    $max     时间窗口内允许的最大请求数。
	 * @param int    $seconds 时间窗口（秒）。
	 * @return bool true=放行，false=已超限。
	 */
	function jinyu_rate_limit_check( string $action, int $max = 10, int $seconds = 60 ): bool {
		$max     = max( 1, $max );
		$seconds = max( 1, $seconds );

		// 站点级总闸（不限 IP）：配合 CDN/WAF 之外的第二道防线，挡住分布式低速暴破。
		if ( ! jinyu_rate_limit_bump( 'site_' . $action, $max * 20, $seconds ) ) {
			return (bool) apply_filters( 'jinyu_rate_limit_check', false, $action, $max, $seconds );
		}

		$ip  = jinyu_client_ip();
		$key = 'rl_' . md5( $action . '|' . $ip );

		if ( ! jinyu_rate_limit_bump( $key, $max, $seconds ) ) {
			return (bool) apply_filters( 'jinyu_rate_limit_check', false, $action, $max, $seconds );
		}

		return (bool) apply_filters( 'jinyu_rate_limit_check', true, $action, $max, $seconds );
	}
}

if ( ! function_exists( 'jinyu_rate_limit_bump' ) ) {
	/**
	 * 自增一个时间窗计数器，判断是否仍在配额内。
	 *
	 * 用法：$ok = jinyu_rate_limit_bump( $key, $max, $seconds );
	 * 返回 false 表示本窗口内已超过 $max 次。
	 *
	 * 并发下 get/set_transient 非原子，极端并发可能少计 1~2 次；作为兜底防线足够，
	 * 需要严格配额的站点由实现方接管过滤器。
	 *
	 * @param string $key     计数器键。
	 * @param int    $max     配额。
	 * @param int    $seconds 窗口长度（秒）。
	 * @return bool true=仍在配额内。
	 */
	function jinyu_rate_limit_bump( string $key, int $max, int $seconds ): bool {
		$t     = get_transient( $key );
		$count = is_numeric( $t ) ? (int) $t + 1 : 1;
		set_transient( $key, $count, $seconds );

		return $count <= $max;
	}
}
