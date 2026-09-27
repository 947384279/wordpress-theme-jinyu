<?php
/**
 * Jinyu\ 命名空间类自动加载器
 *
 * @package         WordPress
 * @subpackage      Jinyu
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 命名空间自动加载（PSR-4 风格）
 * 命名空间 Jinyu\ 映射到 inc/classes/ 目录。
 */
spl_autoload_register(
	function ( $class ) {
		$prefix = 'Jinyu\\';
		if ( strpos( $class, $prefix ) !== 0 ) {
			return;
		}
		$rel  = substr( $class, strlen( $prefix ) );
		$file = __DIR__ . '/' . str_replace( '\\', '/', $rel ) . '.php';
		if ( is_file( $file ) ) {
			require $file;
		}
	}
);
