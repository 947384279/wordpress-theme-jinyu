<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * 站内搜索表单（被 get_search_form() 调用，符合 WP 标准）
 */
?>
<form method="get" action="<?php echo esc_url(home_url('/')); ?>" class="jinyu-search-form" role="search">
    <label class="screen-reader-text" for="jinyu-search-input"><?php esc_html_e('搜索', JINYU); ?></label>
    <input type="search" id="jinyu-search-input" name="s"
           placeholder="<?php esc_attr_e('输入关键词搜索...', JINYU); ?>"
           value="<?php echo esc_attr(get_search_query()); ?>">
    <button type="submit"><?php esc_html_e('搜索', JINYU); ?></button>
</form>
