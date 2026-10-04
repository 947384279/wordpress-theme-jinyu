<?php
/**
 * 主题自建图片尺寸注册
 *
 * @package         WordPress
 * @subpackage      Jinyu
 */


	/**
	 * 注册主题自建图片尺寸。挂 after_setup_theme 是 .org 要求的注册时机；
	 * 已存在的旧图不会自动生成，需后台「维护工具 → 重建封面缩略图」补一次。
	 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ─────────────────────────────────────────────────────────────.
// 主题自建图片尺寸.
// 背景：WP 默认尺寸（thumbnail/medium/large）由后台「设置 → 媒体」的宽度决定，.
// 宽度填 0 即不生成该派生尺寸，模板拿到的就只有原图 → 卡片用 1280px 原图填 332px 坑位.
// 主题无权也绝不去改那三个核心 option（跨主题副作用，且审核不通过）.
// 正解：主题注册自己的尺寸，只影响本站、只服务本主题模板，新图上传时自动裁剪.
// 尺寸名一律 jinyu_ 前缀（.org 前缀规范）；crop=true 与模板的 object-fit:cover + aspect-ratio.
// 配套，硬裁后的图正好贴合卡片比例，不会浪费像素.
// ─────────────────────────────────────────────────────────────.

if ( ! function_exists( 'jinyu_register_image_sizes' ) ) {
	/**
	 * Jinyu_register_image_sizes()
	 *
	 * @return void 返回值
	 */
	function jinyu_register_image_sizes(): void {
		// 卡片 / 封面主图（首页、归档、相关阅读、轮播之外的常规展示位）.
		add_image_size( 'jinyu-cover', 768, 512, true );
		// 列表小缩略图（侧栏小工具、无限加载返回的缩略图位）.
		add_image_size( 'jinyu-thumb', 400, 267, true );
	}
}
add_action( 'after_setup_theme', 'jinyu_register_image_sizes' );

// ─────────────────────────────────────────────────────────────.
// 图片优化：WebP 交付（存储无关 · 双通道）.
// 通道 A（首选）：云端即时转码。后端支持时在 CDN 原图 URL 后追加转码指令.
// （又拍云 !/format/webp、阿里云 ?x-oss-process=image/format,webp、.
// 腾讯云/七牛 ?imageMogr2/format/webp），由 CDN 边缘产出 WebP，零源站负担.
// 通道 B（兜底）：后端不支持即时转码时，本地 GD 生成 WebP 并推送至存储（复用.
// 云端存储适配器）走 CDN；无存储则源站直出。全程失败回退原图，绝不裂图.
// URL 替换统一收口在 jinyu_img_to_webp_url()，仅处理本站 uploads 内 jpg/png.
// ─────────────────────────────────────────────────────────────.

if ( ! function_exists( 'jinyu_url2pid_ver' ) ) {
	/**
	 * 附件 URL→ID 映射的版本号：附件增/改/删时 +1，旧映射 key 全部自然失效。
	 * 请求内静态化，避免同请求多次读版本 key。
	 */
	function jinyu_url2pid_ver(): int {
		static $ver = null;
		if ( null === $ver ) {
			$ver = (int) jinyu_cache_get( 'url2pid_ver', 0 );
		}
		return $ver;
	}
}

if ( ! function_exists( 'jinyu_url2pid_bump_ver' ) ) {
	/** 附件变化时递增映射版本（挂 add/edit/delete_attachment）。 */
	function jinyu_url2pid_bump_ver(): void {
		jinyu_cache_set( 'url2pid_ver', (int) jinyu_cache_get( 'url2pid_ver', 0 ) + 1, 0 );
	}
	add_action( 'add_attachment', 'jinyu_url2pid_bump_ver' );
	add_action( 'edit_attachment', 'jinyu_url2pid_bump_ver' );
	add_action( 'delete_attachment', 'jinyu_url2pid_bump_ver' );
}

if ( ! function_exists( 'jinyu_url_to_postid' ) ) {
	/**
	 * Attachment_url_to_postid 的记忆化包装（请求内 + 跨请求双层）。
	 *
	 * WP 核心的 attachment_url_to_postid 每调用一次就直查一次 postmeta
	 * （meta_key='_wp_attached_file'），且无任何缓存：同一张图在轮播、封面、
	 * Srcset、LQIP、OG 等多处被重复反查，实测首页 19 次查询里仅 3 个不同文件。
	 * 双层缓存后同 URL 全站只打一次库：
	 * - 请求内：静态 $memo；
	 * - 跨请求：jinyu_ 缓存组（有 Memcached 持久命中，无则落 transient），
	 *   版本号在附件增/改/删时失效，TTL 7 天兜底。
	 *
	 * @param string $url 附件完整 URL
	 * @return int 附件 ID，查不到返回 0
	 */
	function jinyu_url_to_postid( $url ) {
		static $memo = [];
		$url         = (string) $url;
		if ( '' === $url ) {
			return 0;
		}
		if ( ! function_exists( 'attachment_url_to_postid' ) ) {
			return 0;
		}
		if ( array_key_exists( $url, $memo ) ) {
			return $memo[ $url ];
		}

		$key = 'url2pid_' . jinyu_url2pid_ver() . '_' . md5( $url );
		$hit = jinyu_cache_get( $key, null );
		if ( null !== $hit ) {
			$memo[ $url ] = (int) $hit;
			return $memo[ $url ];
		}

		$memo[ $url ] = (int) attachment_url_to_postid( $url );
		jinyu_cache_set( $key, $memo[ $url ], 7 * DAY_IN_SECONDS );
		return $memo[ $url ];
	}
}

if ( ! function_exists( 'jinyu_generate_webp_file' ) ) {
	/**
	 * 由 jpg/png 源文件生成同尺寸 WebP（质量 80），带原子锁防止并发重复生成。
	 *
	 * @param string $src 源文件绝对路径
	 * @param string $dst 目标 .webp 绝对路径
	 * @return bool 是否成功产出
	 */
	function jinyu_generate_webp_file( $src, $dst ) {
		$lock = $dst . '.lock';
		$lf   = @fopen( $lock, 'x' ); /* phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors.Discouraged -- 预期可能失败的操作（反序列化/JSON），静默处理 */
		if ( ! $lf ) {
			// 已有锁：超过 60s 视为异常残留，清理后重试一次；否则让当次请求回退原图.
			if ( (int) @filemtime( $lock ) < time() - 60 ) { /* phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors.Discouraged -- 预期可能失败的操作（反序列化/JSON），静默处理 */
				@unlink( $lock ); /* phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors.Discouraged -- 预期可能失败的操作（反序列化/JSON），静默处理 */
				$lf = @fopen( $lock, 'x' ); /* phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors.Discouraged -- 预期可能失败的操作（反序列化/JSON），静默处理 */
			}
			if ( ! $lf ) {
				return false;
			}
		}

		$ok = false;
		try {
			$info = @getimagesize( $src ); /* phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors.Discouraged -- 预期可能失败的操作（反序列化/JSON），静默处理 */
			if ( $info ) {
				switch ( $info[2] ) {
					case IMAGETYPE_JPEG:
						$img = @imagecreatefromjpeg( $src ); /* phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors.Discouraged -- 预期可能失败的操作（反序列化/JSON），静默处理 */
						break;
					case IMAGETYPE_PNG:
						$img = @imagecreatefrompng( $src ); /* phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors.Discouraged -- 预期可能失败的操作（反序列化/JSON），静默处理 */
						break;
					default:
						$img = null;
				}
				if ( $img ) {
					// 调色板图（PNG8）必须先转真彩，否则 imagewebp 输出异常.
					if ( function_exists( 'imagepalettetotruecolor' ) ) {
						imagepalettetotruecolor( $img );
					}
					if ( function_exists( 'imagealphablending' ) ) {
						imagealphablending( $img, true );
						imagesavealpha( $img, true );
					}
					$webp_quality = (int) jinyu_get_option( 'webp_quality', 80 );
					if ( $webp_quality < 1 || $webp_quality > 100 ) {
						$webp_quality = 80;
					}
					$ok = (bool) @imagewebp( $img, $dst, $webp_quality ); /* phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors.Discouraged -- 预期可能失败的操作（反序列化/JSON），静默处理 */
					// 不调 imagedestroy()：PHP 7.0 起它是空操作，GD 资源随作用域结束自动释放.
					// GD 偶发产出 0 字节文件，视为失败并清除.
					if ( $ok && (int) @filesize( $dst ) < 64 ) { /* phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors.Discouraged -- 预期可能失败的操作（反序列化/JSON），静默处理 */
						@unlink( $dst ); /* phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors.Discouraged -- 预期可能失败的操作（反序列化/JSON），静默处理 */
						$ok = false;
					}
				}
			}
		} catch ( \Throwable $e ) {
			$ok = false;
		}

		fclose( $lf ); /* phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- 配合上方 fopen 的关闭操作 */
		@unlink( $lock ); /* phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors.Discouraged -- 预期可能失败的操作（反序列化/JSON），静默处理 */
		return $ok;
	}
}

/*
─────────────────────────────────────────────────────────────
 * WebP 交付策略（存储无关 · 双通道）
 * 通道 A：云端即时转码（首选）。后端支持时在 CDN 原图 URL 后追加转码指令，
 *   由 CDN 边缘直接产出 WebP。零源站 GD、零推送、命中边缘缓存，最优。
 * 通道 B：本地 GD 生成 WebP 并推送至存储（复用 云端存储适配器）走 CDN；
 *   无存储则源站直出（push 模式 CDN 不会自动同步 webp）。
 * 全程错误安全：任何环节失败一律回退原图，绝不裂图。
 * ───────────────────────────────────────────────────────────── */

if ( ! function_exists( 'jinyu_storage_accel' ) ) {
	/**
	 * 对象存储加速配置的唯一读取入口（provider / domain / prefix）。
	 *
	 * 存储能力归属配套插件 jinyu-theme-companion（独立 option jinyu_companion_settings）：
	 * 插件启用时以插件配置为准；插件缺席时回落主题历史 option，保证老站点升级后不漏配
	 * （主题侧早已移除存储 UI，此处只读不写）。
	 *
	 * 「复原为本地链接」暂停重写期间，前台附件 URL 本来就是本地的，
	 * 因此视为未启用加速——否则主题仍会拿着 CDN 域名去还原 URL，白白多一层判定。
	 *
	 * @return array{provider:string,domain:string,prefix:string}
	 */
	function jinyu_storage_accel(): array {
		if ( function_exists( 'jinyu_storage_config' ) ) {
			if ( function_exists( 'jinyu_storage_rewrite_active' ) && ! jinyu_storage_rewrite_active() ) {
				return [
					'provider' => '',
					'domain'   => '',
					'prefix'   => '',
				];
			}
			$cfg = jinyu_storage_config();
			return [
				'provider' => (string) ( $cfg['provider'] ?? '' ),
				'domain'   => trim( (string) ( $cfg['domain'] ?? '' ) ),
				'prefix'   => trim( (string) ( $cfg['prefix'] ?? '' ) ),
			];
		}
		return [
			'provider' => (string) jinyu_get_option( 'storage_provider', '' ),
			'domain'   => trim( (string) jinyu_get_option( 'storage_domain', '' ) ),
			'prefix'   => trim( (string) jinyu_get_option( 'storage_prefix', '' ) ),
		];
	}
}

if ( ! function_exists( 'jinyu_webp_transform_suffix' ) ) {
	/**
	 * 当前存储后端支持的「即时转 WebP」指令后缀；不支持或加速域名未配置则返回 ''。
	 * 仅当存储加速域名已填写且后端已知时生效。
	 */
	function jinyu_webp_transform_suffix() {
		$accel    = jinyu_storage_accel();
		$provider = $accel['provider'];
		$domain   = $accel['domain'];
		if ( '' === $domain ) {
			return '';
		}
		switch ( $provider ) {
			case 'upyun':
				return '!/format/webp';
			case 'aliyun':
				return '?x-oss-process=image/format,webp';
			case 'tencent':
			case 'qiniu':
				return '?imageMogr2/format/webp';
			default:
				return '';
		}
	}
}

if ( ! function_exists( 'jinyu_strip_transform_suffix' ) ) {
	/**
	 * 剥离 CDN 即时转码指令后缀，把「带转码指令的 CDN URL」还原成源图 URL。
	 * 又拍云风格 `!/format/webp` 以 ! 开头、不含 ?#，preg_replace('/[?#].*$/') 无法剥除，
	 * 会导致 attachment_url_to_postid / 扩展名判定 / 降采样失效——这是全站 srcset 为 0 的根因。
	 * 阿里云/腾讯云/七牛的 ?x-oss-process=... 由 [?#].*$ 处理，此处一并兜底。
	 *
	 * @param string $url
	 * @return string
	 */
	function jinyu_strip_transform_suffix( $url ) {
		if ( ! is_string( $url ) || '' === $url ) {
			return $url;
		}
		// 又拍云「!」分隔的转码指令（!/format/webp 等）：剥到行尾.
		$url = preg_replace( '/!.*$/', '', $url );
		// 其它服务商的 ? 查询串 / # 锚点.
		$url = preg_replace( '/[?#].*$/', '', $url );
		return $url;
	}
}

if ( ! function_exists( 'jinyu_lqip_transform_suffix' ) ) {
	/**
	 * LQIP（Low-Quality Image Placeholder）转码指令后缀：在 CDN 边缘把原图缩到极小宽、
	 * 转 WebP，产出约 0.3KB 的模糊占位图。仅当存储后端支持即时转码时返回非空字符串。
	 */
	function jinyu_lqip_transform_suffix() {
		$provider = jinyu_storage_accel()['provider'];
		switch ( $provider ) {
			case 'upyun':
				return '!/fw/40/format/webp/quality/40';
			case 'aliyun':
				return '?x-oss-process=image/resize,w_40/format,webp/quality,q_40';
			case 'tencent':
			case 'qiniu':
				return '?imageMogr2/thumbnail/40x/format,webp/quality/40';
			default:
				return '';
		}
	}
}

if ( ! function_exists( 'jinyu_lqip_url' ) ) {
	/**
	 * 把任意“本站上传图”URL 转成极小 LQIP 占位图 URL，用于 blur-up 的 data-ph。
	 * 流程：还原存储加速域到本地 baseurl → 剥即时转码指令与 -WxH 尺寸后缀
	 * → 仅保留原图文件 → 由存储加速域在边缘实时缩放+转 WebP。
	 * 未配置存储加速域或不支持的图（外链/SVG）返回 ''，前端以纯色占位兜底，绝不下载原图。
	 *
	 * @param string $url
	 * @return string 约 0.3KB 的 webp 占位 URL，或 ''
	 */
	function jinyu_lqip_url( $url ) {
		if ( empty( $url ) || ! is_string( $url ) ) {
			return '';
		}
		$up = wp_get_upload_dir();
		if ( empty( $up['baseurl'] ) || empty( $up['basedir'] ) ) {
			return '';
		}
		// 把存储加速域还原回本地 baseurl.
		$local = (string) $url;
		$roots = array( rtrim( $up['baseurl'], '/' ) );
		$accel = jinyu_storage_accel();
		if ( $accel['domain'] !== '' ) {
			$sp      = trim( $accel['prefix'], '/' );
			$roots[] = rtrim( $accel['domain'], '/' ) . ( $sp !== '' ? '/' . $sp : '' );
		}
		foreach ( $roots as $root ) {
			if ( $root && strpos( $local, $root ) === 0 ) {
				$local = $up['baseurl'] . substr( $local, strlen( $root ) );
				break;
			}
		}
		if ( strpos( $local, $up['baseurl'] ) !== 0 ) {
			return ''; // 非本站上传图（外链/SVG/主题资源）.
		}
		// 剥即时转码指令（!/... 或 ?x-oss...），再剥 -WxH 尺寸后缀，回到原图文件.
		$local = jinyu_strip_transform_suffix( $local );
		$local = preg_replace( '/-\d+x\d+(?=\.(?:jpe?g|png|gif|webp)$)/i', '', $local );
		if ( ! preg_match( '/\.(jpe?g|png)$/i', $local ) ) {
			return ''; // GIF/WebP 等：LQIP 无意义，放弃占位.
		}
		$rel  = substr( $local, strlen( $up['baseurl'] ) ); // 如 /2026/09/x.png.
		$base = jinyu_webp_storage_cdn_url( $rel );
		if ( '' === $base ) {
			return ''; // 未配置存储加速域：放弃占位，纯色兜底.
		}
		$suffix = jinyu_lqip_transform_suffix();
		return $suffix === '' ? '' : $base . $suffix;
	}
}

if ( ! function_exists( 'jinyu_webp_storage_cdn_url' ) ) {
	/**
	 * 由上传相对路径拼出存储 CDN 原图 URL（不含转码指令）。
	 *
	 * @param string $rel 如 /2026/09/x.jpg
	 */
	function jinyu_webp_storage_cdn_url( $rel ) {
		$accel  = jinyu_storage_accel();
		$domain = rtrim( $accel['domain'], '/' );
		$prefix = trim( $accel['prefix'], '/' );
		if ( '' === $domain ) {
			return '';
		}
		return $domain . ( $prefix !== '' ? '/' . $prefix : '' ) . $rel;
	}
}

if ( ! function_exists( 'jinyu_webp_onthefly_cdn_url' ) ) {
	/**
	 * 由上传相对路径拼出「即时转 WebP」的 CDN URL（通道 A 产出）。
	 *
	 * @param string $rel 如 /2026/09/x.jpg
	 */
	function jinyu_webp_onthefly_cdn_url( $rel ) {
		$suffix = jinyu_webp_transform_suffix();
		if ( '' === $suffix ) {
			return '';
		}
		$base = jinyu_webp_storage_cdn_url( $rel );
		return $base === '' ? '' : $base . $suffix;
	}
}

if ( ! function_exists( 'jinyu_webp_onthefly_confirmed' ) ) {
	/**
	 * 一次性自检：确认即时转码确实返回 image/webp，防止后端配置被改动导致裂图。
	 * 结果按「服务商+指令」缓存 12h；网络异常时 fail-open（假定可用，避免误关 WebP）。
	 *
	 * @param string $sample_cdn_url 用于自检的样本 CDN 原图 URL
	 */
	function jinyu_webp_onthefly_confirmed( $sample_cdn_url ) {
		$suffix = jinyu_webp_transform_suffix();
		if ( '' === $suffix ) {
			return false;
		}
		$provider = jinyu_storage_accel()['provider'];
		$key      = 'otf_' . $provider . '_' . md5( $suffix );
		$cached   = jinyu_cache_get( $key, null );
		if ( null !== $cached ) {
			return (bool) $cached;
		}
		$ok = true; // fail-open：网络抖动不应误关 WebP.
		if ( '' !== $sample_cdn_url ) {
			$r = wp_remote_head(
				$sample_cdn_url . $suffix,
				array(
					'timeout'   => 5,
					'sslverify' => true,
				)
			);
			if ( ! is_wp_error( $r ) ) {
				$code = (int) wp_remote_retrieve_response_code( $r );
				$ct   = wp_remote_retrieve_header( $r, 'content-type' );
				$ok   = ( 200 === $code && stripos( (string) $ct, 'webp' ) !== false );
			}
		}
		jinyu_cache_set( $key, $ok ? 1 : 0, 12 * HOUR_IN_SECONDS );
		return $ok;
	}
}

if ( ! function_exists( 'jinyu_webp_enabled' ) ) {
	/**
	 * 是否启用主题自带 WebP 转换（后台「启用 WebP 自动转换」开关）。
	 * 默认开启；用户关闭后不再生成新 WebP、URL 改写全部回退原图。
	 *
	 * @return bool
	 */
	function jinyu_webp_enabled() {
		$v = jinyu_get_option( 'enable_webp', 1 );
		return ! empty( $v ) && '0' !== (string) $v;
	}
}

if ( ! function_exists( 'jinyu_img_to_webp_url' ) ) {
	/**
	 * 将本站 uploads 内的 jpg/png URL 映射为 WebP 交付 URL。
	 * 优先云端即时转码（通道 A），否则本地生成+推送/源站（通道 B），失败回退原图。
	 *
	 * @param string $url 图片 URL（可能为本地或 CDN 形式）
	 * @return string WebP 交付 URL 或原 URL
	 */
	function jinyu_img_to_webp_url( $url ) {
		if ( ! jinyu_webp_enabled() ) {
			return $url;
		}
		if ( empty( $url ) || ! is_string( $url ) ) {
			return $url;
		}

		$up = wp_get_upload_dir();
		if ( empty( $up['baseurl'] ) || empty( $up['basedir'] ) ) {
			return $url;
		}

		// 存储加速域名(storage_domain+prefix) 改写附件 URL，须还原回本地 baseurl 才能映射到本地文件.
		$roots = array();
		$accel = jinyu_storage_accel();
		if ( $accel['domain'] !== '' ) {
			$sp      = trim( $accel['prefix'], '/' );
			$roots[] = rtrim( $accel['domain'], '/' ) . ( $sp !== '' ? '/' . $sp : '' );
		}
		$local_url = $url;
		foreach ( $roots as $root ) {
			if ( $root && strpos( $url, $root ) === 0 ) {
				$local_url = $up['baseurl'] . substr( $url, strlen( $root ) );
				break;
			}
		}

		// 非本站上传目录（外链 / 主题资源 / SVG / GIF）→ 不处理.
		if ( strpos( $local_url, $up['baseurl'] ) !== 0 ) {
			return $url;
		}

		$path = jinyu_strip_transform_suffix( $local_url );
		if ( ! preg_match( '/\.(jpe?g|png)$/i', $path ) ) {
			return $url;
		}

		$rel = substr( $path, strlen( $up['baseurl'] ) ); // 如 /2026/09/x.png.
		$src = $up['basedir'] . wp_normalize_path( $rel );
		if ( ! is_file( $src ) ) {
			return $url;
		}

		// ── 通道 A：云端即时转码（首选，零源站 GD / 零推送）──.
		$otf = jinyu_webp_onthefly_cdn_url( $rel );
		if ( '' !== $otf && jinyu_webp_onthefly_confirmed( jinyu_webp_storage_cdn_url( $rel ) ) ) {
			return $otf;
		}

		// ── 通道 B：本地生成（预算上限防首访超时），再推送存储或源站分发 ──.
		if ( ! extension_loaded( 'gd' ) || ! function_exists( 'imagewebp' ) ) {
			return $url;
		}
		$dst    = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $src );
		$dstRel = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $rel );

		// 单次请求内同步生成的 WebP 数量上限：存量多图首访时若全量即时生成，.
		// 会串行跑大量 GD 转换导致请求超时 / CPU 突刺。超出预算回退原图，.
		// 后续请求（预算重置）继续补齐.
		static $budget = 80;
		if ( ! is_file( $dst ) ) {
			if ( $budget <= 0 ) {
				return $url;
			}
			--$budget;
			jinyu_generate_webp_file( $src, $dst );
			if ( ! is_file( $dst ) ) {
				return $url;
			}
		}

		// 通道 B：本地生成 WebP 后只返回源站 URL.
		// 设计铁律：渲染路径（每次请求）绝不同步推送。把"部署动作"（推送文件上云）塞进.
		// "渲染动作"（拼 URL）会造成首屏 62 次同步 PUT≈15s 卡顿，且与用户意图相悖——.
		// 用户填了 CDN 域名、点「一键替换为 CDN 链接」只是改写前端 URL（见 companion.
		// storage.php 的 wp_get_attachment_url 过滤器），文件实际上云由后台「主动推送」功能.
		// 负责（companion storage.php:1132 的 AJAX 一键推送，批内并行）。二者彻底解耦.
		return preg_replace( '/\.(jpe?g|png)$/i', '.webp', $path );
	}
}

// 上传时同步生成 WebP 副本（是否替换输出 URL 由 jinyu_img_to_webp_url 统一决定）.
add_filter(
	'wp_handle_upload',
	function ( $result ) {
		if ( empty( $result['file'] ) || ! jinyu_webp_enabled() || ! extension_loaded( 'gd' ) || ! function_exists( 'imagewebp' ) ) {
			return $result;
		}
		if ( ! preg_match( '/\.(jpe?g|png)$/i', $result['file'] ) ) {
			return $result;
		}

		$dst = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $result['file'] );
		if ( ! is_file( $dst ) ) {
			jinyu_generate_webp_file( $result['file'], $dst );
		}
		return $result;
	}
);

// 图片懒加载增强 + blur-up 淡入占位.
// 给所有 WP 媒体图（缩略图/相册/附件输出）追加 .jinyu-blur-img 类，.
// 并用 thumbnail 尺寸小图作 data-ph 模糊占位（前端 blur-up 淡入）.
add_filter(
	'wp_get_attachment_image_attributes',
	function ( $attrs, $attachment, $size ) {
		if ( is_admin() ) {
			return $attrs;
		}
		$cl = isset( $attrs['class'] ) ? $attrs['class'] : '';
		if ( strpos( $cl, 'jinyu-blur-img' ) === false ) {
			$attrs['class'] = trim( $cl . ' jinyu-blur-img' );
		}
		if ( empty( $attrs['decoding'] ) ) {
			$attrs['decoding'] = 'async';
		}
		// 附件图 src 同步替换为 WebP（logo、原生相册等走此处；外链/SVG 自动跳过）.
		if ( ! empty( $attrs['src'] ) ) {
			$w = jinyu_img_to_webp_url( $attrs['src'] );
			if ( $w !== $attrs['src'] ) {
				$attrs['src'] = $w;
			}
		}
		$t = wp_get_attachment_image_src( $attachment->ID, 'thumbnail' );
		if ( $t && ! empty( $t[0] ) ) {
			$attrs['data-ph'] = jinyu_lqip_url( $t[0] );
		}
		return $attrs;
	},
	10,
	3
);

if ( ! function_exists( 'jinyu_webp_replace_html_imgs' ) ) {
	/**
	 * 对一段 HTML 内所有 <img> 执行 WebP URL 替换（src + srcset），供 the_content 之外的
	 * HTML 输出点复用：广告代码块、文本/块小工具、短代码等。SVG / data-uri 自动跳过。
	 *
	 * @param mixed $html mixed 参数。
	 */
	function jinyu_webp_replace_html_imgs( $html ) {
		if ( empty( $html ) ) {
			return $html;
		}
		if ( ! extension_loaded( 'gd' ) || ! function_exists( 'imagewebp' ) ) {
			return $html;
		}
		if ( stripos( $html, '<img' ) === false ) {
			return $html;
		}

		$doc  = new DOMDocument();
		$prev = libxml_use_internal_errors( true );
		$doc->loadHTML( '<meta charset="utf-8">' . $html, LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );

		foreach ( $doc->getElementsByTagName( 'img' ) as $img ) {
			$src = $img->getAttribute( 'src' );
			if ( ! $src || preg_match( '/\.svg(\?|$)/i', $src ) || strpos( $src, 'data:' ) === 0 ) {
				continue;
			}
			$webpSrc = jinyu_img_to_webp_url( $src );
			if ( $webpSrc !== $src ) {
				$img->setAttribute( 'src', $webpSrc );
				$srcset = $img->getAttribute( 'srcset' );
				if ( $srcset ) {
					$newSet = preg_replace_callback(
						'/(\S+\.(?:jpe?g|png))(?=\s|$|\s+\d+[wx])/i',
						function ( $m ) {
							return jinyu_img_to_webp_url( $m[1] );
						},
						$srcset
					);
					if ( $newSet !== $srcset ) {
						$img->setAttribute( 'srcset', $newSet );
					}
				}
			}
		}

		$body = $doc->getElementsByTagName( 'body' )->item( 0 );
		if ( $body ) {
			$out = '';
			foreach ( $body->childNodes as $node ) {
				$out .= $doc->saveHTML( $node );
			}
			return $out;
		}
		return $html;
	}
}

if ( ! function_exists( 'jinyu_is_safe_remote_image_url' ) ) {
	/**
	 * 判断一个 URL 是否可以安全地发起远程图片请求（SSRF 门禁）。
	 *
	 * 背景：主题里有两条路径会对「图片 URL」发起服务端请求 ——
	 *   1. jinyu_external_cover_dead_probe()（post-meta.php）探测外链封面是否失效
	 *   2. jinyu_image_size()（本文件）取原始宽高以输出 <img width/height>
	 * 而这些 URL 的来源包括**文章正文第一张 <img>**（见 jinyu_resolve_cover_source()），
	 * 也就是**作者/投稿人可控**。作者在正文里写
	 * `<img src="http://169.254.169.254/latest/meta-data/iam/">`，
	 * 访客打开文章即会让服务端去打云元数据端点或内网地址。
	 *
	 * 为什么必须在这里统一拦：getimagesize() 走 PHP stream，
	 * **不受 WP 的 wp_http_validate_url() / http_request_host_is_external() 约束**，
	 * 也无法被 WP_HTTP_BLOCK_EXTERNAL 拦截 —— 主题层不拦就等于没有防护。
	 *
	 * 判定：仅允许 http/https；主机解析到内网/保留段（127.0.0.1、10.x、192.168.x、
	 * 169.254.x 等）一律拒绝。域名用 gethostbyname() 解析后校验（仅 IPv4；
	 * 纯 IPv6 主机解析不到 A 记录时按拒绝处理 —— 对探测失败从严，不冒内网风险）。
	 *
	 * @param string $url 待校验的 URL。
	 * @return bool true=可安全请求；false=必须拒绝。
	 */
	function jinyu_is_safe_remote_image_url( string $url ): bool {
		$url = trim( $url );
		if ( '' === $url ) {
			return false;
		}

		$parts  = wp_parse_url( $url );
		$scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
		$host   = (string) ( $parts['host'] ?? '' );
		if ( ! in_array( $scheme, [ 'http', 'https' ], true ) || '' === $host ) {
			return false;
		}

		// 主机为字面 IP 时直接校验；域名解析后校验。
		$ip = filter_var( $host, FILTER_VALIDATE_IP ) ? $host : gethostbyname( $host );
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			return false;
		}

		// 解析到公网 IP 后仍需确认原始 host 不是被 CNAME 到内网的伪装
		// （gethostbyname 已跟随 CNAME，这里再比对一次 host 字面量以防歧义）。
		if ( filter_var( $host, FILTER_VALIDATE_IP ) && $host !== $ip ) {
			return false;
		}

		return true;
	}
}

if ( ! function_exists( 'jinyu_image_size' ) ) {
	/**
	 * 取图片的原始宽高，供 <img> 输出 width/height。
	 *
	 * 不写尺寸时，图片加载完成前占位宽度为 0，加载后突然撑开容器，产生 CLS
	 * （Core Web Vitals 的累积布局偏移，直接影响 SEO 与移动端体验）；
	 * 补上原始宽高后浏览器按 aspect-ratio 预留空间，加载期间不再跳动
	 * （显示尺寸仍由 CSS 决定，这里只补「原始比例」信息）。
	 *
	 * 取值顺序：附件元数据（零额外 IO）→ 远程图头（外链 / CDN 场景）。
	 * 结果按 URL 缓存 12 小时，避免每个请求都查一次库。
	 *
	 * @param string $url 图片 URL（本地上传或 CDN 地址均可）。
	 * @return array{0:int,1:int} 宽高；取不到时 [0, 0]，调用方据此省略属性、保持原行为。
	 */
	function jinyu_image_size( $url ) {
		$url  = (string) $url;
		$size = array( 0, 0 );
		if ( '' === $url ) {
			return $size;
		}

		$cache_key = 'img_size_' . md5( $url );
		$cached    = jinyu_cache_get( $cache_key );
		if ( is_array( $cached ) && isset( $cached[0], $cached[1] ) ) {
			return array( (int) $cached[0], (int) $cached[1] );
		}

		// 优先读附件元数据（零额外 IO）；未命中再退化为直读图片头（外链 / CDN 场景）。
		$attach_id = function_exists( 'attachment_url_to_postid' ) ? attachment_url_to_postid( $url ) : 0;
		if ( $attach_id ) {
			$meta = wp_get_attachment_image_src( $attach_id, 'full' );
			if ( ! empty( $meta[1] ) && ! empty( $meta[2] ) ) {
				$size = array( (int) $meta[1], (int) $meta[2] );
			}
		}
		if ( ! $size[0] ) {
			// SSRF 门禁：$url 可能来自文章正文首图（作者级可写），getimagesize() 走 PHP stream
			// 不受 WP 的 http 校验约束，必须自己挡内网 / 云元数据端点。
			// 挡下后 fail-open（返回 [0,0]）—— 调用方据此省略 width/height，行为与取不到尺寸一致。
			if ( ! jinyu_is_safe_remote_image_url( $url ) ) {
				return $size;
			}
			$info = @getimagesize( $url ); /* phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors.Discouraged -- 预期可能失败的操作（取远程图头），静默处理 */
			if ( ! empty( $info[0] ) && ! empty( $info[1] ) ) {
				$size = array( (int) $info[0], (int) $info[1] );
			}
		}

		jinyu_cache_set( $cache_key, $size, 12 * HOUR_IN_SECONDS );
		return $size;
	}
}

if ( ! function_exists( 'jinyu_logo_image_size' ) ) {
	/**
	 * Logo 图尺寸（jinyu_image_size 的语义别名，保留以维持既有调用点不变）。
	 *
	 * @param string $url Logo 图 URL。
	 * @return array{0:int,1:int} 宽高；取不到时 [0, 0]。
	 */
	function jinyu_logo_image_size( $url ) {
		return jinyu_image_size( $url );
	}
}
