<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Jinyu_Setting {

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		add_action( 'wp_ajax_jinyu_save_options', [ $this, 'ajax_save' ] );
		add_action( 'wp_ajax_jinyu_reset_options', [ $this, 'ajax_reset' ] );
		add_action( 'wp_ajax_jinyu_export_options', [ $this, 'ajax_export' ] );
		add_action( 'wp_ajax_jinyu_import_options', [ $this, 'ajax_import' ] );
		add_action( 'wp_ajax_jinyu_reset_section', [ $this, 'ajax_reset_section' ] );
		add_action( 'wp_ajax_jinyu_regenerate_thumbs', [ $this, 'ajax_regenerate_thumbs' ] );
		// 查询是否有未跑完的重建任务（页面刷新 / 重开后恢复进度并接着跑）
		add_action( 'wp_ajax_jinyu_regen_status', [ $this, 'ajax_regen_status' ] );
		// 顶栏报警图标：仍有历史封面缺主题尺寸时在设置页顶栏亮起（见 topbar_thumb_alert）。
		// 不走 admin_notices —— 那个钩子在 .wrap 之外输出，内容与主题设置框架不是同一个
		// 视觉层；改挂顶栏操作区内的自定义时机，点击即可直达本页「维护工具」面板。
		add_action( 'jinyu_setting_topbar_actions', [ $this, 'topbar_thumb_alert' ] );
	}

	/**
	 * 注册顶层菜单「金玉主题配置」。
	 *
	 * 用 add_menu_page 而非 add_theme_page：主题设置是高频入口，藏在「外观」下要两步才到。
	 * 这符合 WP 官方允许的做法（Theme Handbook 的 options page 章节即以此为例），capability
	 * 仍用 edit_theme_options —— 语义上是"改主题选项"，而不是把管理员专属权限再复制一份。
	 *
	 * 位置 81 的取舍：绕开 WP core 的 Settings(80)、Jinyu_Companion 的「金玉增强」(60)，
	 * 也不与任何 core 菜单打架；尾随 Settings 之后，符合"配置类"聚在一起的直觉。
	 * 注意 body class 会随 hook_suffix 变为 toplevel_page_jinyu-options（admin.less 依赖它）。
	 */
	public function register_menu(): void {
		add_menu_page(
			__( '金玉主题配置', 'jinyu' ),
			__( '金玉主题配置', 'jinyu' ),
			'edit_theme_options',
			'jinyu-options',
			[ $this, 'render_page' ],
			'dashicons-admin-customizer',
			81
		);
	}

	/**
	 * 所有设置分组类名（顺序即后台导航顺序）。collect_groups / sdt_defaults / field_schema 共用同一份清单。
	 */
	public static function option_classes(): array {
		return [ 'Jinyu_OptionBasic', 'Jinyu_OptionGlobal', 'Jinyu_OptionStyle', 'Jinyu_OptionContent', 'Jinyu_OptionComment', 'Jinyu_OptionCarousel', 'Jinyu_OptionExtend', 'Jinyu_OptionUser', 'Jinyu_OptionFooter', 'Jinyu_OptionCode' ];
	}

	/**
	 * 展平所有分组的字段定义：id => 字段数组（单一遍历入口，避免多处重复实现）。
	 */
	private static function each_field(): array {
		require_once JINYU_ABS_DIR . '/inc/setting/options/Jinyu_BaseOptionItem.php';
		$fields = [];
		foreach ( self::option_classes() as $cls ) {
			$file = JINYU_ABS_DIR . '/inc/setting/options/' . $cls . '.php';
			if ( ! file_exists( $file ) ) {
				continue;
			}
			require_once $file;
			$fqcn = 'Jinyu\\Theme\\setting\\options\\' . $cls;
			if ( ! class_exists( $fqcn ) ) {
				continue;
			}
			try {
				$group = ( new $fqcn() )->get_fields();
			} catch ( \Throwable $e ) {
				continue;
			}
			$list = $group['fields'] ?? $group;
			if ( ! is_array( $list ) ) {
				continue;
			}
			foreach ( $list as $f ) {
				if ( is_array( $f ) && isset( $f['id'] ) ) {
					$fields[ $f['id'] ] = $f;
				}
			}
		}
		// 合并自定义面板（维护工具等）声明的字段，使其进入保存 schema：
		// 否则这类字段会被 sanitize_fields 当未知键丢弃、或被 drop_orphan_keys 清除。
		foreach ( self::custom_groups() as $cg ) {
			foreach ( ( $cg['fields'] ?? [] ) as $f ) {
				if ( is_array( $f ) && isset( $f['id'] ) ) {
					$fields[ $f['id'] ] = $f;
				}
			}
		}
		return $fields;
	}

	public function collect_groups(): array {
		require_once JINYU_ABS_DIR . '/inc/setting/options/Jinyu_BaseOptionItem.php';
		$classes = self::option_classes();

		$groups = [];
		foreach ( $classes as $cls ) {
			$file = JINYU_ABS_DIR . '/inc/setting/options/' . $cls . '.php';
			if ( ! file_exists( $file ) ) {
				continue;
			}
			require_once $file;
			$fqcn = 'Jinyu\\Theme\\setting\\options\\' . $cls;
			if ( class_exists( $fqcn ) ) {
				$group    = ( new $fqcn() )->get_fields();
				$groups[] = $group;
			}
		}

		// 维护工具 / 我要反馈 等自定义面板（定义见 custom_groups()）。
		// 维护工具面板内由 admin.js 自行渲染并保存个别设置字段（如 footer_runinfo），
		// 这些字段必须在 custom_groups() 的 fields 中声明，才能进入保存 schema、不被 drop_orphan_keys 清除。
		return array_merge( $groups, self::custom_groups() );
	}

	/**
	 * 自定义面板（非普通选项分组）：由 admin.js 自行渲染并保存个别字段。
	 * 若面板内含需持久化的设置字段（如维护工具的 footer_runinfo），必须在 fields 中声明，
	 * 以便 each_field() 将其纳入保存 schema（sanitize / drop_orphan_keys 据此识别）。
	 */
	private static function custom_groups(): array {
		return [
			[
				'key'    => 'tools',
				'title'  => __( '维护工具', 'jinyu' ),
				'desc'   => __( '封面缩略图重建、配置导出导入与运行信息开关等主题侧运维操作。邮件 SMTP / 缓存清理 / 数据库优化 / 对象存储请用「金玉增强」插件面板。', 'jinyu' ),
				'custom' => 'tools',
				'fields' => [
					[
						'id'    => 'footer_runinfo',
						'title' => __( '页脚显示运行信息', 'jinyu' ),
						'type'  => 'switch',
						'sdt'   => 0,
						'desc'  => __( '在前台页脚输出一行实时运行信息：查询数 / 内存 / 渲染耗时。数值由 JS 实时拉取，不会被整页缓存冻结。开启后到「金玉增强」清理一次缓存即可生效', 'jinyu' ),
					],
				],
			],
		];
	}

	/**
	 * 构建 id => sdt 默认值表（静态缓存）。
	 * 供 opt.php 的 jinyu_get_option 在未命中已存值时回退，使字段 sdt 成为运行时默认值单一事实来源。
	 */
	public static function sdt_defaults(): array {
		static $map = null;
		if ( $map !== null ) {
			return $map;
		}
		$map = [];
		foreach ( self::each_field() as $id => $f ) {
			$map[ $id ] = $f['sdt'] ?? null;
		}
		return $map;
	}

	/**
	 * Id => [type, min, max, step, options, sdt]，保存时服务端校验用。
	 */
	public static function field_schema(): array {
		static $map = null;
		if ( $map !== null ) {
			return $map;
		}
		$map = [];
		foreach ( self::each_field() as $id => $f ) {
			$map[ $id ] = [
				'type'    => $f['type'] ?? 'string',
				'min'     => $f['min'] ?? null,
				'max'     => $f['max'] ?? null,
				'step'    => $f['step'] ?? null,
				'options' => ( isset( $f['options'] ) && is_array( $f['options'] ) ) ? array_column( $f['options'], 'value' ) : [],
				'sdt'     => $f['sdt'] ?? null,
				// 契约（防回归）：html=true 的输出层必须配合 wp_kses_post 使用（textarea 当前为
				// footer_about / footer_copyright / single_copyright，string 当前为 top_notice）。
				// 保存侧见 sanitize_fields() 的 textarea / string 分支——html 字段走 wp_kses_post
				// 而非 sanitize_textarea_field / sanitize_text_field。二者必须同步：任一侧改回
				// 「去标签」都会让页脚与顶部公告的 HTML 在保存时丢失。
				// 改动此契约前，先跑 tests/check-html-textarea-contract.js 回归校验。
				'html'    => ! empty( $f['html'] ),
			];
		}
		return $map;
	}

	/**
	 * 按字段 schema 规范化前端提交的数据：
	 * - 未注册的键一律丢弃（防脏数据入库）
	 * - number / slider 钳到 min~max 并按 step 取整
	 * - select / radio 限枚举，非法值回退 sdt
	 * - switch 归一到 0 / 1
	 * 其余类型（string / textarea / color / upload / password / dynamic-list）原样保留。
	 *
	 * @param array $input array 参数。
	 */
	private function sanitize_fields( array $input ): array {
		$schema = self::field_schema();
		$out    = [];
		foreach ( $input as $key => $val ) {
			$f = $schema[ $key ] ?? null;
			if ( ! $f ) {
				continue;
			}
			switch ( $f['type'] ) {
				case 'switch':
					$out[ $key ] = (int) (bool) filter_var( $val, FILTER_VALIDATE_BOOLEAN );
					break;
				case 'number':
				case 'slider':
					$n    = is_numeric( $val ) ? (float) $val : (float) $f['sdt'];
					$step = $f['step'] !== null ? (float) $f['step'] : 1;
					if ( $f['min'] !== null ) {
						$n = max( (float) $f['min'], $n );
					}
					if ( $f['max'] !== null ) {
						$n = min( (float) $f['max'], $n );
					}
					if ( $step > 0 ) {
						$n = round( $n / $step ) * $step;
					}
					if ( $f['min'] !== null ) {
						$n = max( (float) $f['min'], $n );
					}
					if ( $f['max'] !== null ) {
						$n = min( (float) $f['max'], $n );
					}
					$out[ $key ] = ( $n == (int) $n ) ? (int) $n : $n;
					break;
				case 'select':
				case 'radio':
					$allowed     = array_map( 'strval', $f['options'] );
					$out[ $key ] = in_array( (string) $val, $allowed, true ) ? $val : $f['sdt'];
					break;
				default:
					// 自由文本类字段按类型兜底 sanitize（纵深防御：入库前清洗，输出端仍有转义）
					switch ( $f['type'] ) {
						case 'textarea':
							// html 字段输出层用 wp_kses_post，保存时同样放行安全 HTML（剥离 script/style 等），
							// 其余纯文本 textarea 仍用 sanitize_textarea_field 彻底去标签。
							$out[ $key ] = ! empty( $f['html'] )
								? wp_kses_post( (string) $val )
								: sanitize_textarea_field( (string) $val );
							break;
						case 'color':
							$out[ $key ] = sanitize_hex_color( (string) $val ) ?? $f['sdt'];
							break;
						case 'upload':
							$out[ $key ] = esc_url_raw( (string) $val );
							break;
						case 'password':
							// 密钥类：保留原始字符（sanitize 会破坏密钥），落库经 crypto 加密，输出端 esc_attr
							$out[ $key ] = (string) $val;
							break;
						case 'dynamic-list':
							$out[ $key ] = is_array( $val )
								? array_values( array_map( 'sanitize_text_field', array_map( 'strval', $val ) ) )
								: $f['sdt'];
							break;
						default: // string
							// 同 textarea：html 标记字段放行安全 HTML，纯文本 string 仍彻底去标签。
							$out[ $key ] = ! empty( $f['html'] )
								? wp_kses_post( (string) $val )
								: sanitize_text_field( (string) $val );
					}
			}
		}
		return $out;
	}

	/**
	 * 裁剪孤儿键：移除已不在任何面板字段定义中的旧键（字段 id 改名后残留的旧数据）。
	 * Jinyu_options 的唯一写入路径是 ajax_save / ajax_import（均经 jinyu_save_options），
	 * 站内不存在面板外的运行时键（如 jinyu_indexnow_key 为独立 option），故按已注册键集裁剪是安全的。
	 * 避免库随字段重构无限膨胀、且旧键永远不被读取却占用存储。
	 *
	 * @param array $opts array 参数。
	 */
	private function drop_orphan_keys( array $opts ): array {
		$known = array_keys( self::sdt_defaults() );
		return array_intersect_key( $opts, array_flip( $known ) );
	}

	public function render_page(): void {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}
		$groups = $this->collect_groups();
		include __DIR__ . '/template.php';
	}

	public function ajax_save(): void {
		check_ajax_referer( 'jinyu_save_options', 'nonce' );
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_send_json_error( __( '权限不足', 'jinyu' ) );
		}
		$input = json_decode( file_get_contents( 'php://input' ), true );
		if ( ! is_array( $input ) ) {
			wp_send_json_error( __( '数据格式错误', 'jinyu' ) );
		}
		// 服务端按字段 schema 校验/钳制，并与现有选项合并（避免前端漏字段造成配置丢失）
		$current = get_option( JINYU_OPT, [] );
		if ( ! is_array( $current ) ) {
			$current = [];
		}
		$current = $this->drop_orphan_keys( $current );
		$clean   = $this->sanitize_fields( $input );
		jinyu_save_options( array_merge( $current, $clean ) );
		wp_send_json_success( [ 'msg' => __( '保存成功', 'jinyu' ) ] );
	}

	public function ajax_reset(): void {
		check_ajax_referer( 'jinyu_save_options', 'nonce' );
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_send_json_error( __( '权限不足', 'jinyu' ) );
		}
		delete_option( JINYU_OPT );
		wp_send_json_success( [ 'msg' => __( '已重置', 'jinyu' ) ] );
	}

	/**
	 * 导出当前配置为 JSON（供备份 / 迁移）。
	 */
	public function ajax_export(): void {
		check_ajax_referer( 'jinyu_save_options', 'nonce' );
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_send_json_error( __( '权限不足', 'jinyu' ) );
		}
		$data = get_option( JINYU_OPT, [] );
		wp_send_json_success(
			[
				'msg'  => __( '导出成功', 'jinyu' ),
				'data' => $data,
			]
		);
	}

	/**
	 * 导入 JSON 配置（与现有选项合并，增量覆盖，不丢其它键）。
	 */
	public function ajax_import(): void {
		check_ajax_referer( 'jinyu_save_options', 'nonce' );
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_send_json_error( __( '权限不足', 'jinyu' ) );
		}
		$body = json_decode( file_get_contents( 'php://input' ), true );
		if ( ! is_array( $body ) || ! isset( $body['data'] ) || ! is_array( $body['data'] ) ) {
			wp_send_json_error( __( '数据格式错误', 'jinyu' ) );
		}
		$incoming = $body['data'];
		// 白名单过滤：只接受已注册字段的键，避免注入无关数据
		$allowed        = array_keys( self::sdt_defaults() );
		$filtered       = [];
		$skipped_secret = 0;
		foreach ( $incoming as $k => $v ) {
			if ( ! in_array( $k, $allowed, true ) ) {
				continue;
			}
			// 跨站导入的密文由源站密钥加密，本机解不开 → 直接丢弃，不写入不可用的垃圾值
			if ( is_string( $v ) && str_starts_with( $v, 'jinyu_enc::' ) && jinyu_decrypt( $v ) === '' ) {
				++$skipped_secret;
				continue;
			}
			$filtered[ $k ] = $v;
		}
		// 与 ajax_save 走同一条清洗路径（min/max/step/select 白名单钳制），避免导入绕过约束
		$filtered = $this->sanitize_fields( $filtered );
		$current  = get_option( JINYU_OPT, [] );
		$merged   = array_merge( $current, $filtered );
		jinyu_save_options( $merged );
		$msg = __( '导入成功', 'jinyu' );
		if ( $skipped_secret > 0 ) {
			$msg .= sprintf( __( '；%d 个加密字段（API Key / 密码等）无法跨站解密，已跳过，请重新填写', 'jinyu' ), $skipped_secret );
		}
		wp_send_json_success( [ 'msg' => $msg ] );
	}

	/**
	 * 重建封面缩略图：为缺 jinyu-thumb / jinyu-cover 的附件重新生成派生尺寸。
	 *
	 * 主题在 after_setup_theme 注册了 jinyu-cover(768×512) 与 jinyu-thumb(400×267)，但
	 * 「上传时生成派生尺寸」只对注册之后新上传的图生效；历史上传的图必须补一次，前台才会
	 * 拿到真正的小图。整批重建按「只收集真正缺图的队列 + 按实测耗时自适应批次」进行，
	 * 前端每批拿到进度后自动续跑（more=true），直到队列清空。
	 *
	 * 生命周期说明：任务由浏览器发起的 AJAX 驱动，切走页面时当前批次跑完即止；浏览器关闭
	 * 后请求随 FPM 一起终止，不会有后台常驻进程。若此时闸门残留，靠状态里的心跳时间戳
	 * （ts）判定任务已死，新请求可直接接管续跑，无需等 transient 自行过期。
	 */
	public function ajax_regenerate_thumbs(): void {
		// 先能力后 nonce：避免向未授权访客暴露 nonce 有效性
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_send_json_error( __( '权限不足', 'jinyu' ) );
		}
		check_ajax_referer( 'jinyu_save_options' );
		// 裁剪函数随 wp-admin/includes/image.php 注册，前台请求里并不存在，显式兜底加载
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) || ! function_exists( 'wp_get_registered_image_subsizes' ) ) {
			wp_send_json_error( __( '当前服务器环境不支持缩略图重建（缺少 GD 或 WordPress 版本过旧）', 'jinyu' ) );
		}

		$st_key = 'jinyu_thumbs_regen_state';
		$lk_key = 'jinyu_thumbs_regen_busy';

		// 停止按钮：丢弃队列与闸门，下次点击从零开始
		if ( ! empty( $_POST['stop'] ) ) {
			$stopped = get_transient( $st_key );
			delete_transient( $st_key );
			delete_transient( $lk_key );
			$done = is_array( $stopped ) ? (int) $stopped['gen'] + (int) $stopped['skip'] + (int) $stopped['fail'] : 0;
			$tot  = is_array( $stopped ) ? (int) $stopped['total'] : 0;
			wp_send_json_success(
				[
					'msg'     => __( '已停止，下次点击将重新扫描', 'jinyu' ),
					'more'    => false,
					'done'    => $done,
					'total'   => $tot,
					'percent' => $tot > 0 ? min( 100, (int) round( $done * 100 / $tot ) ) : 0,
				]
			);
		}

		$s = get_transient( $st_key );
		if ( ! is_array( $s ) || ! isset( $s['ids'] ) || ! is_array( $s['ids'] ) ) {
			$s = [
				'ids'   => [],
				'idx'   => 0,
				'gen'   => 0,
				'skip'  => 0,
				'fail'  => 0,
				'total' => 0,
				'avg'   => 0,
				'ts'    => 0,
			];
		}

		// 并发闸门 + 孤儿接管：任务心跳超过 2 分钟没更新，说明发起它的浏览器已关闭/断网，
		// 原请求随 FPM 一起死了，此时允许新请求接管继续，不必等闸门过期。
		$busy  = (bool) get_transient( $lk_key );
		$stale = empty( $s['ts'] ) || ( time() - (int) $s['ts'] ) > 120;
		if ( $busy && ! $stale ) {
			// 带 code 是为了让前端区分「撞闸门」与真失败：撞闸门时任务其实还在跑，
			// 自动续跑的调用方只需退避重试，不该把进度区翻成「已停止」
			wp_send_json_error(
				[
					'code' => 'busy',
					'msg'  => __( '已有重建任务正在进行，请稍候再试', 'jinyu' ),
				]
			);
		}

		// 队列只在首次构建，之后按游标推进：省掉每批重复全库扫描，也让进度条反映真实工作量
		if ( empty( $s['ids'] ) ) {
			global $wpdb;
			// 只处理图片附件；DESC 让最近上传的图先补齐（前台最常看到的是最新文章）
			$ids   = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%' ORDER BY ID DESC" );
			$queue = [];
			foreach ( (array) $ids as $aid ) {
				$meta = wp_get_attachment_metadata( (int) $aid );
				if ( is_array( $meta ) && ! empty( $meta['sizes']['jinyu-cover'] ) && ! empty( $meta['sizes']['jinyu-thumb'] ) ) {
					continue;
				}
				$queue[] = (int) $aid;
			}
			$s = [
				'ids'   => $queue,
				'idx'   => 0,
				'gen'   => 0,
				'skip'  => 0,
				'fail'  => 0,
				'total' => count( $queue ),
				'avg'   => 0,
				'ts'    => time(),
			];
		}
		set_transient( $lk_key, 1, 900 );
		$s['ts'] = time();

		$total = (int) $s['total'];
		$left  = count( $s['ids'] ) - (int) $s['idx'];

		if ( $left <= 0 ) {
			$this->finish_thumbs_regen( $s );
		}

		// 批次大小按上一批实测均耗自适应，始终把单批压在 PHP 执行时限之内
		// （多数主机 max_execution_time 为 20s，超出会被直接掐断）
		$max_exec = (int) ini_get( 'max_execution_time' );
		$budget   = $max_exec > 0 ? min( 40, (int) round( $max_exec * 1.2 ) ) : 40;
		$budget   = max( 8, $budget );
		$avg      = (float) $s['avg'];
		$batch    = ( $avg > 0.001 ) ? (int) max( 3, min( 40, floor( $budget * 0.7 / $avg ) ) ) : 10;
		$batch    = min( $batch, $left );

		// 只为补齐主题两档：本站核心尺寸宽度为 0，本就没有派生图，限定后每次重建
		// 省掉核心中间尺寸的 GD 缩放（实测 registered sizes 仅 4 档，可省一半以上）
		add_filter( 'intermediate_image_sizes', [ $this, 'limit_regen_sizes' ], 10, 2 );

		$gen = $skip = $fail = $n = 0;
		$t0  = microtime( true );
		while ( $n < $batch ) {
			$aid = (int) $s['ids'][ (int) $s['idx'] ];
			++$s['idx'];
			++$n;
			$file = get_attached_file( $aid );
			if ( ! $file || ! is_file( $file ) ) {
				++$skip;
				++$s['skip'];
				continue;
			}
			if ( wp_generate_attachment_metadata( $aid, $file ) ) {
				++$gen;
				++$s['gen'];
			} else {
				++$fail;
				++$s['fail'];
			}
			// 到达预算立刻收手：宁可多一轮请求，也不要被 PHP 掐断造成半批丢失
			if ( microtime( true ) - $t0 > $budget ) {
				break;
			}
		}
		remove_filter( 'intermediate_image_sizes', [ $this, 'limit_regen_sizes' ], 10 );

		$elapsed = microtime( true ) - $t0;
		$s['ts'] = time();
		if ( $n > 0 ) {
			$s['avg'] = round( $elapsed, 3 ) / $n;
		}

		$done    = (int) $s['gen'] + (int) $s['skip'] + (int) $s['fail'];
		$percent = $total > 0 ? min( 100, (int) round( $done * 100 / $total ) ) : 100;
		$msg     = sprintf(
			/* translators: 1: 已处理数, 2: 总数, 3: 新生成, 4: 跳过, 5: 失败 */
			__( '已处理 %1$d/%2$d，新生成 %3$d，跳过 %4$d，失败 %5$d', 'jinyu' ),
			number_format_i18n( $done ),
			number_format_i18n( $total ),
			number_format_i18n( (int) $s['gen'] ),
			number_format_i18n( (int) $s['skip'] ),
			number_format_i18n( (int) $s['fail'] )
		);

		if ( (int) $s['idx'] >= count( $s['ids'] ) ) {
			$this->finish_thumbs_regen( $s );
		}

		set_transient( $st_key, $s, 900 );
		delete_transient( $lk_key );
		wp_send_json_success(
			[
				'msg'     => $msg,
				'more'    => true,
				'done'    => $done,
				'total'   => $total,
				'percent' => $percent,
				'gen'     => (int) $s['gen'],
				'skip'    => (int) $s['skip'],
				'fail'    => (int) $s['fail'],
			]
		);
	}

	/**
	 * 只读查询重建任务状态，供前端在页面刷新 / 重新打开后恢复进度并自动续跑。
	 *
	 * 不设闸门、不改状态：判活交给调用方（stale 为真表示心跳已过期，原请求多半已中断）。
	 */
	public function ajax_regen_status(): void {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_send_json_error( __( '权限不足', 'jinyu' ) );
		}
		check_ajax_referer( 'jinyu_save_options' );

		$s = get_transient( 'jinyu_thumbs_regen_state' );
		if ( ! is_array( $s ) || empty( $s['ids'] ) || (int) $s['idx'] >= count( $s['ids'] ) ) {
			wp_send_json_success( [ 'running' => false ] );
		}

		$total = (int) $s['total'];
		$done  = (int) $s['gen'] + (int) $s['skip'] + (int) $s['fail'];
		wp_send_json_success(
			[
				'running'   => true,
				'stale'     => ( time() - (int) $s['ts'] ) > 120,
				'percent'   => $total > 0 ? min( 100, (int) round( $done * 100 / $total ) ) : 0,
				'done'      => $done,
				'total'     => $total,
				'gen'       => (int) $s['gen'],
				'skip'      => (int) $s['skip'],
				'fail'      => (int) $s['fail'],
				'remaining' => count( $s['ids'] ) - (int) $s['idx'],
			]
		);
	}

	/**
	 * 重建时把中间尺寸限制为主题两档，省掉无关缩放。见 ajax_regenerate_thumbs()。
	 *
	 * @param string[] $sizes
	 * @return string[]
	 */
	public function limit_regen_sizes( $sizes ): array {
		return array_values(
			array_filter(
				(array) $sizes,
				function ( $sz ) {
					return in_array( $sz, [ 'jinyu-cover', 'jinyu-thumb' ], true );
				}
			)
		);
	}

	/**
	 * 队列跑完的统一收尾：清闸门与状态、让提醒用的缺口统计下次重新计算。
	 *
	 * @param array $s array 参数。
	 */
	private function finish_thumbs_regen( array $s ): void {
		delete_transient( 'jinyu_thumbs_regen_state' );
		delete_transient( 'jinyu_thumbs_regen_busy' );
		// 缺口情况已变，清掉提醒用的统计缓存，下次进入后台重新计算
		delete_transient( 'jinyu_missing_cover_count' );

		$done = (int) ( $s['gen'] + $s['skip'] + $s['fail'] );
		$tot  = (int) $s['total'];
		/* translators: 1: 新生成张数, 2: 跳过张数, 3: 失败张数 */
		wp_send_json_success(
			[
				'msg'     => sprintf(
					__( '重建完成：新生成 %1$d 张，已齐全跳过 %2$d 张，失败 %3$d 张', 'jinyu' ),
					number_format_i18n( (int) $s['gen'] ),
					number_format_i18n( (int) $s['skip'] ),
					number_format_i18n( (int) $s['fail'] )
				),
				'more'    => false,
				'done'    => $done,
				'total'   => $tot,
				'percent' => 100,
				'gen'     => (int) $s['gen'],
				'skip'    => (int) $s['skip'],
				'fail'    => (int) $s['fail'],
			]
		);
	}

	/**
	 * 仅重置某一个设置分组（移除该分组所有字段的已存值，回退到 sdt 默认值）。
	 */
	public function ajax_reset_section(): void {
		check_ajax_referer( 'jinyu_save_options', 'nonce' );
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_send_json_error( __( '权限不足', 'jinyu' ) );
		}
		$body = json_decode( file_get_contents( 'php://input' ), true );
		$key  = isset( $body['key'] ) ? $body['key'] : '';
		if ( ! $key ) {
			wp_send_json_error( __( '参数错误', 'jinyu' ) );
		}

		$target = null;
		foreach ( $this->collect_groups() as $g ) {
			if ( ( $g['key'] ?? '' ) === $key && empty( $g['custom'] ) ) {
				$target = $g;
				break; }
		}
		if ( ! $target ) {
			wp_send_json_error( __( '分组不存在', 'jinyu' ) );
		}

		$ids  = array_column( $target['fields'] ?? [], 'id' );
		$opts = get_option( JINYU_OPT, [] );
		if ( ! is_array( $opts ) ) {
			$opts = [];
		}
		foreach ( $ids as $id ) {
			unset( $opts[ $id ] ); }
		update_option( JINYU_OPT, $opts );
		wp_send_json_success( [ 'msg' => __( '已重置本组', 'jinyu' ) ] );
	}

	/* ── 后台提醒：历史封面缺主题尺寸 ───────────────────────────────── */

	/**
	 * 顶栏报警图标：仍有封面缺 jinyu-cover / jinyu-thumb 时在**主题设置页**顶栏亮起。
	 *
	 * 派生尺寸只对「注册之后新上传的图」生效，历史图必须补跑一次重建，而这一步
	 * 多数站长并不知道要做（表现为卡片莫名加载原图、首页偏慢）。这里给一个常驻的
	 * 报警入口，避免问题被长期忽略。
	 *
	 * 用图标而不是页顶提醒条：图标只占顶栏里一个按钮的位置，不额外吃纵向空间，
	 * 点击即落到本页「维护工具」面板，不需要二次跳转。
	 *
	 * 只在主题设置页出现：这是一条找得到出路的维护引导，全后台常驻只会变成噪音，
	 * 用户也会以为站点处处有问题。
	 */
	public function topbar_thumb_alert(): void {
		// 先能力后副作用：非授权访客不触发任何查询
		if ( ! current_user_can( 'edit_theme_options' ) || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		// 屏幕判断要放在统计之前：非本页直接返回，避免每个后台页面都去查一遍附件
		// （与 inc/fun/cache.php 的提醒同一约定）
		$screen    = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$screen_ok = $screen && strpos( $screen->id, 'jinyu-options' ) !== false;
		$page_ok   = ! empty( $_GET['page'] ) && $_GET['page'] === 'jinyu-options';
		if ( ! $screen_ok && ! $page_ok ) {
			return;
		}
		// 没有「暂不提醒」这类抑制开关：报警的意义在于它一直在，直到图片补齐为止。
		// 重建跑完 missing 归零、图标自然消失，不需要额外的基线状态。
		$missing = self::count_missing_covers();
		if ( $missing <= 0 ) {
			return;
		}
		// 直达「维护工具」面板（面板切换用 hash，见 admin.js activate）。
		// 用 <a> 而非 <button>：不依赖 admin.min.js 的事件绑定，行为在任何加载顺序下都一致。
		// 设置页已是顶层菜单（admin.php?page=jinyu-options），不再是 themes.php 子页面
		$rebuild_url = add_query_arg( 'page', 'jinyu-options', admin_url( 'admin.php' ) ) . '#tools';
		// 角标只给两位数：三位以上占位会让按钮撑成一个圆饼，99+ 已足够表达"还很多"
		$badge = $missing > 99 ? 99 : $missing;
		/* translators: 1: 缺派生尺寸的封面张数 */
		$summary = sprintf(
			__( '有 %1$d 张历史封面图缺少主题尺寸', 'jinyu' ),
			number_format_i18n( $missing )
		);
		// 完整说明收进浮层与 aria-label：按钮本身只承担"这里有报警"的入口职责，
		// 长文案铺在顶栏会把整条操作区顶宽。
		$tip = $summary .
			__( '：缺 jinyu-cover / jinyu-thumb，卡片会直接加载原图拖慢页面；前往「维护工具」重建一次即可，无需手动裁剪。', 'jinyu' );
		printf(
			'<a class="jinyu-alert" href="%1$s" aria-label="%2$s">' .
				'<span class="jinyu-alert-ico" aria-hidden="true">' .
					'<svg class="jinyu-ico-svg" viewBox="0 0 24 24"><path d="M12 2a1 1 0 0 1 .9.55l9.5 17A1 1 0 0 1 22 21H2a1 1 0 0 1-.9-1.45l9.5-17A1 1 0 0 1 12 2zm0 4.24L4.62 19h14.76L12 6.24zM11 11v5h2v-5h-2zm0 7v2h2v-2h-2z"/></svg>' .
				'</span>' .
				'<span class="jinyu-alert-count" aria-hidden="true">%3$d</span>' .
				'<span class="jinyu-alert-tip" role="tooltip">%4$s</span>' .
			'</a>',
			esc_url( $rebuild_url ),
			esc_attr( $tip ),
			(int) $badge,
			esc_html( $tip )
		);
	}

	/**
	 * 统计最近图片附件中「缺 jinyu-cover」的数量。
	 *
	 * 顶栏报警图标只需大致趋势，全站扫描代价过高，因此只取最近 40 张并缓存 6 小时；
	 * 重建完成时主动清缓存。
	 */
	public static function count_missing_covers(): int {
		$cached = get_transient( 'jinyu_missing_cover_count' );
		if ( false !== $cached ) {
			return (int) $cached;
		}
		global $wpdb;
		$ids     = $wpdb->get_col(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%' ORDER BY ID DESC LIMIT 40"
		);
		$missing = 0;
		foreach ( (array) $ids as $aid ) {
			$meta = wp_get_attachment_metadata( (int) $aid );
			if ( empty( $meta['sizes']['jinyu-cover'] ) ) {
				++$missing;
			}
		}
		set_transient( 'jinyu_missing_cover_count', $missing, 6 * HOUR_IN_SECONDS );
		return $missing;
	}
}
