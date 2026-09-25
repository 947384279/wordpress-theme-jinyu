<?php
/**
 * 金玉主题 · 区块图案（Block Patterns）
 *
 * 主题根目录 patterns/ 内的图案文件由 WordPress 6.0+ 自动发现并注册，
 * 本文件只负责注册图案分类「jinyu」，让图案在编辑器插入面板中独立成组。
 *
 * @package Jinyu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'init',
	function () {
		if ( ! function_exists( 'register_block_pattern_category' ) ) {
			return;
		}
		register_block_pattern_category(
			'jinyu',
			array(
				'label'       => __( '金玉', 'jinyu' ),
				'description' => __( '金玉主题内置的预设排版图案：图文、特性卡、FAQ、行动号召、精选文章、作者卡。', 'jinyu' ),
			)
		);
	}
);
