<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 自动内链（Auto Internal Links）
 * --------------------------------------------------------------------------
 * 把正文里出现的「其它已发布文章标题」自动转成指向该文章的站内链接，
 * 是 WPJAM 所说的「链接建设」里最实在的站内一环：把权重在站点内部打通，
 * 也帮搜索引擎/AI  crawler 发现更多页面。
 *
 * 安全边界（避免变成屎山/误伤正文）：
 *  - 仅对单篇文章正文（the_content）生效，feed / 后台 / ajax 跳过；
 *  - 关键词索引（标题→链接）缓存为 transient，发布/更新/删除文章时失效重建，
 *    不每篇实时全表扫描；
 *  - 长词优先匹配，避免短词先占位把长词切碎；
 *  - 每个关键词整篇只链第一次出现；单篇总链接数上限 JINYU_AUTO_LINK_LIMIT；
 *  - 跳过已在 <a> 内、以及 <h1-6>/<pre>/<code>/<script>/<style>/<button> 内的文本；
 *  - 不链当前文章自身（自链无意义）。
 */

if ( ! defined( 'JINYU_AUTO_LINK_LIMIT' ) ) {
	define( 'JINYU_AUTO_LINK_LIMIT', 5 );
}

/**
 * 构建关键词索引：去重小写标题 => ['id'=>, 'url'=>]
 * 结果缓存为 transient（每日过期），内容变更时主动删除。
 */
if ( ! function_exists( 'jinyu_auto_link_map' ) ) {
	function jinyu_auto_link_map(): array {
		$cached = get_transient( 'jinyu_auto_link_map' );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$map   = [];
		$posts = get_posts( [
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		] );

		// 一次性预热所有文章对象（1~2 条 SQL），避免下面循环里每个 ID 各触发一次 get_post 查询（N+1）。
		// 重建映射发生在内容变更后首次访问，大站点若不预热会有明显查询尖峰。
		if ( ! empty( $posts ) ) {
			_prime_post_caches( $posts, 'post', false );
		}

		foreach ( $posts as $pid ) {
			$title = get_the_title( $pid );
			$key   = mb_strtolower( trim( $title ), 'UTF-8' );
			// 过短标题太泛，跳过，避免大量误链
			if ( mb_strlen( $key, 'UTF-8' ) < 3 ) {
				continue;
			}
			// 仅保留首个（最早）匹配，标题互相包含时不会乱链
			if ( ! isset( $map[ $key ] ) ) {
				$map[ $key ] = [
					'id'  => (int) $pid,
					'url' => get_permalink( $pid ),
				];
			}
		}

		set_transient( 'jinyu_auto_link_map', $map, DAY_IN_SECONDS );
		return $map;
	}
}

/**
 * the_content 过滤器：注入自动内链。
 */
if ( ! function_exists( 'jinyu_auto_link_content' ) ) {
	function jinyu_auto_link_content( $content ) {
		if ( is_feed() || is_admin() || wp_doing_ajax() ) {
			return $content;
		}
		if ( ! jinyu_is_checked( 'auto_link_enable' ) ) {
			return $content;
		}
		if ( ! is_singular( 'post' ) ) {
			return $content;
		}

		$map = jinyu_auto_link_map();
		if ( empty( $map ) ) {
			return $content;
		}

		// 排除当前文章自身
		$current_id = (int) get_the_ID();
		$local      = $map;
		if ( $current_id ) {
			foreach ( $local as $k => $v ) {
				if ( (int) $v['id'] === $current_id ) {
					unset( $local[ $k ] );
				}
			}
		}
		if ( empty( $local ) ) {
			return $content;
		}

		// 长词优先，避免短词先占位
		uksort( $local, function ( $a, $b ) {
			return mb_strlen( $b, 'UTF-8' ) - mb_strlen( $a, 'UTF-8' );
		} );

		$doc = new DOMDocument();
		$doc->substituteEntities = false;
		$prev = libxml_use_internal_errors( true );
		// 前置 charset，让 DOMDocument 按 UTF-8 解析中文（避免已废弃的 mb_convert_encoding）
		$doc->loadHTML( '<meta charset="utf-8">' . $content, LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );

		$xpath  = new DOMXPath( $doc );
		$expr   = '//text()'
			. '[not(ancestor::a)]'
			. '[not(ancestor::script)][not(ancestor::style)]'
			. '[not(ancestor::pre)][not(ancestor::code)]'
			. '[not(ancestor::h1)][not(ancestor::h2)][not(ancestor::h3)]'
			. '[not(ancestor::h4)][not(ancestor::h5)][not(ancestor::h6)]'
			. '[not(ancestor::button)]';
		$texts  = $xpath->query( $expr );
		$targets = [];
		foreach ( $texts as $t ) {
			$targets[] = $t;
		}

		$count       = 0;
		$linked_keys = [];
		$limit       = (int) JINYU_AUTO_LINK_LIMIT;

		foreach ( $targets as $t ) {
			if ( $count >= $limit ) {
				break;
			}
			$text = $t->nodeValue;
			if ( trim( $text ) === '' ) {
				continue;
			}

			// 收集本文本节点内所有可链关键词（非重叠、按出现位置升序、同位置长词优先）
			$hits = [];
			foreach ( $local as $key => $info ) {
				if ( isset( $linked_keys[ $key ] ) ) {
					continue;
				}
				$pos = mb_stripos( $text, $key, 0, 'UTF-8' );
				if ( $pos === false ) {
					continue;
				}
				$hits[] = [ $pos, mb_strlen( $key, 'UTF-8' ), $key, $info ];
			}
			if ( empty( $hits ) ) {
				continue;
			}
			usort( $hits, function ( $a, $b ) {
				if ( $a[0] === $b[0] ) {
					return $b[1] - $a[1]; // 同位置长词优先
				}
				return $a[0] - $b[0];
			} );

			$frag     = $doc->createDocumentFragment();
			$last_end = 0;
			$linked   = false;
			foreach ( $hits as $h ) {
				if ( $count >= $limit ) {
					break;
				}
				list( $pos, $len, $key, $info ) = $h;
				if ( $pos < $last_end ) {
					continue; // 与已链片段重叠，跳过
				}
				$before = mb_substr( $text, $last_end, $pos - $last_end, 'UTF-8' );
				$match  = mb_substr( $text, $pos, $len, 'UTF-8' );
				if ( $before !== '' ) {
					$frag->appendChild( $doc->createTextNode( $before ) );
				}
				$a = $doc->createElement( 'a', $match );
				$a->setAttribute( 'href', esc_url( $info['url'] ) );
				$a->setAttribute( 'class', 'jinyu-auto-link' );
				$a->setAttribute( 'rel', 'bookmark' );
				$frag->appendChild( $a );
				$last_end = $pos + $len;
				$count ++;
				$linked_keys[ $key ] = true;
				$linked = true;
			}
			$tail = mb_substr( $text, $last_end, null, 'UTF-8' );
			if ( $tail !== '' ) {
				$frag->appendChild( $doc->createTextNode( $tail ) );
			}
			if ( $linked ) {
				$t->parentNode->replaceChild( $frag, $t );
			}
		}

		// 仅取 body 内部，避免输出 <html>/<head>/<meta> 等包裹（与 optimize.php 一致）
		$body = $doc->getElementsByTagName( 'body' )->item( 0 );
		if ( $body ) {
			$out = '';
			foreach ( $body->childNodes as $node ) {
				$out .= $doc->saveHTML( $node );
			}
			return $out;
		}
		return $doc->saveHTML();
	}
}
add_filter( 'the_content', 'jinyu_auto_link_content', 12 );

// 内容变更时让关键词索引失效重建
foreach ( [ 'save_post', 'deleted_post', 'trashed_post' ] as $hook ) {
	add_action( $hook, function () {
		delete_transient( 'jinyu_auto_link_map' );
	}, 10, 0 );
}
