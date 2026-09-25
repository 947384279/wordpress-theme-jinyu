<?php
/**
 * Title: 金玉 · 作者介绍卡
 * Slug: jinyu/author-card
 * Categories: jinyu
 * Keywords: 作者, 关于, 介绍
 * Description: 头像 + 姓名 + 简介的联系卡片，适合文章署名或独立介绍页。
 * Viewport Width: 800
 *
 * @package Jinyu
 */

?>
<!-- wp:media-text {"align":"wide","mediaPosition":"right","className":"jy-pat-author"} -->
<div class="wp-block-media-text alignwide is-stacked-on-mobile has-media-on-the-right jy-pat-author"><figure class="wp-block-media-text__media"></figure><div class="wp-block-media-text__content"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">作者姓名</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>用两三句话介绍自己：写什么主题的内容、有什么经历、读者能从这里获得什么。</p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( home_url( '/' ) ); ?>">查看全部文章</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div></div>
<!-- /wp:media-text -->
