<?php
/**
 * 主题设置菜单与模块加载入口
 *
 * @package         WordPress
 * @subpackage      Jinyu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 分组清单独立于 Jinyu_Setting 类（w.org 变体不加载该类，但 Customizer 仍需此清单）。
require_once __DIR__ . '/option-classes.php';

// 自托管版：加载独立设置页（豪华后台配置）。w.org 发行变体（JINYU_WPORG）跳过，仅保留 Customizer 入口。
// 仅后台加载：前台实时预览（is_customize_preview）只需 Customizer 注册，不需要后台设置页。
if ( is_admin() && ! jinyu_is_wporg() ) {
	require_once __DIR__ . '/Jinyu_Setting.php';
	new Jinyu_Setting();
}

// 把主题选项也注册进 Customizer（w.org 合规入口；自托管版保留独立设置页，双入口并存）。
require_once __DIR__ . '/customizer.php';
