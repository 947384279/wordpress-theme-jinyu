<?php
/**
 * jinyu-storage-runner.php — 存储推送/拉回的「服务端自愈」运行器。
 *
 * 设计目标：即使浏览器已关闭（用户没开着后台页面），也能靠系统 cron 周期性调用，
 * 把 jinyu_storage_tasks 里 running/pending 的任务继续推进直至 done。
 *
 * 安全：仅允许 CLI 调用（Web 入口由 nginx deny 兜底）。
 *
 * 用法（root crontab，每 2 分钟触发一次）：
 *   (每两分钟) /usr/local/php-8.5/bin/php /home/wwwroot/www.qicaiyun.top/domain/www.qicaiyun.top/web/wp-content/themes/jinyu/jinyu-storage-runner.php >> /tmp/jinyu-storage-runner.log 2>&1
 */

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 403 );
	exit( "forbidden: cli only\n" );
}

// 向上逐级查找 wp-load.php（不依赖固定目录深度，适应 bin/ 或主题根等不同放置位置）
$dir     = __DIR__;
$wp_load = null;
while ( $dir && $dir !== dirname( $dir ) ) {
	if ( is_file( $dir . '/wp-load.php' ) ) {
		$wp_load = $dir . '/wp-load.php';
		break;
	}
	$dir = dirname( $dir );
}
if ( ! $wp_load ) {
	exit( "error: wp-load.php not found\n" );
}
require_once $wp_load;

// 确保处理函数已加载（functions.php 通常会 require inc/fun/storage.php；未加载则补 require）
if ( ! function_exists( 'jinyu_storage_process_one' ) ) {
	$candidates = array(
		dirname( __DIR__ ) . '/inc/fun/storage.php',
		__DIR__ . '/inc/fun/storage.php',
	);
	foreach ( $candidates as $storage ) {
		if ( is_file( $storage ) ) {
			require_once $storage;
			break;
		}
	}
}

if ( ! function_exists( 'jinyu_storage_process_one' ) ) {
	exit( "error: jinyu_storage_process_one not found\n" );
}

// 单实例锁：避免多个 cron 周期 / 与浏览器 AJAX 同时跑造成重复处理
$lock_key = 'jinyu_storage_runner_lock';
$lock     = get_transient( $lock_key );
if ( $lock && (int) $lock > time() - 60 ) {
	echo "[" . date( 'Y-m-d H:i:s' ) . "] skip: another runner is active\n";
	exit( 0 );
}
set_transient( $lock_key, time(), 60 );

$budget = 50; // 单次运行时间预算（秒），到点即退出，等待下一轮 cron
$start  = microtime( true );
$types  = array( 'push', 'pull' );
$ran    = 0;

foreach ( $types as $type ) {
	while ( true ) {
		$row = jinyu_storage_process_one( $type );
		if ( $row === null ) {
			break; // 该类型无活跃任务
		}
		if ( $row === false ) {
			echo "[" . date( 'Y-m-d H:i:s' ) . "] [$type] error\n";
			break;
		}
		$ran++;
		echo "[" . date( 'Y-m-d H:i:s' ) . "] [$type] " . $row['message'] . " (status=" . $row['status'] . ")\n";
		if ( $row['status'] === 'done' ) {
			break;
		}
		if ( ( microtime( true ) - $start ) > $budget ) {
			echo "[" . date( 'Y-m-d H:i:s' ) . "] budget reached, deferring rest\n";
			break 2;
		}
	}
}

delete_transient( $lock_key );
echo "[" . date( 'Y-m-d H:i:s' ) . "] runner finished (batches=$ran)\n";
exit( 0 );
