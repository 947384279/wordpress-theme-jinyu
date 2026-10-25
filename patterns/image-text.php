<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Title: Jinyu: Media &amp; Text
 * Slug: jinyu/image-text
 * Categories: jinyu
 * Keywords: media-text, intro, image
 * Description: Image on the left, text on the right; stacks automatically on mobile.
 * Viewport Width: 900
 *
 * @package Jinyu
 */

?>
<!-- wp:media-text {"align":"wide"} -->
<div class="wp-block-media-text alignwide is-stacked-on-mobile"><figure class="wp-block-media-text__media"></figure><div class="wp-block-media-text__content"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Build a Personal Blog from Scratch</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Write your intro text here. After inserting this pattern, replace the image on the left and this copy with your own — layout, spacing, and mobile responsiveness are all handled for you.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( home_url( '/' ) ); ?>">Learn more</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div></div>
<!-- /wp:media-text -->
