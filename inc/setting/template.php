<?php
/**
 * 主题设置页视图渲染
 *
 * @package         WordPress
 * @subpackage      Jinyu
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap jinyu-setting-wrap">
	<?php
	/*
	防白屏：在顶栏绘制前同步应用已存偏好 / 系统配色，避免深色用户每次进入先闪一下浅色。
	与 admin.js 的 wireThemeToggle() 用同一存储键 jinyu_admin_theme、同一判定逻辑，
	仅做「初始 class 注入」，toggle 与持久化仍由 admin.js 接管。 */
	?>
	<script<?php echo jinyu_csp_nonce_attr();  /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- nonce 属性，非动态内容 */ ?>>
	(function () {
		try {
			var k = 'jinyu_admin_theme', s = localStorage.getItem(k);
			var dark = s === 'dark' || (s !== 'light' && window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches);
			if (dark) {
				var w = document.querySelector('.jinyu-setting-wrap');
				if (w) { w.classList.add('is-dark'); }
				document.documentElement.classList.add('is-dark');
			}
		} catch (e) {}
	})();
	</script>
	<!-- 顶栏：玻璃吸顶操作条。
		品牌标识与页面标题已从这里下放到侧栏顶部与内容卡卡头，顶栏只留「当前位置 + 全局操作」，
		高度 60px 且滚动时常驻，避免长表单「滚到底才能保存」。所有按钮 id 保持不变，
		admin.js 的事件绑定与快捷键（Ctrl+K）无需改动。 -->
	<div class="jinyu-topbar">
		<nav class="jinyu-crumb" aria-label="<?php esc_attr_e( 'Current location', 'jinyu' ); ?>">
			<span class="jinyu-crumb-root"><span class="jinyu-crumb-logo" aria-hidden="true"></span><?php esc_html_e( 'Jinyu Theme Settings', 'jinyu' ); ?></span>
			<span class="jinyu-crumb-sep" aria-hidden="true">/</span>
			<!-- 当前分组名由 admin.js 在切换分组时写入 -->
			<b class="jinyu-crumb-cur" id="jinyu-crumb-cur"><?php echo esc_html( get_admin_page_title() ); ?></b>
			<span class="jinyu-ver-chip">v<?php echo esc_html( JINYU_CUR_VER ); ?></span>
		<span class="jinyu-ver-req"><?php esc_html_e( 'PHP 8.0+ · WordPress 6.0+', 'jinyu' ); ?></span>
	</nav>

		<div class="jinyu-topbar-actions">
			<span id="jinyu-saved-time" class="jinyu-saved-time"></span>
			<div class="jinyu-search-wrap">
				<input type="search" id="jinyu-search" class="jinyu-search" placeholder="<?php esc_attr_e( 'Search settings…', 'jinyu' ); ?>">
				<button type="button" id="jinyu-search-clear" class="jinyu-search-clear" aria-label="<?php esc_attr_e( 'Clear search', 'jinyu' ); ?>" hidden>
					<span class="dashicons dashicons-no-alt"></span>
				</button>
				<kbd class="jinyu-search-kbd" aria-hidden="true">Ctrl + K</kbd>
			</div>
		<!-- 「恢复默认」保留纯图标（低频次操作，图标比文字更克制）；图标提示由
			.jinyu-icon-btn 的 CSS 浮层渲染，不引 JS、也不与原生 title 重复。 -->
		<button type="button" id="jinyu-reset" class="jinyu-icon-btn jinyu-icon-btn--ghost" data-tip="<?php esc_attr_e( 'Restore defaults', 'jinyu' ); ?>" aria-label="<?php esc_attr_e( 'Restore defaults', 'jinyu' ); ?>">
			<svg class="jinyu-ico-svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4V1L8 5l4 4V6a6 6 0 1 1-6 6H4a8 8 0 1 0 8-8z"/></svg>
		</button>
		<!-- 移动端深色切换：窄屏时侧栏底部工具条隐藏，把月亮/太阳切钮放到恢复默认右侧。
			桌面端通过 CSS 隐藏，JS 对所有 .jinyu-theme-toggle 统一绑定。 -->
		<button type="button" id="jinyu-theme-toggle-mobile" class="jinyu-icon-btn jinyu-icon-btn--ghost jinyu-theme-toggle jinyu-theme-toggle--mobile" data-tip="<?php esc_attr_e( 'Toggle dark mode', 'jinyu' ); ?>" aria-label="<?php esc_attr_e( 'Toggle dark mode', 'jinyu' ); ?>" aria-pressed="false">
			<svg class="jinyu-ico-svg jinyu-ico-moon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
			<svg class="jinyu-ico-svg jinyu-ico-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4.2"/><path d="M12 2v2.5M12 19.5V22M2 12h2.5M19.5 12H22M4.9 4.9l1.8 1.8M17.3 17.3l1.8 1.8M19.1 4.9l-1.8 1.8M6.7 17.3l-1.8 1.8"/></svg>
		</button>
	</div>
	</div>

	<?php
	/*
	插件告警图标中转位：侧栏头部（原折叠按钮处）由 JS 渲染，PHP action 无法直接
	注入其中。先把 jinyu_setting_topbar_actions 的输出暂存到这个隐藏容器，
	admin.js 在构建侧栏时将其搬进 .jinyu-nav-head-actions。 */
	?>
	<div id="jinyu-plugin-actions-source" hidden>
		<?php do_action( 'jinyu_setting_topbar_actions' ); ?>
	</div>

	<div id="jinyu-setting-app">
		<!-- 设置分组（含「维护工具」面板）由 admin.js 渲染 -->
	</div>

	<div class="jinyu-dirtybar" id="jinyu-dirtybar" role="status" aria-live="polite" hidden>
		<span class="jinyu-dirty-dot" aria-hidden="true"></span>
		<span class="jinyu-dirty-text">
			<?php esc_html_e( 'You have unsaved changes', 'jinyu' ); ?>
			<span class="jinyu-dirty-count" hidden></span>
		</span>
		<span class="jinyu-dirty-actions">
			<button type="button" id="jinyu-discard" class="jinyu-dirty-btn jinyu-dirty-btn--ghost" aria-label="<?php esc_attr_e( 'Discard changes', 'jinyu' ); ?>">
				<span class="dashicons dashicons-undo" aria-hidden="true"></span>
				<span class="jinyu-dirty-btn-label" aria-hidden="true"><?php esc_html_e( 'Discard changes', 'jinyu' ); ?></span>
			</button>
			<button type="button" id="jinyu-save-float" class="jinyu-dirty-btn jinyu-dirty-btn--primary" aria-label="<?php esc_attr_e( 'Save', 'jinyu' ); ?>">
				<span class="dashicons dashicons-saved" aria-hidden="true"></span>
				<span class="jinyu-dirty-btn-label" aria-hidden="true"><?php esc_html_e( 'Save', 'jinyu' ); ?></span>
			</button>
		</span>
	</div>
</div>
<?php
// 敏感字段入库为密文，渲染后台表单前先解密，使密码框显示明文（保存时再加密）.
$jinyu_admin_opts = get_option( JINYU_OPT, [] );
if ( is_array( $jinyu_admin_opts ) ) {
	foreach ( jinyu_sensitive_keys() as $jinyu_sk ) {
		if ( isset( $jinyu_admin_opts[ $jinyu_sk ] ) ) {
			$jinyu_admin_opts[ $jinyu_sk ] = jinyu_decrypt( $jinyu_admin_opts[ $jinyu_sk ] );
		}
	}
}
?>
<script<?php echo jinyu_csp_nonce_attr();  /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- nonce 属性，非动态内容 */ ?>>
window.JINYU_SETTING = 
<?php
echo wp_json_encode( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped.
	[
		'ajax_url' => admin_url( 'admin-ajax.php' ),
		'nonce'    => wp_create_nonce( 'jinyu_save_options' ),
		'action'   => 'jinyu_save_options',
		'groups'   => $groups,
		'options'  => $jinyu_admin_opts,
		'cats'     => get_categories(
			[
				'hide_empty' => false,
				'fields'     => 'id=>name',
			]
		) ?: [],
		'version'  => JINYU_CUR_VER,
	],
	JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
?>
;
</script>
