<?php
/**
 * 站内搜索表单（被 get_search_form() 调用，符合 WP 标准）
 *
 * @package         WordPress
 * @subpackage      Jinyu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<form method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>" class="jinyu-search-form" role="search">
	<label class="screen-reader-text" for="jinyu-search-input"><?php esc_html_e( 'Search', 'jinyu' ); ?></label>
	<i class="fa-solid fa-magnifying-glass jinyu-search-input-icon" aria-hidden="true"></i>
	<input type="search" id="jinyu-search-input" name="s"
			placeholder="<?php esc_attr_e( 'Type keywords to search...', 'jinyu' ); ?>"
			value="<?php echo esc_attr( get_search_query() ); ?>">
	<button type="submit" aria-label="<?php esc_attr_e( 'Search', 'jinyu' ); ?>">
		<span class="jinyu-search-btn-text"><?php esc_html_e( 'Search', 'jinyu' ); ?></span>
		<i class="fa-solid fa-magnifying-glass jinyu-search-btn-icon" aria-hidden="true"></i>
	</button>
</form>
