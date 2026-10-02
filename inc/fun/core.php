<?php
/**
 * 主题功能：core.php
 *
 * @package         WordPress
 * @subpackage      Jinyu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// OOP 自动加载（inc/classes 下的 Jinyu\ 命名空间）.
require_once __DIR__ . '/../classes/autoload.php';
require_once __DIR__ . '/extension.php';
require_once __DIR__ . '/opt.php';
require_once __DIR__ . '/cache.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/sidebar.php';
require_once __DIR__ . '/post-meta.php';
require_once __DIR__ . '/template-tags.php';
// 解耦说明：本主题为纯呈现层。能力扩展一律走 extension.php 的插槽（jinyu_ext_* → apply_filters），
// 由配套插件 / 私有插件 / mu-plugin 应答；主题不探测外部函数是否存在，也不认识外部任何符号。
require_once __DIR__ . '/hitokoto-data.php';
require_once __DIR__ . '/widget.php';
require_once __DIR__ . '/related.php';
require_once __DIR__ . '/comment.php';
require_once __DIR__ . '/user-agent-parse.php';
// live.php 含「把访客 IP 发往第三方」的 IP 归属地查询，w.org 发行变体整体剔除该文件，
// 故此处必须条件加载（否则 require 会 fatal）。相关函数调用点均有 function_exists 守卫。
if ( ! jinyu_is_wporg() ) {
	require_once __DIR__ . '/live.php';
}
require_once __DIR__ . '/comment-ajax.php';
require_once __DIR__ . '/user.php';
require_once __DIR__ . '/media.php';
require_once get_template_directory() . '/inc/ajax/index.php';
require_once get_template_directory() . '/inc/ajax/user.php';
require_once __DIR__ . '/dynamic-style.php';
require_once __DIR__ . '/carousel.php';
require_once __DIR__ . '/optimize.php';
require_once __DIR__ . '/typography.php';
require_once __DIR__ . '/nonce.php';
require_once __DIR__ . '/meta-fields.php';
require_once __DIR__ . '/misc.php';
// 后台配置页的 JS 词条表（被 functions.php 的 admin_enqueue 调用 jinyu_admin_l10n()）。
require_once __DIR__ . '/admin-i18n.php';
