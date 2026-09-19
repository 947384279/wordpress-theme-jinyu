<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 后台编辑器短代码快捷入口
 * - 经典编辑器：TinyMCE 菜单 + 文本模式 QuickTags 按钮
 * - 块编辑器（Gutenberg）：右上「⋯」菜单 + 右侧面板，点击插入 core/shortcode 块
 * 让作者在编辑器里直接看到并一键插入 jinyu_ 短代码（对齐 Puock 的后台快捷入口）。
 */

if ( ! function_exists( 'jinyu_sc_editor_list' ) ) {
	function jinyu_sc_editor_list() {
		return [
			[ 'key' => 'tip',           'label' => '提示框',        'tpl' => '[jinyu_tip type="info" title="提示"]内容[/jinyu_tip]' ],
			[ 'key' => 'download',      'label' => '下载按钮',      'tpl' => '[jinyu_download url="" name="文件名" type="free" pwd=""]' ],
			[ 'key' => 'login',         'label' => '登录可见',      'tpl' => '[jinyu_login]隐藏内容[/jinyu_login]' ],
			[ 'key' => 'comment',       'label' => '评论可见',      'tpl' => '[jinyu_comment]隐藏内容[/jinyu_comment]' ],
			[ 'key' => 'login_email',   'label' => '登录+邮箱可见',  'tpl' => '[jinyu_login_email]隐藏内容[/jinyu_login_email]' ],
			[ 'key' => 'hide',          'label' => '仅管理员可见',  'tpl' => '[jinyu_hide]内容[/jinyu_hide]' ],
			[ 'key' => 'password_read', 'label' => '阅读密码',      'tpl' => '[jinyu_password_read pass="123" tip="请输入密码"]内容[/jinyu_password_read]' ],
			[ 'key' => 'github',        'label' => 'GitHub 卡片',   'tpl' => '[jinyu_github user="Licoy" repo="wordpress-theme-puock"]' ],
			[ 'key' => 'gist',          'label' => 'Gist 嵌入',     'tpl' => '[jinyu_gist id=""]' ],
			[ 'key' => 'gallery',       'label' => '图片组',        'tpl' => '[jinyu_gallery ids="1,2,3" cols="3"]' ],
			[ 'key' => 'badge',         'label' => '徽章',          'tpl' => '[jinyu_badge color="primary"]文字[/jinyu_badge]' ],
			[ 'key' => 'progress',      'label' => '进度条',        'tpl' => '[jinyu_progress value="60" color="primary" label="进度"]' ],
			[ 'key' => 'tabs',          'label' => '选项卡',        'tpl' => '[jinyu_tabs][jinyu_tab title="标签1"]内容1[/jinyu_tab][jinyu_tab title="标签2"]内容2[/jinyu_tab][/jinyu_tabs]' ],
			[ 'key' => 'collapse',      'label' => '折叠面板',      'tpl' => '[jinyu_collapse title="点击展开"]内容[/jinyu_collapse]' ],
			[ 'key' => 'pre',           'label' => '代码块',        'tpl' => '[jinyu_pre lang="php" title="示例"]<?php echo "hello"; ?>[/jinyu_pre]' ],
			[ 'key' => 'music',         'label' => '音乐播放',      'tpl' => '[jinyu_music url="" name="" artist="" cover=""]' ],
			[ 'key' => 'video',         'label' => '视频',          'tpl' => '[jinyu_video url="" cover=""]' ],
			[ 'key' => 'btn',           'label' => '按钮',          'tpl' => '[jinyu_btn type="primary" href="https://example.com"]按钮文字[/jinyu_btn]' ],
			[ 'key' => 'oauth',         'label' => '登录入口',      'tpl' => '[jinyu_oauth]' ],
			[ 'key' => 'faq',           'label' => 'FAQ',           'tpl' => '[jinyu_faq][jinyu_faq_item q="问题？"]答案[/jinyu_faq_item][/jinyu_faq]' ],
			[ 'key' => 'howto',         'label' => 'HowTo',         'tpl' => '[jinyu_howto][jinyu_step name="步骤1"]说明[/jinyu_step][/jinyu_howto]' ],
		];
	}
}

// TinyMCE 外部插件（由 assets/dist/js/shortcodes.min.js 提供）
add_filter( 'mce_external_plugins', function ( $plugins ) {
	global $pagenow;
	if ( ! in_array( $pagenow, [ 'post.php', 'post-new.php' ], true ) ) {
		return $plugins;
	}
	$plugins['jinyu_shortcodes'] = JINYU_ABS_URI . '/assets/dist/js/shortcodes.min.js?ver=' . JINYU_CUR_VER;
	return $plugins;
} );

// TinyMCE 工具栏按钮
add_filter( 'mce_buttons', function ( $buttons ) {
	global $pagenow;
	if ( ! in_array( $pagenow, [ 'post.php', 'post-new.php' ], true ) ) {
		return $buttons;
	}
	$buttons[] = 'jinyu_shortcodes';
	return $buttons;
} );

// 在 <head> 注入短代码清单（早于 TinyMCE 初始化，保证菜单有数据）
add_action( 'admin_head', function () {
	global $pagenow;
	if ( ! in_array( $pagenow, [ 'post.php', 'post-new.php' ], true ) ) {
		return;
	}
	$list = jinyu_sc_editor_list();
	echo '<script>window.JINYU_SC=' . wp_json_encode( [ 'list' => $list ] ) . ';</script>';
} );

// 文本模式（QuickTags）按钮
add_action( 'admin_print_footer_scripts', function () {
	global $pagenow;
	if ( ! in_array( $pagenow, [ 'post.php', 'post-new.php' ], true ) ) {
		return;
	}
	$list = jinyu_sc_editor_list();
	?>
	<script>
	(function () {
		if (typeof QTags === 'undefined') return;
		var list = <?php echo wp_json_encode( $list ); ?>;
		list.forEach(function (item) {
			QTags.addButton('jinyu_' + item.key, item.label, item.tpl, '', '', item.label, 300);
		});
	})();
	</script>
	<?php
} );

// 块编辑器（Gutenberg）快捷入口：仅块编辑器加载，注入数据并注册侧栏面板
add_action( 'enqueue_block_editor_assets', function () {
	$ver = JINYU_CUR_VER;
	wp_enqueue_script(
		'jinyu-shortcodes-gb',
		JINYU_ABS_URI . '/assets/dist/js/shortcodes-gb.min.js',
		[ 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-blocks' ],
		$ver,
		true
	);
	// 经 wp_localize_script 注入清单，避免与 admin_head 的 window.JINYU_SC 抢时序
	wp_localize_script(
		'jinyu-shortcodes-gb',
		'JINYU_SC_GB',
		[ 'list' => jinyu_sc_editor_list() ]
	);
} );
