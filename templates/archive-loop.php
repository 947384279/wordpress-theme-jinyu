<?php
/**
 * 归档页文章列表循环（作者 / 分类 / 标签 / 搜索 / 系列归档 共用）。
 *
 * 这 5 个归档模板的循环体曾逐字重复 5 份，连分页参数都一致。抽出成本文件后，
 * 改布局（如卡片样式、加入 load-more）只需改这一处。
 *
 * ⚠️ 刻意不纳入的模板：
 *   · index.php / date.php —— 它们用 jinyu_pagination()（无参，默认带 load-more），
 *     与归档页的 ['load_more' => false] 是**有意区分**（首页走 AJAX 加载更多），不是重复。
 *   · single.php —— 文章详情，无循环列表。
 *
 * @package         WordPress
 * @subpackage      Jinyu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<?php if ( have_posts() ) : ?>
	<div class="jinyu-post-grid">
		<?php
		while ( have_posts() ) :
			the_post();
			get_template_part( 'templates/module', 'post' );
		endwhile;
		?>
	</div>
	<?php
	// 归档页一律不走 AJAX 加载更多：分类/标签/搜索结果需要搜索引擎抓取完整分页链接。
	jinyu_pagination( [ 'load_more' => false ] );
	get_template_part( 'templates/flinks' );
	?>
<?php else : ?>
	<?php get_template_part( 'templates/content', 'none' ); ?>
<?php endif; ?>
