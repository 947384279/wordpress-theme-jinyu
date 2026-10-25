<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Title: Jinyu: Author Card
 * Slug: jinyu/author-card
 * Categories: jinyu
 * Keywords: author, about, bio
 * Description: Avatar + name + bio contact card. Fits post bylines and standalone about pages.
 * Viewport Width: 800
 *
 * @package Jinyu
 */

?>
<!-- wp:media-text {"align":"wide","mediaPosition":"right","className":"jy-pat-author"} -->
<div class="wp-block-media-text alignwide is-stacked-on-mobile has-media-on-the-right jy-pat-author"><figure class="wp-block-media-text__media"></figure><div class="wp-block-media-text__content"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Author Name</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Introduce yourself in two or three sentences: what you write about, your background, and what readers will get here.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( home_url( '/' ) ); ?>">View all posts</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div></div>
<!-- /wp:media-text -->
