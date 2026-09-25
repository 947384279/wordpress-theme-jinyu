<?php
/**
 * Title: 金玉 · 精选文章三栏
 * Slug: jinyu/posts-grid
 * Categories: jinyu
 * Keywords: 文章列表, 最新文章, 网格
 * Description: 动态取最新 3 篇文章，封面 + 标题 + 日期三栏网格，前端自动更新。
 * Viewport Width: 900
 *
 * @package Jinyu
 */

?>
<!-- wp:query {"queryId":0,"query":{"perPage":3,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false}} -->
<div class="wp-block-query"><!-- wp:post-template {"layout":{"type":"grid","columnCount":3},"className":"jy-pat-posts"} -->
<!-- wp:post-featured-image {"isLink":true} /-->

<!-- wp:post-title {"isLink":true} /-->

<!-- wp:post-date /-->
<!-- /wp:post-template -->

<!-- wp:query-no-results -->
<!-- wp:paragraph -->
<p><?php echo esc_html__( '暂无文章。', 'jinyu' ); ?></p>
<!-- /wp:paragraph -->
<!-- /wp:query-no-results --></div>
<!-- /wp:query -->
