<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// OOP 自动加载（inc/classes 下的 Jinyu\ 命名空间）
require_once __DIR__ . '/../classes/autoload.php';
require_once __DIR__ . '/opt.php';
require_once __DIR__ . '/cache.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/sidebar.php';
require_once __DIR__ . '/post-meta.php';
require_once __DIR__ . '/template-tags.php';
// 解耦说明：本主题为纯呈现层。可选的增强功能（SEO、社交、相关文章、统计、缓存等）
// 由配套插件 jinyu-theme-companion 在启用时提供；主题调用点均加 function_exists() 守卫，
// 未安装该插件时优雅降级，不会出现白屏或致命错误。
require_once __DIR__ . '/hitokoto-data.php';
require_once __DIR__ . '/widget.php';
require_once __DIR__ . '/comment.php';
require_once __DIR__ . '/user-agent-parse.php';
require_once __DIR__ . '/live.php';
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