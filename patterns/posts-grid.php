<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Title: Jinyu: Featured Posts Grid
 * Slug: jinyu/posts-grid
 * Categories: jinyu
 * Keywords: posts, latest, grid
 * Description: A three-column grid of the 3 latest posts (cover + title + date), updated automatically on the front end.
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
<p><?php echo esc_html__( 'No posts yet.', 'jinyu' ); ?></p>
<!-- /wp:paragraph -->
<!-- /wp:query-no-results --></div>
<!-- /wp:query -->
