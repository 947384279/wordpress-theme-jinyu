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

require_once __DIR__ . '/Jinyu_Setting.php';
new Jinyu_Setting();
