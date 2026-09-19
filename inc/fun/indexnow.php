<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * IndexNow 主动推送（GEO 发现加速）
 * 文章发布/更新时主动通知 Bing / Yandex / Seznam 等，使其秒级重新抓取，
 * 进而被基于这些索引的 AI 搜索（如 Copilot）快速收录，无需等待爬虫闲逛。
 *
 * 密钥与验证文件均由主题自动管理：
 *   - 密钥首次使用时自动生成并持久化（站点选项 jinyu_indexnow_key）。
 *   - 验证文件端点 /<key>.txt 由重写规则提供，返回密钥明文。
 * 配置开关：主题设置 → SEO →「启用 IndexNow 主动推送」（默认开启）。
 */

// 1) 密钥：首次使用时自动生成并持久化（32 位十六进制）。
add_action( 'init', 'jinyu_indexnow_ensure_key', 5 );
function jinyu_indexnow_ensure_key(): void {
	$key = get_option( 'jinyu_indexnow_key' );
	if ( $key ) {
		return;
	}
	$key = function_exists( 'random_bytes' )
		? bin2hex( random_bytes( 16 ) )
		: wp_generate_password( 32, false, false );
	update_option( 'jinyu_indexnow_key', $key );
}

// 2) 查询变量 + 重写规则：站点根 /<key>.txt 返回密钥明文，供 IndexNow 验证。
add_filter( 'query_vars', 'jinyu_indexnow_query_var' );
function jinyu_indexnow_query_var( array $vars ): array {
	$vars[] = 'jinyu_indexnow_key';
	return $vars;
}

add_action( 'init', 'jinyu_indexnow_rewrite', 6 );
function jinyu_indexnow_rewrite(): void {
	$key = (string) get_option( 'jinyu_indexnow_key' );
	if ( ! $key ) {
		return;
	}
	add_rewrite_rule( '^' . preg_quote( $key, '/' ) . '\.txt$', 'index.php?jinyu_indexnow_key=1', 'top' );
}

// 与 llms 端点同理：阻止 /<key>.txt 被 WP 补尾斜杠 301 到 /<key>.txt/，
// 否则 IndexNow 验证抓取（严格按无斜杠 URL 请求）可能因多跳而失败。
add_filter( 'redirect_canonical', 'jinyu_indexnow_no_trailing_slash', 10, 2 );
function jinyu_indexnow_no_trailing_slash( $redirect_url, $requested_url ) {
	if ( get_query_var( 'jinyu_indexnow_key' ) ) {
		return false;
	}
	return $redirect_url;
}

// 主题已启用时也能立即生效：按版本号做一次重写规则刷新（之后不再重复刷新）。
add_action( 'init', 'jinyu_indexnow_maybe_flush', 20 );
function jinyu_indexnow_maybe_flush(): void {
	if ( get_option( 'jinyu_indexnow_rewrite_ver' ) === '1' ) {
		return;
	}
	flush_rewrite_rules();
	update_option( 'jinyu_indexnow_rewrite_ver', '1' );
}

// 主题启用/切换时刷新重写规则，使验证文件端点生效。
add_action( 'after_switch_theme', 'jinyu_indexnow_flush' );
function jinyu_indexnow_flush(): void {
	flush_rewrite_rules();
}

add_action( 'template_redirect', 'jinyu_indexnow_serve' );
function jinyu_indexnow_serve(): void {
	if ( empty( get_query_var( 'jinyu_indexnow_key' ) ) ) {
		return;
	}
	$key = (string) get_option( 'jinyu_indexnow_key' );
	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'X-Robots-Tag: noindex' );
	echo $key;
	exit;
}

// 3) 发布 / 更新已发布文章时，向 IndexNow 提交该 URL。
add_action( 'save_post', 'jinyu_indexnow_submit', 10, 3 );
function jinyu_indexnow_submit( int $post_id, WP_Post $post, bool $update ): void {
	// 开关未开或无密钥则跳过。
	if ( ! jinyu_is_checked( 'indexnow_enable' ) ) {
		return;
	}
	$key = (string) get_option( 'jinyu_indexnow_key' );
	if ( ! $key ) {
		return;
	}

	// 排除自动保存 / 修订 / 非文章页类型 / 非已发布状态。
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	if ( ! isset( $post->post_type ) || ! in_array( $post->post_type, [ 'post', 'page' ], true ) ) {
		return;
	}
	if ( get_post_status( $post_id ) !== 'publish' ) {
		return;
	}

	$url  = get_permalink( $post_id );
	$host = (string) parse_url( home_url(), PHP_URL_HOST );
	if ( ! $url || ! $host ) {
		return;
	}

	$payload = wp_json_encode( [
		'host'        => $host,
		'key'         => $key,
		'keyLocation' => home_url( '/' . $key . '.txt' ),
		'urlList'     => [ $url ],
	], JSON_UNESCAPED_SLASHES );

	// 非阻塞触发：IndexNow 接口慢/不可达时不阻塞后台发布流程（保存即返回，推送在后台异步完成）。
	wp_remote_post( 'https://api.indexnow.org/indexnow', [
		'headers'   => [ 'Content-Type' => 'application/json; charset=utf-8' ],
		'body'      => $payload,
		'timeout'   => 10,
		'blocking'  => false,
		'sslverify' => true,
	] );
}
