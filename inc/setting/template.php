<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap jinyu-setting-wrap">
    <div class="jinyu-topbar">
        <div class="jinyu-brand">
            <span class="dashicons dashicons-admin-customizer"></span>
            <div class="jinyu-brand-text">
                <h1 class="jinyu-brand-title"><?php echo esc_html(get_admin_page_title()); ?></h1>
                <span class="jinyu-brand-sub"><?php esc_html_e('WordPress 7+ · 全功能设置中心', JINYU); ?></span>
            </div>
        </div>
        <div class="jinyu-topbar-actions">
            <span id="jinyu-saved-time" class="jinyu-saved-time"></span>
            <div class="jinyu-search-wrap">
                <input type="search" id="jinyu-search" class="jinyu-search" placeholder="<?php esc_attr_e('搜索设置…', JINYU); ?>">
                <button type="button" id="jinyu-search-clear" class="jinyu-search-clear" aria-label="<?php esc_attr_e('清除搜索', JINYU); ?>" hidden>
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>
            <button type="button" id="jinyu-reset" class="button"><?php esc_html_e('恢复默认', JINYU); ?></button>
            <button type="button" id="jinyu-save" class="button button-primary"><?php esc_html_e('保存设置', JINYU); ?></button>
        </div>
    </div>

    <div id="jinyu-setting-app">
        <!-- 设置分组（含「维护工具」面板）由 admin.js 渲染 -->
    </div>

    <div class="jinyu-dirtybar" id="jinyu-dirtybar" hidden>
        <span class="jinyu-dirty-text"><?php esc_html_e('有未保存的更改', JINYU); ?></span>
        <span class="jinyu-dirty-actions">
            <button type="button" id="jinyu-discard" class="button-link"><?php esc_html_e('放弃更改', JINYU); ?></button>
            <button type="button" id="jinyu-save-float" class="button button-primary"><?php esc_html_e('保存', JINYU); ?></button>
        </span>
    </div>
</div>
<?php
// 敏感字段入库为密文，渲染后台表单前先解密，使密码框显示明文（保存时再加密）
$jinyu_admin_opts = get_option(JINYU_OPT, []);
if (is_array($jinyu_admin_opts)) {
    foreach (jinyu_sensitive_keys() as $jinyu_sk) {
        if (isset($jinyu_admin_opts[$jinyu_sk])) {
            $jinyu_admin_opts[$jinyu_sk] = jinyu_decrypt($jinyu_admin_opts[$jinyu_sk]);
        }
    }
}
?>
<script>
window.JINYU_SETTING = <?php echo wp_json_encode([
    'ajax_url' => admin_url('admin-ajax.php'),
    'nonce'    => wp_create_nonce('jinyu_save_options'),
    'action'   => 'jinyu_save_options',
    'groups'   => $groups,
    'options'  => $jinyu_admin_opts,
    'cats'     => get_categories(['hide_empty' => false, 'fields' => 'id=>name']) ?: [],
    'version'  => JINYU_CUR_VER,
]); ?>;
</script>
