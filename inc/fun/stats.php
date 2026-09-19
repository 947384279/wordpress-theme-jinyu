<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 金玉访问统计 - PV/UV 面板

// 建表 (首次，带 fallback)
add_action('after_switch_theme', 'jinyu_stats_install');
function jinyu_stats_install()
{
    global $wpdb;
    $tbl = $wpdb->prefix . 'jinyu_stats';
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $tbl (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        stat_date DATE NOT NULL,
        pv INT UNSIGNED DEFAULT 0,
        uv INT UNSIGNED DEFAULT 0,
        UNIQUE KEY uk_date (stat_date)
    ) $charset;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

// 前端计数
// 延迟到 shutdown 写库：统计计数不阻塞页面渲染（原挂 wp_footer 会在 HTML 输出阶段同步写库）
add_action('shutdown', 'jinyu_stats_track');
function jinyu_stats_track()
{
    if (is_admin() || is_robots() || is_feed()) return;
    global $wpdb;
    $tbl = $wpdb->prefix . 'jinyu_stats';
    $today = current_time('Y-m-d');

    // 确保表存在（带 transient 锁）
    if (!get_transient('jinyu_stats_checked')) {
        $wpdb->query("CREATE TABLE IF NOT EXISTS $tbl (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            stat_date DATE NOT NULL,
            pv INT UNSIGNED DEFAULT 0,
            uv INT UNSIGNED DEFAULT 0,
            UNIQUE KEY uk_date (stat_date)
        ) {$wpdb->get_charset_collate()}");
        set_transient('jinyu_stats_checked', 1, HOUR_IN_SECONDS * 12);
    }

    $wpdb->query($wpdb->prepare("INSERT INTO $tbl (stat_date, pv, uv) VALUES (%s, 1, 1) ON DUPLICATE KEY UPDATE pv=pv+1", $today));

    $uv_key = 'jinyu_uv_' . $today;
    if (!isset($_COOKIE[$uv_key])) {
        setcookie($uv_key, '1', time()+86400, '/');
        $wpdb->query($wpdb->prepare("UPDATE $tbl SET uv=uv+1 WHERE stat_date=%s", $today));
    }
}

// 后台仪表盘小工具
add_action('wp_dashboard_setup', function(){
    wp_add_dashboard_widget('jinyu_stats_widget', '金玉访问统计', function(){
        global $wpdb;
        $tbl = $wpdb->prefix . 'jinyu_stats';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT stat_date, pv, uv FROM %i ORDER BY stat_date DESC LIMIT 7", $tbl));
        $total = $wpdb->get_row($wpdb->prepare("SELECT SUM(pv) pv, SUM(uv) uv FROM %i", $tbl));
        echo '<p><b>总 PV:</b> ' . number_format((int)$total->pv) . ' &nbsp; <b>总 UV:</b> ' . number_format((int)$total->uv) . '</p>';
        if ($rows) {
            echo '<table class="widefat striped"><thead><tr><th>日期</th><th>PV</th><th>UV</th></tr></thead><tbody>';
            foreach ($rows as $r) echo '<tr><td>'.$r->stat_date.'</td><td>'.$r->pv.'</td><td>'.$r->uv.'</td></tr>';
            echo '</tbody></table>';
        } else {
            echo '<p>暂无数据</p>';
        }
    });
});

// AJAX 统计接口（仅管理员仪表盘小工具使用，关闭匿名访问）
add_action('wp_ajax_jinyu_stats', 'jinyu_stats_ajax');
function jinyu_stats_ajax()
{
    global $wpdb;
    $tbl = $wpdb->prefix . 'jinyu_stats';
    $row = $wpdb->get_row($wpdb->prepare("SELECT SUM(pv) as pv, SUM(uv) as uv FROM %i", $tbl));
    wp_send_json_success(['pv'=>(int)$row->pv, 'uv'=>(int)$row->uv]);
}