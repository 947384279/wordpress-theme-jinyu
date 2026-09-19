<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 主题更新通道：接入 WP 原生「主题更新」机制，并加入供应链防护。
 * - 让仪表盘「有可用更新」红条与一键升级可用，不再只是后台手动下载链接。
 * - 数据来源于 JINYU_UPDATE_SERVER 返回的版本 JSON，与后台「检查更新」共用同一数据源。
 * - 防护：① JSON 经 RSA-SHA256 签名，主题内置公钥验签，防更新源被篡改指向恶意包；
 *        ② 更新包 zip 带 sha256，下载后校验，防包被替换投毒。
 *
 * 期望 JSON：
 * {"version":"1.1.0","changelog":"...","download_url":"https://.../theme.zip",
 *  "detail_url":"...","hash":"<sha256 of zip>","signature":"<base64(RSA-SHA256(payload))>"}
 *
 * 签名载荷（按固定字段顺序，以 ASCII 0x1F 分隔）：
 *   version \x1F changelog \x1F download_url \x1F detail_url \x1F hash
 */

/**
 * 验签公钥（仅公钥随主题分发，私钥仅发布者持有，盗版者无法伪造签名）。
 */
define(
	'JINYU_UPDATE_PUBKEY',
	<<<JINYU_PUBKEY
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAqLakGXi75f8FkiajP3La
C85un5mTfg/KC9I6sadx895kG1yf1zirVqml5li+r+SX/pUWWfZnRunPewhXV/UC
yDbBwP3MmkydDQQpy5en4CXjfbWQN974NjL2XQpBUQhPDvtXi6GSy1iG9J3jnxn4
mS1dK1ceb6C+5ulDqZPVn1zeWzqbXhRX8BFWviSNUDqQSZiRfZL9syGNHpbUt2ey
8cnrvkOUJU8IQRhxYiTpF0aBNNWQK8lw5AUIrgSi+IeEmyXj/0iX0ab/0v0O7pXy
B23GHORyOPUrorod65Au2hWt8PYsEvQSMm0YK7gToj+67RmAddUxtKm0zYW9pIyl
pwIDAQAB
-----END PUBLIC KEY-----
JINYU_PUBKEY
);

if ( ! function_exists( 'jinyu_update_signing_payload' ) ) {
	/**
	 * 构造待签名/验签载荷：固定字段顺序 + 0x1F 分隔，两端字节一致。
	 *
	 * @param array $data 解析后的 JSON 数组。
	 * @return string
	 */
	function jinyu_update_signing_payload( array $data ): string {
		$fields = [ 'version', 'changelog', 'download_url', 'detail_url', 'hash' ];
		$parts  = [];
		foreach ( $fields as $f ) {
			$parts[] = isset( $data[ $f ] ) ? (string) $data[ $f ] : '';
		}

		return implode( "\x1f", $parts );
	}
}

if ( ! function_exists( 'jinyu_verify_update_signature' ) ) {
	/**
	 * 用内置公钥验证 JSON 签名。
	 *
	 * @param array $data 解析后的 JSON 数组（含 signature 字段）。
	 * @return bool
	 */
	function jinyu_verify_update_signature( array $data ): bool {
		if ( empty( $data['signature'] ) || ! defined( 'JINYU_UPDATE_PUBKEY' ) ) {
			return false;
		}
		$sig = base64_decode( $data['signature'], true );
		if ( false === $sig ) {
			return false;
		}
		$payload = jinyu_update_signing_payload( $data );
		$result  = openssl_verify( $payload, $sig, JINYU_UPDATE_PUBKEY, OPENSSL_ALGO_SHA256 );

		return 1 === $result;
	}
}

if ( ! function_exists( 'jinyu_fetch_update_info' ) ) {
	/**
	 * 拉取并解析更新服务器返回的版本 JSON（含签名校验）。
	 *
	 * @param bool $force 跳过 1 小时缓存强制重新拉取（后台「检查更新」用）。
	 * @return array|null 成功且验签通过返回数据数组，任何异常返回 null。
	 */
	function jinyu_fetch_update_info( bool $force = false ): ?array {
		$url = trim( (string) ( defined( 'JINYU_UPDATE_SERVER' ) ? JINYU_UPDATE_SERVER : '' ) );
		if ( '' === $url ) {
			return null;
		}

		$cache_key = 'jinyu_update_info';
		if ( ! $force ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached ) {
				return is_array( $cached ) ? $cached : null;
			}
		} else {
			delete_transient( $cache_key );
		}

		$resp = wp_remote_get(
			$url,
			[
				'timeout' => 15,
				'headers' => [ 'Accept' => 'application/json' ],
			]
		);
		if ( is_wp_error( $resp ) ) {
			set_transient( 'jinyu_update_verify_failed', false, HOUR_IN_SECONDS );
			set_transient( $cache_key, 'err', HOUR_IN_SECONDS );

			return null;
		}
		if ( 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) {
			set_transient( 'jinyu_update_verify_failed', false, HOUR_IN_SECONDS );
			set_transient( $cache_key, 'err', HOUR_IN_SECONDS );

			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( ! is_array( $data ) || empty( $data['version'] ) ) {
			set_transient( 'jinyu_update_verify_failed', false, HOUR_IN_SECONDS );
			set_transient( $cache_key, 'err', HOUR_IN_SECONDS );

			return null;
		}

		// 签名校验：未通过一律视为不可信，阻止任何升级提示。
		if ( ! jinyu_verify_update_signature( $data ) ) {
			set_transient( 'jinyu_update_verify_failed', true, HOUR_IN_SECONDS );
			set_transient( $cache_key, 'err', HOUR_IN_SECONDS );

			return null;
		}

		delete_transient( 'jinyu_update_verify_failed' );
		set_transient( $cache_key, $data, HOUR_IN_SECONDS );

		return $data;
	}
}

if ( ! function_exists( 'jinyu_inject_theme_update' ) ) {
	/**
	 * 将自有更新信息注入 WP 主题更新 transient，驱动原生更新提示与一键升级。
	 *
	 * @param object $transient update_themes transient 对象。
	 * @return object
	 */
	function jinyu_inject_theme_update( $transient ) {
		if ( empty( $transient ) || ! is_object( $transient ) ) {
			return $transient;
		}

		$slug  = get_template(); // 主题目录名（通常为 jinyu）
		$theme = wp_get_theme( $slug );
		if ( ! $theme || ! $theme->exists() ) {
			return $transient;
		}

		$info = jinyu_fetch_update_info();
		if ( null === $info ) {
			return $transient;
		}

		$current = (string) $theme->get( 'Version' );
		$latest  = trim( (string) $info['version'] );

		// 标记已检查，避免 WP 回退到 wp.org 查询（本主题不在 wp.org）。
		$transient->checked[ $slug ] = $current;

		if ( version_compare( $latest, $current, '>' ) ) {
			$transient->response[ $slug ] = [
				'theme'        => $slug,
				'new_version'  => $latest,
				'url'          => ! empty( $info['detail_url'] ) ? $info['detail_url'] : '',
				'package'      => ! empty( $info['download_url'] ) ? $info['download_url'] : '',
				'requires'     => '7.1',
				'requires_php' => '8.0',
				'jinyu_hash'   => ! empty( $info['hash'] ) ? strtolower( (string) $info['hash'] ) : '',
			];
		} else {
			// 已是最新：从响应中移除，确保不残留旧提示。
			unset( $transient->response[ $slug ] );
		}

		return $transient;
	}

	// 更新检查（含 cron 与手动「检查更新」）与读取时均注入。
	add_filter( 'pre_set_site_transient_update_themes', 'jinyu_inject_theme_update' );
	add_filter( 'site_transient_update_themes', 'jinyu_inject_theme_update' );
}

if ( ! function_exists( 'jinyu_upgrader_pre_download' ) ) {
	/**
	 * 一键升级下载前，校验更新包 sha256 是否与 JSON 中声明的 hash 一致。
	 * 不一致直接中止安装，阻断供应链投毒。
	 *
	 * @param false|string|WP_Error $reply   默认 false（交给 WP 自带下载）。
	 * @param string                $url     待下载的包地址。
	 * @param \WP_Upgrader          $upgrader 升级器实例。
	 * @return false|string|WP_Error
	 */
	function jinyu_upgrader_pre_download( $reply, $url, $upgrader ) {
		if ( false !== $reply ) {
			return $reply;
		}

		$transient = get_site_transient( 'update_themes' );
		if ( empty( $transient->response ) || ! is_array( $transient->response ) ) {
			return $reply;
		}

		$slug = get_template();
		if ( empty( $transient->response[ $slug ]['package'] )
			|| $transient->response[ $slug ]['package'] !== $url
		) {
			return $reply;
		}

		$expected = ! empty( $transient->response[ $slug ]['jinyu_hash'] )
			? strtolower( (string) $transient->response[ $slug ]['jinyu_hash'] )
			: '';
		if ( '' === $expected ) {
			// 无 hash 声明时不阻断（理论上签名已含 hash 字段，此分支仅作兜底）。
			return $reply;
		}

		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$tmp = download_url( $url );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}

		if ( strtolower( (string) hash_file( 'sha256', $tmp ) ) !== $expected ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

			return new \WP_Error(
				'jinyu_hash_mismatch',
				__( '更新包校验失败（sha256 不匹配），已阻止安装以防供应链投毒。', JINYU )
			);
		}

		// WP 解压按内容识别，但部分路径会检查 .zip 后缀（手动上传等）。
		// download_url 生成的临时文件已被去扩展名，改名成 .zip 以兼容所有 WP 版本。
		$zip_path = $tmp;
		if ( ! preg_match( '/\.zip$/i', $tmp ) && function_exists( 'rename' ) ) {
			$renamed = $tmp . '.zip';
			if ( @rename( $tmp, $renamed ) && file_exists( $renamed ) ) { // phpcs:ignore
				$zip_path = $renamed;
			}
		}

		// 返回本地临时文件路径，WP 直接用之解压安装。
		return $zip_path;
	}

	add_filter( 'upgrader_pre_download', 'jinyu_upgrader_pre_download', 10, 3 );
}

if ( ! function_exists( 'jinyu_upgrader_source_selection' ) ) {
	/**
	 * 修复一键升级时 zip 顶层目录名与主题 slug 不一致导致的「主题安装失败」。
	 * 将解压出的目录重命名为标准主题目录名。
	 *
	 * @param string       $source       解压源目录。
	 * @param string       $remote_source 临时解压根目录。
	 * @param \WP_Upgrader $upgrader     升级器实例。
	 * @param array        $hook_extra   升级上下文（含 theme slug）。
	 * @return string
	 */
	function jinyu_upgrader_source_selection( $source, $remote_source, $upgrader, $hook_extra ) {
		if ( empty( $hook_extra['theme'] ) || $hook_extra['theme'] !== get_template() ) {
			return $source;
		}
		if ( empty( $source ) || empty( $remote_source ) || ! is_dir( $source ) ) {
			return $source;
		}

		$slug       = get_template();
		$new_source = trailingslashit( $remote_source ) . $slug . '/';
		if ( $source === $new_source || is_dir( $new_source ) ) {
			return $source;
		}

		global $wp_filesystem;
		if ( $wp_filesystem && method_exists( $wp_filesystem, 'move' ) ) {
			$wp_filesystem->move( $source, $new_source );
		} elseif ( function_exists( 'rename' ) ) {
			@rename( $source, $new_source ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}

		return is_dir( $new_source ) ? $new_source : $source;
	}

	add_filter( 'upgrader_source_selection', 'jinyu_upgrader_source_selection', 10, 4 );
}
