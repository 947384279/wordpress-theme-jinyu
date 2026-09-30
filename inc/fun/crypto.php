<?php
/**
 * 敏感字段加密（兼容壳）
 *
 * 真实加密实现已迁至配套插件 jinyu-theme-companion（inc/fun/crypto.php，
 * 函数 jinyu_companion_encrypt / jinyu_companion_decrypt：算法同源、可透明解密历史密文）。
 *
 * 主题自 1.x 起为纯呈现层，此处仅保留委托壳；插件缺失时回落为明文（不致命）。
 * 敏感字段清单（jinyu_sensitive_keys）为静态配置，仍由主题提供。
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
	 * 加密（委托给配套插件；缺失时原样返回）。
	 *
	 * @param mixed $plain 明文
	 * @return mixed
	 */
	function jinyu_encrypt( $plain ) {
		return function_exists( 'jinyu_companion_encrypt' ) ? jinyu_companion_encrypt( $plain ) : $plain;
	}
}

if ( ! function_exists( 'jinyu_decrypt' ) ) {
	/**
	 * 解密（委托给配套插件；缺失时原样返回）。
	 *
	 * @param mixed $val 密文
	 * @return mixed
	 */
	function jinyu_decrypt( $val ) {
		return function_exists( 'jinyu_companion_decrypt' ) ? jinyu_companion_decrypt( $val ) : $val;
	}
}
