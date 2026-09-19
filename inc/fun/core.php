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
require_once __DIR__ . '/related.php';
require_once __DIR__ . '/auto-link.php';
require_once __DIR__ . '/widget.php';
require_once __DIR__ . '/comment.php';
require_once __DIR__ . '/user-agent-parse.php';
require_once __DIR__ . '/live.php';
require_once __DIR__ . '/comment-notify.php';
require_once __DIR__ . '/comment-ajax.php';
require_once __DIR__ . '/short-code.php';
require_once __DIR__ . '/shortcode-ui.php';
require_once get_template_directory() . '/inc/seo-jsonld.php';
require_once get_template_directory() . '/inc/link.php';
require_once __DIR__ . '/stats.php';
require_once __DIR__ . '/anti-spam.php';
require_once __DIR__ . '/captcha.php';
require_once __DIR__ . '/user.php';
require_once __DIR__ . '/media.php';
require_once get_template_directory() . '/inc/oauth/index.php';
require_once get_template_directory() . '/inc/ajax/index.php';
require_once get_template_directory() . '/inc/ajax/user.php';
require_once get_template_directory() . '/inc/ajax/ai.php';
require_once get_template_directory() . '/inc/ajax/poster.php';
require_once __DIR__ . '/dynamic-style.php';
require_once __DIR__ . '/carousel.php';
require_once __DIR__ . '/optimize.php';
require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/ad.php';
require_once __DIR__ . '/typography.php';
require_once __DIR__ . '/compat.php';
require_once __DIR__ . '/nonce.php';
require_once __DIR__ . '/page-cache.php';
require_once __DIR__ . '/meta-fields.php';
require_once __DIR__ . '/email.php';
require_once __DIR__ . '/db-optimize.php';
require_once __DIR__ . '/go-link.php';
require_once __DIR__ . '/misc.php';
require_once get_template_directory() . '/inc/seo.php';
require_once __DIR__ . '/category-seo.php';
require_once __DIR__ . '/llms.php';
require_once __DIR__ . '/indexnow.php';
if (jinyu_is_checked('no_category')) {
    require_once __DIR__ . '/no-category.php';
}
require_once __DIR__ . '/../ext/moments.php';
require_once __DIR__ . '/baidu-push.php';
require_once __DIR__ . '/social.php';