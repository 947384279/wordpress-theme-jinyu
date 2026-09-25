<?php
/**
 * Title: 金玉 · 行动号召
 * Slug: jinyu/cta
 * Categories: jinyu
 * Keywords: CTA, 行动号召, 订阅
 * Description: 居中的号召文案 + 按钮，适合文末引流或落地页收尾。
 * Viewport Width: 800
 *
 * @package Jinyu
 */

?>
<!-- wp:group {"className":"jy-pat-cta"} -->
<div class="wp-block-group jy-pat-cta"><!-- wp:heading {"textAlign":"center","level":3} -->
<h3 class="wp-block-heading has-text-align-center">喜欢这些内容？</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center">订阅本站，新文章第一时间送达你的邮箱。</p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( home_url( '/' ) ); ?>">立即订阅</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->
