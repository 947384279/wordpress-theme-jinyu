<?php
/**
 * 主题功能：feed.php
 *
 * 给 RSS 补两样东西：
 *   1. 缩略图 —— 正文前置 <figure> 图，并向 rss2 / atom 注入 <enclosure>，
 *      让 Feedly / Inoreader / Reeder 等阅读器直接显示封面；
 *   2. 前台订阅入口 —— 由 jinyu_footer_social() 兜底追加（见 inc/fun/template-tags.php）。
 *
 * 作者信息不在本文件处理：WordPress 核心 rss2_item() / atom_entry() 默认已输出
 * <dc:creator> 与 <author><name>，重复注入会产生非法 XML 节点，故直接沿用核心行为。
 *
 * @package         WordPress
 * @subpackage      Jinyu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'jinyu_feed_image_url' ) ) {
	/**
	 * 取文章在 feed 里使用的首图地址。
	 *
	 * 取图优先级：特色图 > 正文首个 <img> > 所属附件的第一张图。
	 * 正文里的相对路径会被补成绝对地址，否则阅读器按自身域名请求必然 404。
	 *
	 * @param int|WP_Post|null $post 可选；默认取当前文章。
	 * @return string 图片绝对 URL；无可用图时返回空串。
	 */
	function jinyu_feed_image_url( $post = null ): string {
		$post = get_post( $post );
		if ( ! $post instanceof WP_Post ) {
			return '';
		}

		// 1) 特色图：直接读 _thumbnail_id 元数据再取附件原图地址.
		// 刻意不用 get_post_thumbnail_url()：它内部会走 image_downsize() 生成/读取子尺寸，
		// 在 feed 这种要逐篇跑的批量场景里开销大，个别服务器还会因此内存耗尽或超时返回 500.
		if ( post_type_supports( $post->post_type, 'thumbnail' ) ) {
			$thumb_id = (int) get_post_meta( $post->ID, '_thumbnail_id', true );
			if ( $thumb_id > 0 ) {
				$thumb = (string) wp_get_attachment_url( $thumb_id );
				if ( '' !== $thumb ) {
					return $thumb;
				}
			}
		}

		// 2) 正文内第一个 <img src="...">（只抽 src 属性值，不解析其余标记）.
		if ( preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/i', (string) $post->post_content, $m ) ) {
			$src = trim( (string) $m[1] );

			// 相对路径要补站点域名，否则阅读器按自身域名请求必然 404.
			if ( '/' === substr( $src, 0, 1 ) ) {
				return home_url( $src );
			}
			if ( '' !== $src ) {
				return $src;
			}
		}

		// 3) 所属附件的第一张图片.
		$attach_ids = get_posts(
			[
				'post_parent'    => $post->ID,
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
				'post_status'    => 'inherit',
				'fields'         => 'ids',
				'orderby'        => 'menu_order ID',
				'order'          => 'ASC',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
			]
		);

		if ( ! empty( $attach_ids[0] ) ) {
			$url = (string) wp_get_attachment_url( (int) $attach_ids[0] );
			if ( '' !== $url ) {
				return $url;
			}
		}

		return '';
	}
}

if ( ! function_exists( 'jinyu_feed_image_type' ) ) {
	/**
	 * 按扩展名推断图片 MIME，用于 enclosure 的 type 字段。
	 *
	 * @param string $url 图片地址。
	 * @return string MIME 类型；无法识别时返回 image/octet-stream。
	 */
	function jinyu_feed_image_type( string $url ): string {
		$ext = strtolower( (string) pathinfo( wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );

		$map = [
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'gif'  => 'image/gif',
			'webp' => 'image/webp',
			'avif' => 'image/avif',
			'svg'  => 'image/svg+xml',
		];

		return $map[ $ext ] ?? 'image/octet-stream';
	}
}

if ( ! function_exists( 'jinyu_feed_enclosure' ) ) {
	/**
	 * 生成 RSS enclosure 标记。
	 *
	 * enclosure 的 length 对阅读器无实际用途（不下载就拿不到字节数），
	 * 规范要求必填，故统一写 0，避免伪造一个错误长度。
	 *
	 * @param string $url 图片地址。
	 * @return string enclosure XML 片段。
	 */
	function jinyu_feed_enclosure( string $url ): string {
		// XML 属性值里裸 & 会让 feed 解析失败，必须转义成 &amp;.
		$safe = str_replace( '&', '&amp;', esc_url_raw( $url ) );

		return '<enclosure url="' . $safe . '" length="0" type="' . jinyu_feed_image_type( $url ) . '" />';
	}
}

if ( ! function_exists( 'jinyu_feed_atom_enclosure' ) ) {
	/**
	 * 生成 Atom 的 enclosure 标记。
	 *
	 * Atom 不接受 RSS 的 <enclosure>，封面只能用 <link rel="enclosure" /> 表达。
	 *
	 * @param string $url 图片地址。
	 * @return string Atom XML 片段。
	 */
	function jinyu_feed_atom_enclosure( string $url ): string {
		$safe = str_replace( '&', '&amp;', esc_url_raw( $url ) );

		return '<link rel="enclosure" href="' . $safe . '" length="0" type="' . jinyu_feed_image_type( $url ) . '" />';
	}
}

if ( ! function_exists( 'jinyu_is_feed_view' ) ) {
	/**
	 * 判断当前是否处于「文章 feed」视图（评论 feed 不算）。
	 *
	 * @return bool
	 */
	function jinyu_is_feed_view(): bool {
		// 顶层加载阶段 is_feed() 还拿不到准确结果，故只由钩子回调内部调用.
		return function_exists( 'is_feed' ) && is_feed() && ! is_comment_feed();
	}
}

if ( ! function_exists( 'jinyu_feed_thumb_enabled' ) ) {
	/**
	 * 判断是否给 feed 注入缩略图。
	 *
	 * 配套插件 jinyu-theme-companion 可用 jinyu_feed_thumbnail 钩子关掉。
	 *
	 * @return bool
	 */
	function jinyu_feed_thumb_enabled(): bool {
		/**
		 * 是否启用 feed 缩略图（默认启用）。
		 *
		 * @param bool $enabled 是否启用。
		 */
		return (bool) apply_filters( 'jinyu_feed_thumbnail', true );
	}
}

// 注入点统一挂在钩子上，是否处于 feed 上下文由回调内部判断：
// functions.php 顶层加载时 query 还没解析，此刻调 is_feed() 得到的必然是错的结果。
// 评论 feed 排除在外，避免给评论项注入无意义的图片.

/**
 * 输出当前文章的封面图地址；非 feed 视图或无图时返回空串。
 *
 * @return string
 */
function jinyu_feed_current_image(): string {
	if ( ! jinyu_is_feed_view() || ! jinyu_feed_thumb_enabled() ) {
		return '';
	}

	return jinyu_feed_image_url();
}

/**
 * 拼一张封面 <figure>；内容里已有同图时原样返回，避免重复显示.
 *
 * @param string $content 原有内容。
 * @param string $url     图片地址。
 * @return string
 */
function jinyu_feed_figure( string $content, string $url ): string {
	if ( '' === $url || false !== strpos( $content, $url ) ) {
		return $content;
	}

	return '<figure class="jinyu-feed-thumb"><img src="' . esc_url( $url ) . '" alt="' . esc_attr( get_the_title() ) . '" /></figure>' . $content;
}

// ① 封面进 <description>：后台开启「摘要」时 feed 只有 description，
//    图放这里才能在 Feedly / Inoreader 里当封面显示.
add_filter(
	'the_excerpt_rss',
	function ( string $content ): string {
		if ( '' === trim( $content ) ) {
			return $content;
		}

		return jinyu_feed_figure( $content, jinyu_feed_current_image() );
	},
	10
);

// ② 封面进 <content:encoded>：后台关掉「摘要」时走这条，正文里就带图了.
add_filter(
	'the_content_feed',
	function ( string $content ): string {
		if ( '' === trim( $content ) ) {
			return $content;
		}

		return jinyu_feed_figure( $content, jinyu_feed_current_image() );
	},
	10
);

// ③ enclosure：WordPress 7.x 起 rss2_item / atom_entry 已由 apply_filters 改为 do_action，
//    继续用 add_filter 挂上去不会报错，但内容永远不会被输出（静默失效）.
add_action(
	'rss2_item',
	function (): void {
		$url = jinyu_feed_current_image();
		if ( '' === $url ) {
			return;
		}

		// 输出的是手工构造的 XML 片段，内容已按 XML 属性规则转义，无需 esc_echo.
		echo jinyu_feed_enclosure( $url ); /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 手工构造的 XML，内部已转义 */
	},
	20
);

add_action(
	'atom_entry',
	function (): void {
		$url = jinyu_feed_current_image();
		if ( '' === $url ) {
			return;
		}

		echo jinyu_feed_atom_enclosure( $url ); /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 手工构造的 XML，内部已转义 */
	},
	20
);
