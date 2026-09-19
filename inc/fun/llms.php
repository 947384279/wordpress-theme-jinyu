<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GEO（生成式引擎优化）：动态输出 /llms.txt
 * 供 AI 助理（ChatGPT / Claude / Perplexity / 元宝 等）快速读懂并引用本站核心内容。
 * 若站点根目录已存在静态 llms.txt，由 Web 服务器直接返回，本模块不介入。
 *
 * 内容结构（自上而下重要性递减）：
 *   站点名 + 简介 + 引导语
 *   热门文章   —— 按浏览量，代表站点最具代表性的内容
 *   最新文章   —— 新鲜度信号
 *   系列教程   —— jinyu_series 自定义分类法，结构化知识，体现主题权威度
 *   分类       —— 主题地图
 *   重要页面   —— 首页 / 关于 / 友链 / 标签 / 归档 等
 */

add_filter( 'query_vars', 'jinyu_llms_query_var' );
function jinyu_llms_query_var( array $vars ): array {
	$vars[] = 'jinyu_llms';
	$vars[] = 'jinyu_llms_full';
	return $vars;
}

add_action( 'init', 'jinyu_llms_rewrite', 5 );
function jinyu_llms_rewrite(): void {
	// 站点根 /llms.txt → 交由本模块渲染（索引版）
	add_rewrite_rule( '^llms\.txt$', 'index.php?jinyu_llms=1', 'top' );
	// 站点根 /llms-full.txt → 全站正文纯 markdown（供 AI 整站吸收）
	add_rewrite_rule( '^llms-full\.txt$', 'index.php?jinyu_llms_full=1', 'top' );
}

// 阻止 WordPress 对 /llms.txt、/llms-full.txt 做「补尾斜杠」301 重定向。
// WP 会把无斜杠请求 301 到 /llms.txt/，而重写规则只匹配无斜杠，导致端点多跳、
// 部分 AI 抓取器（及 IndexNow 验证风格严格 fetcher）不跟进而导致端点失效。
// llms.txt 规范要求在 /llms.txt 直接返回 200，故在此关闭该端点的 canonical 重定向。
add_filter( 'redirect_canonical', 'jinyu_llms_no_trailing_slash', 10, 2 );
function jinyu_llms_no_trailing_slash( $redirect_url, $requested_url ) {
	if ( get_query_var( 'jinyu_llms' ) || get_query_var( 'jinyu_llms_full' ) ) {
		return false;
	}
	return $redirect_url;
}

// 新增 /llms-full.txt 重写规则后，旧站点需一次性刷新重写规则使其生效。
add_action( 'init', 'jinyu_llms_full_maybe_flush', 20 );
function jinyu_llms_full_maybe_flush(): void {
	if ( get_option( 'jinyu_llms_full_rewrite_ver' ) === '1' ) {
		return;
	}
	flush_rewrite_rules();
	update_option( 'jinyu_llms_full_rewrite_ver', '1' );
}

// 主题已启用时也能立即生效：按版本号做一次重写规则刷新（之后不再重复刷新）
add_action( 'init', 'jinyu_llms_maybe_flush', 20 );
function jinyu_llms_maybe_flush(): void {
	if ( get_option( 'jinyu_llms_rewrite_ver' ) === JINYU_CUR_VER ) {
		return;
	}
	flush_rewrite_rules();
	update_option( 'jinyu_llms_rewrite_ver', JINYU_CUR_VER );
}

// 主题启用/切换时刷新重写规则，使 ^llms\.txt$ 生效
add_action( 'after_switch_theme', 'jinyu_llms_flush' );
function jinyu_llms_flush(): void {
	flush_rewrite_rules();
}

add_action( 'template_redirect', 'jinyu_llms_serve' );
function jinyu_llms_serve(): void {
	$mode = '';
	if ( ! empty( get_query_var( 'jinyu_llms' ) ) ) {
		$mode = 'index';
	} elseif ( ! empty( get_query_var( 'jinyu_llms_full' ) ) ) {
		$mode = 'full';
	}
	if ( ! $mode ) {
		return;
	}

	// 后台关闭时返回 404，使该端点对外不可见。
	// 重写规则保持注册（避免「关→开」后残留陈旧规则需手动刷新），仅在此处拦截。
	if ( ! jinyu_is_checked( 'llms_enable' ) ) {
		global $wp_query;
		if ( isset( $wp_query ) ) {
			$wp_query->set_404();
		}
		status_header( 404 );
		return;
	}

	header( 'Content-Type: text/markdown; charset=utf-8' );
	header( 'X-Robots-Tag: index, follow' );

	if ( $mode === 'full' ) {
		jinyu_llms_serve_full();
		return;
	}

	$site_name = (string) get_bloginfo( 'name' );
	$desc      = (string) get_bloginfo( 'description' );
	$home      = home_url( '/' );

	$out  = '# ' . $site_name . "\n\n";
	$out .= $desc ? $desc . "\n\n" : '';
	$out .= '> 本文件供 AI 助理（如 ChatGPT、Claude、Perplexity、元宝等）快速了解本站核心内容。' . "\n\n";
	$out .= '> 全站正文版见 [llms-full.txt](' . esc_url( home_url( '/llms-full.txt' ) ) . ')。' . "\n\n";

	// 渲染单篇文章链接行（标题 + 截断摘要），供多个区块复用
	$post_line = static function ( WP_Post $p ): string {
		$url     = get_permalink( $p );
		$title   = wp_strip_all_tags( get_the_title( $p ) );
		$excerpt = wp_strip_all_tags( $p->post_excerpt ?: $p->post_content );
		// 内容可能为双重（乃至多重）HTML 实体编码，循环解码直至稳定
		$prev = '';
		$iter = 0;
		while ( $prev !== $excerpt && $iter < 3 ) {
			$prev    = $excerpt;
			$excerpt = html_entity_decode( $excerpt, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$iter++;
		}
		$excerpt = trim( preg_replace( '/\s+/', ' ', $excerpt ) );
		if ( mb_strlen( $excerpt, 'UTF-8' ) > 200 ) {
			$excerpt = mb_substr( $excerpt, 0, 200, 'UTF-8' ) . '…';
		}
		$line = '- [' . $title . '](' . esc_url( $url ) . ')';
		if ( $excerpt ) {
			$line .= ': ' . $excerpt;
		}
		return $line . "\n";
	};

	// 重要页面辅助：按 slug 取页面（避免已弃用的 get_page_by_path）
	$page_by_slug = static function ( string $slug ): ?WP_Post {
		$pages = get_posts( [
			'post_type'      => 'page',
			'name'           => $slug,
			'posts_per_page' => 1,
			'no_found_rows'  => true,
		] );
		return $pages ? $pages[0] : null;
	};

	// 热门文章（站点最具代表性的内容，按浏览量降序）
	$out .= '## 热门文章' . "\n\n";
	$hot  = function_exists( 'jinyu_get_hot_posts' )
		? jinyu_get_hot_posts( 8 )
		: get_posts( [
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 8,
			'no_found_rows'  => true,
			'orderby'        => 'comment_count',
			'order'          => 'DESC',
		] );
	foreach ( $hot as $p ) {
		$out .= $post_line( $p );
	}
	$out .= "\n";

	// 最新文章（新鲜度信号）
	$out     .= '## 最新文章' . "\n\n";
	$latest   = get_posts( [
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => 8,
		'no_found_rows'  => true,
		'orderby'        => 'date',
		'order'          => 'DESC',
	] );
	foreach ( $latest as $p ) {
		$out .= $post_line( $p );
	}
	$out .= "\n";

	// 系列教程（结构化知识，体现站点主题权威度）
	$series = get_terms( [
		'taxonomy'   => 'jinyu_series',
		'orderby'    => 'count',
		'order'      => 'DESC',
		'number'     => 8,
		'hide_empty' => true,
	] );
	if ( ! is_wp_error( $series ) && ! empty( $series ) ) {
		$out .= '## 系列教程' . "\n\n";
		foreach ( $series as $s ) {
			$line = '- [' . esc_html( $s->name ) . '](' . esc_url( get_term_link( $s ) ) . ')';
			if ( $s->description ) {
				$raw = wp_strip_all_tags( $s->description );
				$prev = '';
				$iter = 0;
				while ( $prev !== $raw && $iter < 3 ) {
					$prev = $raw;
					$raw  = html_entity_decode( $raw, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
					$iter++;
				}
				$line .= ': ' . trim( preg_replace( '/\s+/', ' ', $raw ) );
			}
			$out .= $line . "\n";
		}
		$out .= "\n";
	}

	// 分类（主题地图）
	$cats = get_categories( [ 'orderby' => 'count', 'order' => 'DESC', 'number' => 10 ] );
	if ( ! empty( $cats ) ) {
		$out .= '## 分类' . "\n\n";
		foreach ( $cats as $c ) {
			$out .= '- [' . esc_html( $c->name ) . '](' . esc_url( get_category_link( $c->term_id ) ) . ')' . "\n";
		}
		$out .= "\n";
	}

	// 标签（更细粒度的主题索引）
	$tags = get_tags( [ 'orderby' => 'count', 'order' => 'DESC', 'number' => 15 ] );
	if ( ! empty( $tags ) ) {
		$out .= '## 标签' . "\n\n";
		foreach ( $tags as $t ) {
			$out .= '- [' . esc_html( $t->name ) . '](' . esc_url( get_tag_link( $t->term_id ) ) . ')' . "\n";
		}
		$out .= "\n";
	}

	// 重要页面
	$out .= '## 重要页面' . "\n\n";
	$out .= '- [首页](' . esc_url( $home ) . ')' . "\n";
	foreach ( [
		'about'     => '关于',
		'links'     => '友情链接',
		'tags'      => '标签',
		'archives'  => '归档',
		'guestbook' => '留言板',
	] as $slug => $label ) {
		$page = $page_by_slug( $slug );
		if ( $page ) {
			$out .= '- [' . $label . '](' . esc_url( get_permalink( $page ) ) . ')' . "\n";
		}
	}

	echo $out;
	exit;
}

/**
 * HTML → Markdown 轻量转换（无第三方依赖）。
 * 保留标题 / 链接 / 列表 / 代码 / 粗斜体，剥离其余标签，适合喂给 LLM。
 */
function jinyu_html_to_md( string $html ): string {
	$html = html_entity_decode( $html, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

	// 0) 去除主题自定义短代码标签（如 [jinyu_tip]、[jinyu_collapse]），保留内部文本
	$html = preg_replace( '#\[/?jinyu_[^\]]*\]#i', '', $html );

	// 1) 提取代码块 / 行内代码，用占位符保护，避免内部被二次处理
	$blocks = [];
	$html   = preg_replace_callback( '#<pre[^>]*>(.*?)</pre>#is', static function ( $m ) use ( &$blocks ) {
		$blocks[] = trim( preg_replace( '#</?code[^>]*>#i', '', $m[1] ) );
		return "\n@@CODE" . ( count( $blocks ) - 1 ) . "@@\n";
	}, $html );
	$icodes = [];
	$html   = preg_replace_callback( '#<code[^>]*>(.*?)</code>#is', static function ( $m ) use ( &$icodes ) {
		$icodes[] = trim( wp_strip_all_tags( $m[1] ) );
		return "@@ICODE" . ( count( $icodes ) - 1 ) . "@@";
	}, $html );

	// 2) 标题
	$html = preg_replace_callback( '#<h([1-6])[^>]*>(.*?)</h\1>#is', static function ( $m ) {
		$lvl = min( 6, max( 1, (int) $m[1] ) );
		return "\n\n" . str_repeat( '#', $lvl ) . ' ' . trim( wp_strip_all_tags( $m[2] ) ) . "\n\n";
	}, $html );

	// 3) 链接
	$html = preg_replace_callback( '#<a[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#is', static function ( $m ) {
		$url = trim( $m[1] );
		$txt = trim( wp_strip_all_tags( $m[2] ) );
		if ( $txt === '' ) {
			$txt = $url;
		}
		return '[' . $txt . '](' . $url . ')';
	}, $html );

	// 4) 列表项
	$html = preg_replace_callback( '#<li[^>]*>(.*?)</li>#is', static function ( $m ) {
		return '- ' . trim( wp_strip_all_tags( $m[1] ) ) . "\n";
	}, $html );

	// 5) 粗体 / 斜体
	$html = preg_replace( '#<(strong|b)[^>]*>(.*?)</\1>#is', '**$2**', $html );
	$html = preg_replace( '#<(em|i)[^>]*>(.*?)</\1>#is', '*$2*', $html );

	// 6) 块级换行
	$html = preg_replace( '#</(p|div|blockquote|ul|ol|table|tr|h[1-6])>#i', "\n\n", $html );
	$html = preg_replace( '#<br\s*/?>#i', "\n", $html );
	$html = preg_replace( '#<hr\s*/?>#i', "\n\n---\n\n", $html );

	// 7) 去除剩余标签
	$html = wp_strip_all_tags( $html );

	// 8) 还原代码
	$html = preg_replace_callback( '#@@CODE(\d+)@@#', static function ( $m ) use ( $blocks ) {
		$i = (int) $m[1];
		return "\n```\n" . ( $blocks[ $i ] ?? '' ) . "\n```\n";
	}, $html );
	$html = preg_replace_callback( '#@@ICODE(\d+)@@#', static function ( $m ) use ( $icodes ) {
		$i = (int) $m[1];
		return '`' . ( $icodes[ $i ] ?? '' ) . '`';
	}, $html );

	// 9) 清理多余空行
	$html = preg_replace( '/\n{3,}/', "\n\n", $html );
	return trim( $html );
}

/**
 * 输出 /llms-full.txt：全站已发布文章正文（纯 markdown），供 AI 整站吸收。
 */
function jinyu_llms_serve_full(): void {
	$site_name = (string) get_bloginfo( 'name' );
	$desc      = (string) get_bloginfo( 'description' );

	$out  = '# ' . $site_name . '（全文）' . "\n\n";
	$out .= $desc ? $desc . "\n\n" : '';
	$out .= '> 本文件为全站已发布内容（文章与页面）正文纯文本（markdown），供 AI 助理（如 ChatGPT、Claude、Perplexity、元宝等）整站吸收与引用。' . "\n\n";

	$posts = get_posts( [
		'post_type'      => [ 'post', 'page' ],
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'no_found_rows'  => true,
	] );

	foreach ( $posts as $p ) {
		$url     = get_permalink( $p );
		$title   = wp_strip_all_tags( get_the_title( $p ) );
		$date    = get_the_date( 'Y-m-d', $p );
		$cats    = wp_get_post_categories( $p->ID, [ 'fields' => 'names' ] );
		$tags    = wp_get_post_tags( $p->ID, [ 'fields' => 'names' ] );
		$content = jinyu_html_to_md( $p->post_content );
		// 去除正文里与标题重复的 H1（文章标题已在上方独立渲染）
		$content = preg_replace( '/^#\s+.*$(\R)?/m', '', $content, 1 );

		$out .= '## ' . $title . "\n\n";
		$out .= '- URL: ' . esc_url( $url ) . "\n";
		$out .= '- 发布：' . $date . "\n";
		if ( $cats ) {
			$out .= '- 分类：' . implode( '、', $cats ) . "\n";
		}
		if ( $tags ) {
			$out .= '- 标签：' . implode( '、', $tags ) . "\n";
		}
		$out .= "\n" . $content . "\n\n---\n\n";
	}

	echo $out;
	exit;
}
