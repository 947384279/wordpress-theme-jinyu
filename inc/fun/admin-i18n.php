<?php
/**
 * 后台配置页（admin.js）界面文案词条表。
 *
 * 为什么要独立成文件：这一整页是 admin.js 动态拼装的，PHP 侧走不到 __()，
 * 必须靠 wp_localize_script 单独注入。而词条表是**数据**不是逻辑，放进 functions.php
 * 会把那个文件撑破 700 行的护栏（解耦契约测试卡这个）。
 *
 * 取词约定见 assets/js/admin.js 顶部的 t() / tf()：
 *   t('key', 'English fallback') —— 缺译文时回落英文原文，不会渲染出 undefined。
 *
 * @package         WordPress
 * @subpackage      Jinyu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'jinyu_admin_l10n' ) ) {
	/**
	 * 输出后台配置页的 JS 词条表（挂在 jinyu-admin 脚本上）。
	 *
	 * @return void
	 */
	function jinyu_admin_l10n(): void {
		wp_localize_script( 'jinyu-admin', 'JINYU_ADMIN_I18N', jinyu_admin_l10n_strings() );
	}
}

if ( ! function_exists( 'jinyu_admin_l10n_strings' ) ) {
	/**
	 * 后台配置页词条表。
	 *
	 * @return array<string,string> 词条键 => 译文。
	 */
	function jinyu_admin_l10n_strings(): array {
		return array(
			'brandTitle'           => __( 'Jinyu Theme Settings', 'jinyu' ),
			'settingsGroups'       => __( 'Settings groups', 'jinyu' ),
			'collapseSidebar'      => __( 'Collapse / expand sidebar', 'jinyu' ),
			// 深色模式切换按钮（admin.js wireThemeToggle 的 tooltip / aria-label）.
			'toggleDarkMode'       => __( 'Toggle dark mode', 'jinyu' ),
			'toggleLightMode'      => __( 'Toggle light mode', 'jinyu' ),
			'noMatching'           => __( 'No matching settings. Try a different keyword', 'jinyu' ),
			// 动态列表（四宫格等）
			'addItem'              => __( 'Add item', 'jinyu' ),
			'upToItems'            => /* translators: %d: 动态列表最多可添加的项数 */ __( 'Up to %d items', 'jinyu' ),
			'item'                 => /* translators: %d: 项的序号（从 1 开始） */ __( 'Item %d', 'jinyu' ),
			'dragToSort'           => __( 'Drag to sort', 'jinyu' ),
			'moveUp'               => __( 'Move up', 'jinyu' ),
			'moveDown'             => __( 'Move down', 'jinyu' ),
			'copyItem'             => __( 'Copy', 'jinyu' ),
			'deleteItem'           => __( 'Delete', 'jinyu' ),
			'removeChip'           => __( 'Remove', 'jinyu' ),
			// 多选控件
			'selectedItems'        => /* translators: %d: 已选中的项数 */ __( 'Selected: %d items', 'jinyu' ),
			'selectAll'            => __( 'Select all', 'jinyu' ),
			'deselectAll'          => __( 'Deselect all', 'jinyu' ),
			'noOptions'            => __( 'No options available', 'jinyu' ),
			'selectPlaceholder'    => __( 'Select…', 'jinyu' ),
			'searchPlaceholder'    => __( 'Search…', 'jinyu' ),
			// 上传控件
			'clickToSelect'        => __( 'Click the button on the right to select, or enter a URL', 'jinyu' ),
			'clickToSelectSm'      => __( 'Click to select or enter a URL', 'jinyu' ),
			'selectBtn'            => __( 'Select', 'jinyu' ),
			'selectImage'          => __( 'Select image', 'jinyu' ),
			// 分组操作
			'resetGroup'           => __( 'Reset this group', 'jinyu' ),
			'resetGroupTitle'      => __( 'Reset only this group to defaults', 'jinyu' ),
			/* translators: %s: 分组显示名 */
			'resetGroupConfirm'    => /* translators: %s: 分组的显示名 */ __( 'Reset the "%s" group to defaults? Customized settings in this group will be cleared.', 'jinyu' ),
			'resetAllConfirm'      => __( 'Restore all options to defaults? This action cannot be undone.', 'jinyu' ),
			'resetPrefix'          => __( 'Reset the "', 'jinyu' ),
			// 通用状态
			'saveFailed'           => __( 'Save failed', 'jinyu' ),
			'savedAt'              => /* translators: %s: 保存时间 */ __( 'Saved at %s', 'jinyu' ),
			'savedSuccessfully'    => __( 'Saved successfully', 'jinyu' ),
			'done'                 => __( 'Done', 'jinyu' ),
			'networkError'         => __( 'Network error', 'jinyu' ),
			/* translators: %s: 错误信息 */
			'networkErrorWith'     => /* translators: %s: 底层错误信息 */ __( 'Network error: %s', 'jinyu' ),
			/* translators: %s: 错误信息 */
			'requestFailed'        => /* translators: %s: 底层错误信息 */ __( 'Request failed: %s', 'jinyu' ),
			/* translators: %s: HTTP 状态码 */
			'apiHttpError'         => /* translators: %s: HTTP 状态码 */ __( 'API request failed (HTTP %s). Try refreshing the page; if it keeps happening, log in again', 'jinyu' ),
			/* translators: %s: HTTP 状态码 */
			'apiParseError'        => /* translators: %s: HTTP 状态码 */ __( 'Could not parse the API response (HTTP %s)', 'jinyu' ),
			// 维护工具面板
			'exporting'            => __( 'Exporting…', 'jinyu' ),
			'exported'             => __( 'Exported', 'jinyu' ),
			'exportFailed'         => __( 'Export failed', 'jinyu' ),
			'importing'            => __( 'Importing…', 'jinyu' ),
			'importedSuccessfully' => __( 'Imported successfully', 'jinyu' ),
			'importFailed'         => __( 'Import failed', 'jinyu' ),
			'jsonParseFailed'      => __( 'JSON parse failed', 'jinyu' ),
			'saving'               => __( 'Saving…', 'jinyu' ),
			'stopping'             => __( 'Stopping…', 'jinyu' ),
			'processing'           => __( 'Processing…', 'jinyu' ),
			'success'              => __( 'Success', 'jinyu' ),
			'failed'               => __( 'Failed', 'jinyu' ),
			'stopped'              => __( 'Stopped', 'jinyu' ),
			'regenerating'         => __( 'Regenerating cover thumbnails…', 'jinyu' ),
			'rebuildComplete'      => __( 'Rebuild complete', 'jinyu' ),
			'thumbBusyResume'      => __( 'A task is already running; it will resume automatically…', 'jinyu' ),
			/* translators: %d: 已处理数量 */
			'processedXofY'        => /* translators: %1$s: 已处理数量 %2$s: 总数量 */ __( 'Processed %1$s / %2$s images', 'jinyu' ),
			/* translators: %d: 生成数量 */
			'generatedCount'       => /* translators: %s: 已生成缩略图的数量 */ __( 'Generated %s images', 'jinyu' ),
			/* translators: %d: 跳过数量 */
			'skippedCount'         => /* translators: %s: 跳过的图片数量（已存在该尺寸） */ __( 'Skipped %s images', 'jinyu' ),
			/* translators: %d: 失败数量 */
			'failedCount'          => /* translators: %s: 处理失败的图片数量 */ __( 'Failed %s images', 'jinyu' ),
			'close'                => __( 'Close', 'jinyu' ),
			// 维护工具面板（toolsHtml / thumbsToolRow）
			'regenThumbs'          => __( 'Regenerate cover thumbnails', 'jinyu' ),
			'regenThumbsDesc'      => __( 'Generate the theme image sizes jinyu-cover (768×512) and jinyu-thumb (400×267) for previously uploaded covers, so cards load small images instead of originals. Only images missing these sizes are processed, in batches until complete.', 'jinyu' ),
			'startRebuild'         => __( 'Start rebuild', 'jinyu' ),
			'preparing'            => __( 'Preparing…', 'jinyu' ),
			'stop'                 => __( 'Stop', 'jinyu' ),
			'exportSettings'       => __( 'Export settings', 'jinyu' ),
			'exportSettingsDesc'   => __( 'Export all theme settings to a JSON file for backup and multi-site migration.', 'jinyu' ),
			'exportJson'           => __( 'Export JSON', 'jinyu' ),
			'importSettings'       => __( 'Import settings', 'jinyu' ),
			'importSettingsDesc'   => __( 'Restore theme settings from a JSON file. This overwrites all current settings — export a backup first.', 'jinyu' ),
			'chooseFileImport'     => __( 'Choose a file and import', 'jinyu' ),
			'runinfoDesc'          => __( 'Output server-side run info in the footer (queries / page generation time). On a full-page cache hit both show 0. After enabling, clear the cache once in "Jinyu Booster" for it to take effect.', 'jinyu' ),
			'enabled'              => __( 'Enabled', 'jinyu' ),
			'disabled'             => __( 'Disabled', 'jinyu' ),
			// —— 以下为 2026-10-02 复审补漏（审计发现词条已存在但代码没接 / 或压根没词条）——
			'clearColor'           => __( 'Clear', 'jinyu' ),
			'removeFile'           => __( 'Remove', 'jinyu' ),
			'pwdKeepUnchanged'     => __( 'Set. Leave empty to keep unchanged', 'jinyu' ),
			'showHide'             => __( 'Show/hide', 'jinyu' ),
			'copySuffix'           => __( ' copy', 'jinyu' ),
			'items'                => __( 'items', 'jinyu' ),
		);
	}
}
