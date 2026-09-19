<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 金玉 · 销售与变现（前端渲染）
 *
 * 输出：全站悬浮 CTA 条、侧边悬浮客服/微信、文末统一引导卡、邮件订阅框，
 * 并为广告位包裹「广告」角标。订阅通过 AJAX 落地（见 inc/fun/tracking.php）。
 *
 * 注意整页缓存：CTA / 悬浮按钮 / 订阅框均为静态 HTML（不含访客个性化），
 * 可直接被页面缓存固化；点击/曝光事件由前端 JS 异步上报，不依赖缓存穿透。
 */

if ( ! function_exists( 'jinyu_subscribe_form_html' ) ) {
	/**
	 * 订阅表单 HTML（短代码 / 小工具 / 文末自动追加共用）。
	 */
	function jinyu_subscribe_form_html( string $source = 'shortcode' ): string {
		if ( ! jinyu_is_checked( 'subscribe_enable' ) ) {
			return '';
		}
		$nonce = wp_create_nonce( 'jinyu_front' );
		$title = jinyu_get_option( 'subscribe_title', __( '订阅更新', JINYU ) );
		$desc  = jinyu_get_option( 'subscribe_desc', '' );
		ob_start();
		?>
		<div class="jinyu-subscribe" data-jinyu-subscribe>
			<?php if ( $title ) : ?><h3 class="jinyu-subscribe-title"><?php echo esc_html( $title ); ?></h3><?php endif; ?>
			<?php if ( $desc ) : ?><p class="jinyu-subscribe-desc"><?php echo esc_html( $desc ); ?></p><?php endif; ?>
			<form class="jinyu-subscribe-form" method="post" action="javascript:void(0)">
				<input type="email" name="email" class="jinyu-subscribe-input" placeholder="<?php esc_attr_e( '输入你的邮箱', JINYU ); ?>" required>
				<button type="submit" class="jinyu-subscribe-btn"><?php esc_html_e( '订阅', JINYU ); ?></button>
				<input type="hidden" name="source" value="<?php echo esc_attr( $source ); ?>">
				<input type="hidden" name="nonce" value="<?php echo esc_attr( $nonce ); ?>">
			</form>
			<p class="jinyu-subscribe-msg" hidden></p>
		</div>
		<?php
		return ob_get_clean();
	}
}

// 短代码 [subscribe]
if ( ! shortcode_exists( 'subscribe' ) ) {
	add_shortcode( 'subscribe', function ( $atts ) {
		$atts = shortcode_atts( [ 'source' => 'shortcode' ], $atts, 'subscribe' );
		return jinyu_subscribe_form_html( $atts['source'] );
	} );
}

// 文末自动追加订阅框（需在单篇文章且开启）
add_filter( 'the_content', function ( $content ) {
	if ( ! is_singular( 'post' ) || ! in_the_loop() || is_admin() ) {
		return $content;
	}
	if ( jinyu_is_checked( 'subscribe_auto_end' ) && jinyu_is_checked( 'subscribe_enable' ) ) {
		$content .= "\n" . jinyu_subscribe_form_html( 'post_end' );
	}
	return $content;
} );

// 全站悬浮 CTA 条
add_action( 'wp_footer', function () {
	if ( ! jinyu_is_checked( 'cta_bar_enable' ) ) {
		return;
	}
	$text = trim( (string) jinyu_get_option( 'cta_bar_text', '' ) );
	$link = trim( (string) jinyu_get_option( 'cta_bar_link', '' ) );
	if ( ! $text || ! $link ) {
		return;
	}
	$delay = (int) jinyu_get_option( 'cta_bar_delay', 3 );
	?>
	<div class="jinyu-cta-bar" id="jinyu-cta-bar" data-delay="<?php echo $delay; ?>" hidden>
		<span class="jinyu-cta-text"><?php echo esc_html( $text ); ?></span>
		<a class="jinyu-cta-link" href="<?php echo esc_url( $link ); ?>" target="_blank" rel="noopener" data-jinyu-cta><?php esc_html_e( '前往', JINYU ); ?></a>
		<button type="button" class="jinyu-cta-close" id="jinyu-cta-close" aria-label="<?php esc_attr_e( '关闭', JINYU ); ?>">×</button>
	</div>
	<?php
}, 20 );

// 侧边悬浮客服/微信
add_action( 'wp_footer', function () {
	if ( ! jinyu_is_checked( 'float_contact_enable' ) ) {
		return;
	}
	$img = trim( (string) jinyu_get_option( 'float_contact_img', '' ) );
	if ( ! $img ) {
		return;
	}
	$title = trim( (string) jinyu_get_option( 'float_contact_title', __( '联系我们', JINYU ) ) );
	?>
	<div class="jinyu-float-contact" id="jinyu-float-contact">
		<button type="button" class="jinyu-float-btn" id="jinyu-float-btn" aria-label="<?php echo esc_attr( $title ); ?>">
			<i class="fa-solid fa-comment-dots"></i>
		</button>
		<div class="jinyu-float-pop" id="jinyu-float-pop" hidden>
			<p class="jinyu-float-title"><?php echo esc_html( $title ); ?></p>
			<img src="<?php echo esc_url( jinyu_img_to_webp_url( $img ) ); ?>" alt="<?php echo esc_attr( $title ); ?>" class="jinyu-float-img">
		</div>
	</div>
	<?php
}, 21 );

// 文末统一引导卡（独立渲染，不依赖 the_content 过滤器，避免与订阅框重复）
add_action( 'jinyu_single_after_content', function () {
	if ( ! jinyu_is_checked( 'post_guide_enable' ) ) {
		return;
	}
	$title = trim( (string) jinyu_get_option( 'post_guide_title', '' ) );
	$text  = trim( (string) jinyu_get_option( 'post_guide_text', '' ) );
	$btn   = trim( (string) jinyu_get_option( 'post_guide_btn_text', '' ) );
	$link  = trim( (string) jinyu_get_option( 'post_guide_btn_link', '' ) );
	if ( ! $title && ! $text ) {
		return;
	}
	?>
	<div class="jinyu-post-guide">
		<?php if ( $title ) : ?><h3 class="jinyu-post-guide-title"><?php echo esc_html( $title ); ?></h3><?php endif; ?>
		<?php if ( $text ) : ?><p class="jinyu-post-guide-text"><?php echo esc_html( $text ); ?></p><?php endif; ?>
		<?php if ( $btn && $link ) : ?>
			<a class="jinyu-post-guide-btn" href="<?php echo esc_url( $link ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $btn ); ?></a>
		<?php endif; ?>
	</div>
	<?php
} );

// 广告位「广告」角标包裹（仅当开启 ad_badge_enable）
if ( ! function_exists( 'jinyu_ad_badge_wrap' ) ) {
	/**
	 * 给广告位内容包一层带角标的容器。
	 */
	function jinyu_ad_badge_wrap( string $html, string $slot ): string {
		if ( ! $html ) {
			return '';
		}
		if ( ! jinyu_is_checked( 'ad_badge_enable' ) ) {
			return $html;
		}
		$label = trim( (string) jinyu_get_option( 'ad_badge_text', '' ) ) ?: __( '广告', JINYU );
		return '<div class="jinyu-ad" data-ad-slot="' . esc_attr( $slot ) . '">'
		. '<span class="jinyu-ad-badge">' . esc_html( $label ) . '</span>'
		. jinyu_webp_replace_html_imgs( $html ) . '</div>';
	}
}
