<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 数据库优化（维护工具面板「数据库优化」入口）
 * 仅清理可安全删除的冗余数据，绝不触碰正常文章 / 评论 / 用户。
 * 校验：jinyu_save_options nonce + manage_options 权限。
 */

add_action( 'wp_ajax_jinyu_db_optimize', 'jinyu_db_optimize' );

function jinyu_db_optimize() {
	check_ajax_referer( 'jinyu_save_options', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( __( '权限不足', JINYU ) );
	}

	global $wpdb;

	// 仅允许对当前站点的表做 OPTIMIZE，且限定为 WordPress 核心表前缀，防止越权操作。
	$items = [];

	/* 1. 文章修订版本（revision） */
	$deleted = (int) $wpdb->query(
		$wpdb->prepare(
			"DELETE p, pm FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID WHERE p.post_type = %s",
			'revision'
		)
	);
	$items['revisions'] = $deleted;

	/* 2. 自动草稿（auto-draft）与草稿中无内容残留 */
	$deleted = (int) $wpdb->query(
		$wpdb->prepare(
			"DELETE p, pm FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID WHERE p.post_type = %s AND p.post_status = %s",
			'post',
			'auto-draft'
		)
	);
	$items['auto_drafts'] = $deleted;

	/* 3. 垃圾评论（spam）与回收站评论（trash） */
	$deleted = (int) $wpdb->query(
		$wpdb->prepare(
			"DELETE c, cm FROM {$wpdb->comments} c LEFT JOIN {$wpdb->commentmeta} cm ON cm.comment_id = c.comment_ID WHERE c.comment_approved IN (%s, %s)",
			'spam',
			'trash'
		)
	);
	$items['comments'] = $deleted;

	/* 4. 孤立 postmeta（post_id 已不存在） */
	$items['orphan_postmeta'] = jinyu_db_delete_orphan( $wpdb->postmeta, 'post_id', $wpdb->posts, 'ID' );

	/* 5. 孤立 commentmeta（comment_id 已不存在） */
	$items['orphan_commentmeta'] = jinyu_db_delete_orphan( $wpdb->commentmeta, 'comment_id', $wpdb->comments, 'comment_ID' );

	/* 6. 孤立 termmeta（term_id 已不存在） */
	if ( jinyu_db_table_exists( $wpdb->termmeta ) ) {
		$items['orphan_termmeta'] = jinyu_db_delete_orphan( $wpdb->termmeta, 'term_id', $wpdb->terms, 'term_id' );
	}

	/* 7. 过期 transient（含站点健康临时数据） */
	$items['expired_transients'] = jinyu_db_delete_expired_transients();

	/* 8. 优化数据表（回收空洞、降低碎片） */
	$optimized = jinyu_db_optimize_tables();
	$items['optimized_tables'] = $optimized;

	$total = 0;
	foreach ( $items as $k => $v ) {
		if ( $k === 'optimized_tables' ) {
			continue;
		}
		$total += (int) $v;
	}

	wp_send_json_success( [
		'msg'  => sprintf( __( '数据库优化完成：共清理 %d 条冗余记录，优化 %d 张表。', JINYU ), $total, $optimized ),
		'data' => $items,
	] );
}

/**
 * 删除子表中指向父表已不存在的孤儿行，返回删除行数。
 */
function jinyu_db_delete_orphan( string $child, string $child_fk, string $parent, string $parent_pk ): int {
	global $wpdb;
	// 用 LEFT JOIN ... IS NULL 一次性删除孤儿行（限定两张表同属本站前缀，安全）。
	return (int) $wpdb->query(
		"DELETE c FROM {$child} c LEFT JOIN {$parent} p ON p.{$parent_pk} = c.{$child_fk} WHERE p.{$parent_pk} IS NULL"
	);
}

/**
 * 删除已过期的 transient：_transient_timeout_* 时间已过即视为过期。
 * 过期项对应的 _transient_* 一并清理（避免残留）。
 */
function jinyu_db_delete_expired_transients(): int {
	global $wpdb;
	$now = time();
	// 先删超时标记，再删对应值；用单条语句按超时时间过滤，避免逐条 PHP 循环。
	$count = (int) $wpdb->query(
		$wpdb->prepare(
			"DELETE t, tv FROM {$wpdb->options} t
			 INNER JOIN {$wpdb->options} tv ON tv.option_name = REPLACE(t.option_name, '_transient_timeout_', '_transient_')
			 WHERE t.option_name LIKE %s AND t.option_value < %d",
			$wpdb->esc_like( '_transient_timeout_' ) . '%',
			$now
		)
	);
	// 只删「已过期」瞬态：上面的 INNER JOIN 已覆盖本主题前缀（_transient_timeout_jinyu_*），
	// 故不再额外清理未过期瞬态，避免误删仍在用的整页缓存等有效数据。
	return $count;
}

/**
 * 对所有本站核心表执行 OPTIMIZE TABLE（仅对支持该语句的存储引擎生效，失败静默跳过）。
 */
function jinyu_db_optimize_tables(): int {
	global $wpdb;
	$tables = $wpdb->get_col( "SHOW TABLES LIKE " . $wpdb->prepare( '%s', $wpdb->prefix . '%' ) );
	if ( empty( $tables ) ) {
		return 0;
	}
	$done = 0;
	foreach ( $tables as $table ) {
		// 仅优化属于本站前缀的表，绝不越权操作其它库表。
		if ( strpos( $table, $wpdb->prefix ) !== 0 ) {
			continue;
		}
		$res = $wpdb->query( "OPTIMIZE TABLE " . esc_sql( $table ) );
		if ( $res !== false ) {
			$done++;
		}
	}
	return $done;
}

/**
 * 判断数据表是否存在（跨 MySQL/MariaDB 兼容）。
 */
function jinyu_db_table_exists( string $table ): bool {
	global $wpdb;
	$name = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	return $name === $table;
}
