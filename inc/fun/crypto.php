<?php
/**
 * 敏感字段加密（纯呈现层的「需求广播方」）
 *
 * 主题不实现任何加密算法，只声明需求 + 提供最保守的降级值（原样返回明文）：
 * 真实加密实现由配套插件 jinyu-theme-companion（inc/fun/crypto.php）经
 * jinyu_encrypt / jinyu_decrypt 两个过滤器提供，算法同源、可透明解密历史密文。
 *
 * 契约方向：主题 apply_filters 广播 → 实现方 add_filter 响应，双方互不引用对方符号。
 * 插件缺席时敏感字段以明文入库（与原先「插件缺失」降级一致，不致命，但安全性降低——
 * 故文档与后台设置页应提示「安装配套插件以启用敏感字段加密」）。
 *
 * 敏感字段清单（jinyu_sensitive_keys）为静态配置，仍由主题提供（属呈现层的表单定义）。
 *
 * @package         WordPress
 * @subpackage      Jinyu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'jinyu_sensitive_keys' ) ) {
	/**
	 * 需要入库即加密的字段。SMTP 授权码与对象存储 Secret 已随「解耦三件套」迁入
	 * 配套插件 jinyu-theme-companion（各自独立 option + 自有加密），主题不再持有对应配置项。
	 *
	 * @return array
	 */
	function jinyu_sensitive_keys(): array {
		return [ 'ai_api_key' ];
	}
}

if ( ! function_exists( 'jinyu_encrypt' ) ) {
	/**
	 * 加密。默认原样返回；实现方可经 jinyu_encrypt 过滤器返回密文。
	 *
	 * @param mixed $plain 明文
	 * @return mixed
	 */
	function jinyu_encrypt( $plain ) {
		return apply_filters( 'jinyu_encrypt', $plain );
	}
}

if ( ! function_exists( 'jinyu_decrypt' ) ) {
	/**
	 * 解密。默认原样返回；实现方可经 jinyu_decrypt 过滤器返回明文。
	 *
	 * @param mixed $val 密文
	 * @return mixed
	 */
	function jinyu_decrypt( $val ) {
		return apply_filters( 'jinyu_decrypt', $val );
	}
}

if ( ! function_exists( 'jinyu_is_encrypted' ) ) {
	/**
	 * 判断一个值是否为密文（覆盖两代格式前缀）。
	 *
	 * 前缀是数据契约的一部分，会随加密实现方演进：
	 *   - jinyu_enc2:: 当前格式（encrypt-then-MAC）
	 *   - jinyu_enc::  历史格式（无 MAC），仍需识别以免旧密文被二次加密
	 * 因此这里集中判定，调用方不得再各自硬编码单一前缀。
	 *
	 * @param mixed $val 待判断的值。
	 * @return bool
	 */
	function jinyu_is_encrypted( $val ): bool {
		if ( ! is_string( $val ) ) {
			return false;
		}
		return str_starts_with( $val, 'jinyu_enc2::' ) || str_starts_with( $val, 'jinyu_enc::' );
	}
}
