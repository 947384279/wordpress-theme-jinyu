<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 侧栏「实时数据」支撑层
 *
 * 为「访客信息 / 站点性能 / 时钟 / 网站概况」四个小工具提供数据来源。
 *
 * 为什么这些数据不在服务端直接渲染进 HTML：
 *  站点可能启用整页缓存（主题缓存层或 WP Super Cache 等插件），缓存页对所有人
 *  是同一份 HTML。若把「访客 IP / 归属地 / 运行指标」写进正文，会把缓存生成者
 *  那一刻的数据固化给之后所有访客（把别人的 IP 当自己的显示）。故统一改为页面
 *  加载后经 admin-ajax 取回，正文只输出结构与静态文案。
 *
 * 依赖：jinyu_parse_ua()（user-agent-parse.php）、jinyu_ajax_guard()（ajax/index.php）
 *
 * @package Jinyu
 */

if (!defined('JINYU_PERF_MAX')) {
	/** 心跳环形缓冲长度 */
	define('JINYU_PERF_MAX', 24);
}

/* ==========================================================================
   访客 IP（仅供展示）
   ========================================================================== */

if (!function_exists('jinyu_visitor_ip')) {
	/**
	 * 取得「用于展示」的访客 IP。
	 *
	 * 与 jinyu_client_ip()（security.php）的区别及理由：
	 *  - jinyu_client_ip() 服务于限流等安全判定，只在 REMOTE_ADDR 属于可信私网时才采信
	 *    X-Forwarded-For，避免伪造该头绕过限流——这是对的，必须保持原样。
	 *  - 本站挂了 CDN，PHP 侧 REMOTE_ADDR 是 CDN 边缘节点 IP，拿它展示「您的 IP」是错的。
	 *    展示场景无安全后果（伪造 XFF 只会让访客看到自己伪造的假 IP），故这里优先取 XFF 首段。
	 *
	 * ⚠️ 本函数结果只可用于展示，禁止用于任何权限 / 限流 / 计费判定。
	 *
	 * @return string 合法 IP；取不到时返回空串
	 */
	function jinyu_visitor_ip(): string
	{
		foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP'] as $key) {
			if (empty($_SERVER[$key])) {
				continue;
			}
			foreach (explode(',', (string) $_SERVER[$key]) as $candidate) {
				$ip = trim($candidate);
				// 排除私有 / 保留网段：CDN 有时会把内网地址塞进链路
				if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
					return $ip;
				}
			}
		}
		return trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
	}
}

/* ==========================================================================
   IP 归属地（外部查询 + 缓存 + 降级）
   ========================================================================== */

if (!function_exists('jinyu_ip_location')) {
	/**
	 * 查询 IP 归属地（省 + 市 + 运营商），如「安徽省宣城市 电信」。
	 *
	 * 三重保护，避免这个外部依赖拖垮侧栏：
	 *  1. 命中 12 小时瞬态缓存即直接返回，常态下一次外部请求也没有；
	 *  2. 未命中时先写「空值 + 5 分钟」负缓存，防止并发把第三方接口打爆；
	 *  3. 任何失败（超时 / 非 200 / 解析失败）一律静默返回空串，调用方自行隐藏该行。
	 *
	 * @param string $ip           待查 IP
	 * @param bool   $allow_lookup 为 false 时只读缓存不发外部请求（限流降级用）
	 * @return string 归属地；查询失败返回空串
	 */
	function jinyu_ip_location(string $ip, bool $allow_lookup = true): string
	{
		if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
			return '';
		}

		$cache_key = 'jinyu_iploc_' . md5($ip);
		$cached    = get_transient($cache_key);
		if ($cached !== false) {
			return (string) $cached;
		}
		if (!$allow_lookup) {
			return '';
		}

		// 负缓存占位：并发请求在首次查询完成前都读到这里，直接降级
		set_transient($cache_key, '', 5 * MINUTE_IN_SECONDS);

		$loc  = '';
		$resp = wp_remote_get(
			'https://whois.pconline.com.cn/ipJson.jsp?json=true&ip=' . rawurlencode($ip),
			[
				'timeout'     => 3,
				'redirection' => 2,
				'user-agent'  => 'Jinyu-Theme/' . (defined('JINYU_CUR_VER') ? JINYU_CUR_VER : '1.0'),
			]
		);

		if (!is_wp_error($resp) && 200 === (int) wp_remote_retrieve_response_code($resp)) {
			$body = wp_remote_retrieve_body($resp);
			// 该接口返回 GBK，不转码会得到乱码
			if (function_exists('mb_convert_encoding')) {
				$body = mb_convert_encoding($body, 'UTF-8', 'GBK');
			} elseif (function_exists('iconv')) {
				$converted = @iconv('GBK', 'UTF-8//IGNORE', $body);
				if ($converted !== false) {
					$body = $converted;
				}
			}
			$data = json_decode($body, true);
			if (is_array($data)) {
				$loc = trim((string) ($data['addr'] ?? ''));
				if ($loc === '') {
					$loc = trim((string) ($data['pro'] ?? '') . ' ' . (string) ($data['city'] ?? ''));
				}
			}
		}

		$loc = trim((string) preg_replace('/\s+/u', ' ', $loc));
		set_transient($cache_key, $loc, $loc === '' ? 5 * MINUTE_IN_SECONDS : 12 * HOUR_IN_SECONDS);

		return $loc;
	}
}

/* ==========================================================================
   运行指标采样（心跳）
   ========================================================================== */

if (!function_exists('jinyu_perf_sample')) {
	/**
	 * 记录一次「真实 PHP 渲染」的性能样本到环形缓冲。
	 *
	 * 由 shutdown 钩子触发。命中整页缓存时直接返回：缓存响应只有几毫秒，
	 * 混进曲线会把心跳图压成一条直线，失去参考价值。
	 * 20 秒节流，避免高流量下每请求都写一次瞬态。
	 */
	function jinyu_perf_sample(): void
	{
		if (defined('JINYU_CACHE_HIT') && JINYU_CACHE_HIT) {
			return;
		}
		if (is_admin() || wp_doing_ajax() || wp_doing_cron() || is_404() || is_feed()) {
			return;
		}
		if (!is_front_page() && !is_home() && !is_singular() && !is_archive()) {
			return;
		}

		$last = (int) get_transient('jinyu_perf_last');
		if ($last && time() - $last < 20) {
			return;
		}

		$start = (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
		$raw   = get_transient('jinyu_perf_beats');
		$beats = is_array($raw) ? $raw : [];

		$beats[] = [
			'ms'  => max(1, (int) round((microtime(true) - $start) * 1000)),
			'q'   => (int) get_num_queries(),
			'mem' => round(memory_get_peak_usage(true) / 1048576, 1),
			'ts'  => time(),
		];
		if (count($beats) > JINYU_PERF_MAX) {
			$beats = array_slice($beats, -JINYU_PERF_MAX);
		}

		set_transient('jinyu_perf_beats', $beats, DAY_IN_SECONDS);
		set_transient('jinyu_perf_last', time(), DAY_IN_SECONDS);
	}
}

if (!function_exists('jinyu_perf_history')) {
	/**
	 * @return array<int, array{ms:int,q:int,mem:float,ts:int}>
	 */
	function jinyu_perf_history(): array
	{
		$raw = get_transient('jinyu_perf_beats');
		return is_array($raw) ? $raw : [];
	}
}

// 采样挂在 shutdown：此时查询与内存都已到终值，测出来的才是整页真实开销
add_action('shutdown', 'jinyu_perf_sample', 20);

/* ==========================================================================
   派生指标（均值 / 峰值 / 健康分 / 资源占用）
   ========================================================================== */

if (!function_exists('jinyu_memory_limit_mb')) {
	/**
	 * PHP memory_limit 折算成 MB。不限（-1）或取不到时返回 0，调用方据此隐藏占用率。
	 *
	 * @return int
	 */
	function jinyu_memory_limit_mb(): int
	{
		$raw = trim((string) ini_get('memory_limit'));
		if ($raw === '' || $raw === '-1') {
			return 0;
		}
		$bytes = function_exists('wp_convert_hr_to_bytes') ? (int) wp_convert_hr_to_bytes($raw) : 0;
		return $bytes > 0 ? (int) round($bytes / 1048576) : 0;
	}
}

if (!function_exists('jinyu_server_load')) {
	/**
	 * 服务器 1 分钟平均负载。取不到（Windows / disable_functions / 无权限）返回 null，
	 * 前端据此把该格降级为「内存占用」——绝不显示一个假的 0.00。
	 *
	 * @return float|null
	 */
	function jinyu_server_load(): ?float
	{
		if (!function_exists('sys_getloadavg')) {
			return null;
		}
		$la = @sys_getloadavg();
		if (!is_array($la) || !isset($la[0]) || !is_numeric($la[0])) {
			return null;
		}
		return round((float) $la[0], 2);
	}
}

if (!function_exists('jinyu_perf_stats')) {
	/**
	 * 把心跳样本折算成性能面板要展示的派生指标。
	 *
	 * 计算留在服务端（而不是前端各算一份）：口径唯一，避免将来把「均值」改成中位数时
	 * 只改了一处。前端只负责画。
	 *
	 * 健康分权重：耗时 45 / 查询 20 / 内存 20 / 负载 15；负载取不到时按剩余权重归一化，
	 * 不把缺失项当 0 分算。
	 *
	 * @param array<int, array{ms:int,q:int,mem:float,ts:int}> $beats
	 * @return array<string, mixed>
	 */
	function jinyu_perf_stats(array $beats): array
	{
		$n = count($beats);
		if ($n < 1) {
			return [];
		}

		$ms_list = array_map('intval', array_column($beats, 'ms'));
		$latest  = $beats[$n - 1];
		$prev    = $n > 1 ? $beats[$n - 2] : null;

		$sorted = $ms_list;
		sort($sorted);
		$p90 = (int) $sorted[min($n - 1, (int) floor($n * 0.9))];

		$ms  = (int) $latest['ms'];
		$q   = (int) $latest['q'];
		$mem = (float) $latest['mem'];

		$mem_max = jinyu_memory_limit_mb();
		$mem_pct = $mem_max > 0 ? (int) min(100, round($mem / $mem_max * 100)) : 0;
		$load    = jinyu_server_load();

		// 各分项：100ms 满分、1s 归零；查询 ≤25 次满分，每多一次扣 2 分；
		// 内存占用 ≤10% 满分，每多 1% 扣 1.2 分；负载 0 满分，每 1 扣 40 分。
		$s_ms   = 100 - $ms / 10;
		$s_q    = 100 - max(0, $q - 25) * 2;
		$s_mem  = $mem_max > 0 ? 100 - max(0, $mem_pct - 10) * 1.2 : 100;
		$s_load = $load === null ? null : 100 - $load * 40;

		$parts = [[$s_ms, 0.45], [$s_q, 0.20], [$s_mem, 0.20]];
		if ($s_load !== null) {
			$parts[] = [$s_load, 0.15];
		}
		$acc = 0.0;
		$wsum = 0.0;
		foreach ($parts as $part) {
			$acc  += $part[0] * $part[1];
			$wsum += $part[1];
		}
		$score = (int) max(0, min(100, (int) round($acc / $wsum)));

		return [
			'avg'     => (int) round(array_sum($ms_list) / $n),
			'max'     => (int) $sorted[$n - 1],
			'min'     => (int) $sorted[0],
			'p90'     => $p90,
			'samples' => $n,
			'age'     => max(0, time() - (int) $latest['ts']),
			'prev'    => $prev ? [
				'ms'  => (int) $prev['ms'],
				'q'   => (int) $prev['q'],
				'mem' => (float) $prev['mem'],
			] : null,
			'score'   => $score,
			'level'   => $score >= 90 ? 'fast' : ($score >= 75 ? 'ok' : ($score >= 55 ? 'warn' : 'bad')),
			'mem_max' => $mem_max,
			'mem_pct' => $mem_pct,
			'load'    => $load,
		];
	}
}

/* ==========================================================================
   建站时间（网站概况：运行时长）
   ========================================================================== */

if (!function_exists('jinyu_site_since')) {
	/**
	 * 站点上线时间戳。优先取最早一篇已发布文章的时间，结果缓存 12 小时。
	 *
	 * @return int Unix 时间戳；站点尚无文章时返回 0
	 */
	function jinyu_site_since(): int
	{
		$cached = get_transient('jinyu_site_since');
		if ($cached !== false) {
			return (int) $cached;
		}

		$ids = get_posts([
			'post_type'        => 'post',
			'post_status'      => 'publish',
			'posts_per_page'   => 1,
			'orderby'          => 'date',
			'order'            => 'ASC',
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'ignore_sticky_posts' => true,
			'suppress_filters' => false,
		]);

		$ts = $ids ? (int) get_post_time('U', true, $ids[0]) : 0;
		set_transient('jinyu_site_since', $ts, 12 * HOUR_IN_SECONDS);

		return $ts;
	}
}

if (!function_exists('jinyu_parse_date_to_ts')) {
	/**
	 * 把「Y-m-d」按站点时区解析成时间戳（不用 strtotime，避免按 UTC 解释产生时区偏移）。
	 *
	 * @param string $date
	 * @return int
	 */
	function jinyu_parse_date_to_ts(string $date): int
	{
		$date = trim($date);
		if ($date === '') {
			return 0;
		}
		try {
			$dt = date_create_immutable($date, wp_timezone());
		} catch (Exception $e) {
			return 0;
		}
		return $dt ? (int) $dt->getTimestamp() : 0;
	}
}

/* ==========================================================================
   站点时区时间（时钟 / 日期）
   ========================================================================== */

if (!function_exists('jinyu_live_time')) {
	/**
	 * 当前站点时间。
	 *
	 * ts  是真实 UTC 纪元秒，off 是站点时区偏移秒数。前端据此换算出站点本地时间，
	 * 不依赖访客本机时区（访客在别的时区也不会把「本站时钟」显示成他自己的时间）。
	 *
	 * @return array{ts:int,off:float,date:string,week:string,hm:string}
	 */
	function jinyu_live_time(): array
	{
		$local = current_time('timestamp');           // 站点本地「伪 UTC」纪元
		$week  = ['星期日', '星期一', '星期二', '星期三', '星期四', '星期五', '星期六'];

		return [
			'ts'   => time(),
			'off'  => (float) get_option('gmt_offset', 0) * 3600,
			'date' => gmdate('Y年n月j日', $local),
			'week' => $week[(int) gmdate('w', $local)],
			'hm'   => gmdate('H:i', $local),
		];
	}
}

/* ==========================================================================
   聚合载荷（Ajax 出口）
   ========================================================================== */

if (!function_exists('jinyu_live_payload')) {
	/**
	 * 组装侧栏实时数据。整包一次返回，四个小工具共享一次请求。
	 *
	 * @param bool $allow_geo 是否允许触发外部归属地查询（限流降级用）
	 * @return array
	 */
	function jinyu_live_payload(bool $allow_geo = true): array
	{
		$ua   = jinyu_parse_ua();
		$ip   = jinyu_visitor_ip();
		$beats = jinyu_perf_history();

		// 无历史样本时（新站 / 刚清缓存）用本次请求兜底，保证曲线至少有一个点
		if (!$beats) {
			$start  = (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
			$beats  = [[
				'ms'  => max(1, (int) round((microtime(true) - $start) * 1000)),
				'q'   => (int) get_num_queries(),
				'mem' => round(memory_get_peak_usage(true) / 1048576, 1),
				'ts'  => time(),
			]];
		}

		$latest = end($beats);

		return [
			'time'    => jinyu_live_time(),
			'visitor' => [
				'ip'      => $ip,
				'os'      => (string) ($ua['platform'] ?? ''),
				'browser' => (string) ($ua['browser'] ?? ''),
				'version' => (string) ($ua['version'] ?? ''),
				'loc'     => jinyu_ip_location($ip, $allow_geo),
			],
			'perf'    => array_merge(jinyu_perf_stats($beats), [
				'beats' => array_values(array_map('intval', array_column($beats, 'ms'))),
				'ms'    => (int) $latest['ms'],
				'q'     => (int) $latest['q'],
				'mem'   => (float) $latest['mem'],
			]),
		];
	}
}

/* ==========================================================================
   Ajax：jinyu_sidebar_live
   ========================================================================== */

add_action('wp_ajax_jinyu_sidebar_live', 'jinyu_ajax_sidebar_live');
add_action('wp_ajax_nopriv_jinyu_sidebar_live', 'jinyu_ajax_sidebar_live');

if (!function_exists('jinyu_ajax_sidebar_live')) {
	/**
	 * 侧栏实时数据接口。只读、只依赖访客自身请求信息，不泄露任何站点私密数据。
	 *
	 * 限流只降级归属地查询，不整体报错——否则被刷时时钟/心跳会跟着一起停摆。
	 */
	function jinyu_ajax_sidebar_live(): void
	{
		jinyu_ajax_guard();
		nocache_headers();

		$allow_geo = true;
		if (function_exists('jinyu_rate_limit_check')) {
			// 用真实对端 IP 限流（不能用 jinyu_visitor_ip()，那个可被 XFF 伪造）
			$allow_geo = jinyu_rate_limit_check('sidebar_live', 30, 60);
		}

		wp_send_json_success(jinyu_live_payload($allow_geo));
	}
}
