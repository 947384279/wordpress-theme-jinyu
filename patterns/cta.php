<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Title: Jinyu: Call to Action
 * Slug: jinyu/cta
 * Categories: jinyu
 * Keywords: CTA, subscribe, conversion
 * Description: Centered call-to-action copy with a button. Good for end-of-post funnels and landing pages.
 * Viewport Width: 800
 *
 * @package Jinyu
 */

?>
<!-- wp:group {"className":"jy-pat-cta"} -->
<div class="wp-block-group jy-pat-cta"><!-- wp:heading {"textAlign":"center","level":3} -->
<h3 class="wp-block-heading has-text-align-center">Enjoying the content?</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center">Subscribe and get every new post delivered straight to your inbox.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( home_url( '/' ) ); ?>">Subscribe now</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->
