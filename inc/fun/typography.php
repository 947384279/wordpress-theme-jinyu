<?php
/**
 * 正文字号与排版设置
 *
 * @package         WordPress
 * @subpackage      Jinyu
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 中文自动排版（PHP 级）
 * 当开启「中英文自动加空格」时，在中文(汉字)与拉丁字母/数字边界插入空格，
 * 提升中英文混排可读性。保护 HTML 标签与 URL，避免破坏链接/属性。
 */

if ( ! function_exists( 'jinyu_cn_autospace' ) ) {
	/**
	 * Jinyu_cn_autospace()
	 *
	 * @return string 返回值
	 * @param string $content string 参数。
	 */
	function jinyu_cn_autospace( string $content ): string {
		if ( ! jinyu_is_checked( 'cn_autospace' ) || ! is_string( $content ) ) {
			return $content;
		}

		// 抽走受保护块：HTML 标签 + URL，避免破坏属性与链接.
		$store   = [];
		$content = preg_replace_callback(
			'#(<[^>]+>)|(https?://[^\s<]+)#i',
			function ( $m ) use ( &$store ) {
				$store[] = $m[0];
				return "\x00JINYU_PROTECT_" . ( count( $store ) - 1 ) . "\x00";
			},
			$content
		);

		// 中文(汉字) 与 拉丁字母/数字 之间加空格.
		$content = preg_replace( '#(?<=\p{Han})(?=[A-Za-z0-9])#u', ' ', $content );
		$content = preg_replace( '#(?<=[A-Za-z0-9])(?=\p{Han})#u', ' ', $content );

		// 还原受保护块.
		$content = preg_replace_callback(
			'#\x00JINYU_PROTECT_(\d+)\x00#',
			function ( $m ) use ( $store ) {
				return $store[ (int) $m[1] ] ?? '';
			},
			$content
		);

		return $content;
	}
}

add_filter( 'the_content', 'jinyu_cn_autospace', 20 );
add_filter( 'comment_text', 'jinyu_cn_autospace', 20 );

if ( ! function_exists( 'jinyu_is_cn_locale' ) ) {
	/**
	 * Jinyu_is_cn_locale()
	 *
	 * 判断当前站点是否为中文语言环境。
	 * 依据站点语言（locale）：zh_CN / zh_TW / zh_HK / zh_SG 等一律视为中文环境。
	 * 用于决定是否套用中文排版增强（.jinyu-cn 作用域：两端对齐、标点挤压、舒适行距）。
	 * 这些规则对英文正文是有害的（词间拉出空隙），故必须按语言环境开关，而非无条件常开。
	 *
	 * @return bool
	 */
	function jinyu_is_cn_locale(): bool {
		$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
		if ( ! is_string( $locale ) || '' === $locale ) {
			return false;
		}
		$locale = strtolower( str_replace( '_', '-', $locale ) );

		return ( 0 === strpos( $locale, 'zh' ) )
			|| ( 0 === strpos( $locale, 'cmn' ) )   // 中文（ISO 639-3）
			|| ( 0 === strpos( $locale, 'yue' ) )   // 粤语
			|| ( 0 === strpos( $locale, 'nan' ) );  // 闽南语
	}
}
