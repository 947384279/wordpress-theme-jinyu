<?php
/**
 * 设置分组清单（单一真源）
 *
 * 独立于 Jinyu_Setting 类：w.org 发行变体不加载 Jinyu_Setting.php，
 * 但 Customizer 适配层仍需要这份清单，故抽为独立函数供两边共用。
 * 顺序即后台导航顺序。
 *
 * @package         WordPress
 * @subpackage      Jinyu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 所有设置分组类名（不含命名空间前缀）。
 *
 * @return string[]
 */
function jinyu_option_classes(): array {
	return [ 'Jinyu_OptionBasic', 'Jinyu_OptionGlobal', 'Jinyu_OptionStyle', 'Jinyu_OptionContent', 'Jinyu_OptionComment', 'Jinyu_OptionCarousel', 'Jinyu_OptionExtend', 'Jinyu_OptionUser', 'Jinyu_OptionFooter', 'Jinyu_OptionCode' ];
}
