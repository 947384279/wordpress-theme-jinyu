<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap jinyu-setting-wrap">
    <!-- 顶栏：玻璃吸顶操作条。
         品牌标识与页面标题已从这里下放到侧栏顶部与内容卡卡头，顶栏只留「当前位置 + 全局操作」，
         高度 60px 且滚动时常驻，避免长表单「滚到底才能保存」。所有按钮 id 保持不变，
         admin.js 的事件绑定与快捷键（Ctrl+K）无需改动。 -->
    <div class="jinyu-topbar">
        <nav class="jinyu-crumb" aria-label="<?php esc_attr_e('当前位置', 'jinyu'); ?>">
            <span class="jinyu-crumb-root"><span class="jinyu-crumb-logo" aria-hidden="true"></span><?php esc_html_e('金玉设置', 'jinyu'); ?></span>
            <span class="jinyu-crumb-sep" aria-hidden="true">/</span>
            <!-- 当前分组名由 admin.js 在切换分组时写入 -->
            <b class="jinyu-crumb-cur" id="jinyu-crumb-cur"><?php echo esc_html( get_admin_page_title() ); ?></b>
            <span class="jinyu-ver-chip">v<?php echo esc_html( JINYU_CUR_VER ); ?></span>
            <span class="jinyu-ver-req"><?php esc_html_e( 'PHP 8.0+ · WordPress 6.0+', 'jinyu' ); ?></span>
            <button type="button" class="jinyu-btn jinyu-btn-sm jinyu-btn-ghost jinyu-crumb-update" id="jinyu-check-update" data-check-update><?php esc_html_e( '检查更新', 'jinyu' ); ?></button>
        </nav>

        <div class="jinyu-topbar-actions">
            <?php
            /* 报警图标位：与「恢复默认 / 保存更改」同处一个操作区。
               放在顶栏里而不是页顶提醒条，既占不到纵向空间，点击也直接落到本页
               的「维护工具」面板，不需要二次跳转。无隐患时这里什么都不输出。 */
            do_action( 'jinyu_setting_topbar_actions' );
            ?>
            <span id="jinyu-saved-time" class="jinyu-saved-time"></span>
            <div class="jinyu-search-wrap">
                <input type="search" id="jinyu-search" class="jinyu-search" placeholder="<?php esc_attr_e( '搜索设置…', 'jinyu' ); ?>">
                <button type="button" id="jinyu-search-clear" class="jinyu-search-clear" aria-label="<?php esc_attr_e( '清除搜索', 'jinyu' ); ?>" hidden>
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
                <kbd class="jinyu-search-kbd" aria-hidden="true">Ctrl + K</kbd>
            </div>
            <!-- 「恢复默认」保留纯图标（低频次操作，图标比文字更克制）；图标提示由
                 .jinyu-icon-btn 的 CSS 浮层渲染，不引 JS、也不与原生 title 重复。 -->
            <button type="button" id="jinyu-reset" class="jinyu-icon-btn jinyu-icon-btn--ghost" data-tip="<?php esc_attr_e( '恢复默认', 'jinyu' ); ?>" aria-label="<?php esc_attr_e( '恢复默认', 'jinyu' ); ?>">
                <svg class="jinyu-ico-svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4V1L8 5l4 4V6a6 6 0 1 1-6 6H4a8 8 0 1 0 8-8z"/></svg>
            </button>
            <!-- 保存是主操作：用文字主按钮而非图标，避免「猜谜图标」且窄屏不易误触 -->
            <button type="button" id="jinyu-save" class="jinyu-btn jinyu-btn-primary jinyu-save-btn" aria-label="<?php esc_attr_e( '保存更改', 'jinyu' ); ?>">
                <svg class="jinyu-save-ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M17 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V7l-4-4zM7 5h6v5H7V5zm10 14H7v-6h10v6z"/></svg>
                <span><?php esc_html_e( '保存更改', 'jinyu' ); ?></span>
            </button>
        </div>
    </div>

    <div id="jinyu-setting-app">
        <!-- 设置分组（含「维护工具」面板）由 admin.js 渲染 -->
    </div>

    <div class="jinyu-dirtybar" id="jinyu-dirtybar" role="status" aria-live="polite" hidden>
        <span class="jinyu-dirty-dot" aria-hidden="true"></span>
        <span class="jinyu-dirty-text">
            <?php esc_html_e( '有未保存的更改', 'jinyu' ); ?>
            <span class="jinyu-dirty-count" hidden></span>
        </span>
        <span class="jinyu-dirty-actions">
            <button type="button" id="jinyu-discard" class="jinyu-dirty-btn jinyu-dirty-btn--ghost" aria-label="<?php esc_attr_e( '放弃更改', 'jinyu' ); ?>">
                <span class="dashicons dashicons-undo" aria-hidden="true"></span>
                <span class="jinyu-dirty-btn-label" aria-hidden="true"><?php esc_html_e( '放弃更改', 'jinyu' ); ?></span>
            </button>
            <button type="button" id="jinyu-save-float" class="jinyu-dirty-btn jinyu-dirty-btn--primary" aria-label="<?php esc_attr_e( '保存', 'jinyu' ); ?>">
                <span class="dashicons dashicons-saved" aria-hidden="true"></span>
                <span class="jinyu-dirty-btn-label" aria-hidden="true"><?php esc_html_e( '保存', 'jinyu' ); ?></span>
            </button>
        </span>
    </div>
</div>
<?php
// 敏感字段入库为密文，渲染后台表单前先解密，使密码框显示明文（保存时再加密）
$jinyu_admin_opts = get_option( JINYU_OPT, [] );
if ( is_array( $jinyu_admin_opts ) ) {
	foreach ( jinyu_sensitive_keys() as $jinyu_sk ) {
		if ( isset( $jinyu_admin_opts[ $jinyu_sk ] ) ) {
			$jinyu_admin_opts[ $jinyu_sk ] = jinyu_decrypt( $jinyu_admin_opts[ $jinyu_sk ] );
		}
	}
}
?>
<script>
window.JINYU_SETTING = <?php echo wp_json_encode( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    [
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'jinyu_save_options' ),
        'action'   => 'jinyu_save_options',
        'groups'   => $groups,
        'options'  => $jinyu_admin_opts,
        'cats'     => get_categories( [ 'hide_empty' => false, 'fields' => 'id=>name' ] ) ?: [],
        'version'  => JINYU_CUR_VER,
    ],
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
); ?>;
</script>
