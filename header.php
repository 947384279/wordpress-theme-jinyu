<?php
/**
 * 站点头部
 *
 * @package WordPress
 * @subpackage Jinyu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?><!doctype html>
<html <?php language_attributes(); ?><?php jinyu_html_attrs(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="theme-color" content="#f5f6f8" media="(prefers-color-scheme: light)">
	<meta name="theme-color" content="#16181c" media="(prefers-color-scheme: dark)">
	<?php wp_head(); ?>
	<?php
	// 首页 LCP 图预加载：让浏览器在 CSSOM 建立前就发现并请求首屏最大图，压低 LCP。
	// href 必须与真实 <img> 的 src 完全一致（轮播首图直接输出 $s['image']，不套 webp 转换），
	// 否则预加载 URL 对不上、Chrome 报 "preload not used" 且零收益。
	if ( is_front_page() ) {
		$jinyu_lcp        = '';
		$jinyu_lcp_srcset = '';
		if ( function_exists( 'jinyu_is_checked' ) && jinyu_is_checked( 'home_carousel' )
			&& function_exists( 'jinyu_get_carousel_slides' ) ) {
			$jinyu_slides = jinyu_get_carousel_slides();
			if ( ! empty( $jinyu_slides[0]['image'] ) ) {
				$jinyu_lcp        = $jinyu_slides[0]['image'];
				$jinyu_lcp_srcset = ! empty( $jinyu_slides[0]['srcset'] ) ? $jinyu_slides[0]['srcset'] : '';
			}
		}
		if ( ! $jinyu_lcp && ! empty( $wp_query ) && ! empty( $wp_query->posts ) ) {
			$jinyu_lcp = function_exists( 'jinyu_get_post_cover' ) ? jinyu_get_post_cover( $wp_query->posts[0]->ID ) : '';
		}
		if ( $jinyu_lcp ) :
			?>
	<link rel="preload" as="image" href="<?php echo esc_url( $jinyu_lcp ); ?>"<?php echo $jinyu_lcp_srcset ? ' imagesrcset="' . esc_attr( $jinyu_lcp_srcset ) . '" imagesizes="100vw"' : ''; ?> fetchpriority="high">
			<?php
		endif;
	}
	?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a href="#jinyu-content" class="jinyu-skip-link"><?php esc_html_e( 'Skip to content', 'jinyu' ); ?></a>

<div class="jinyu-skeleton" id="jinyu-skeleton" aria-hidden="true">
	<div class="jinyu-skeleton-bar is-title"></div>
	<div class="jinyu-skeleton-bar is-wide"></div>
	<div class="jinyu-skeleton-bar is-mid"></div>
	<div class="jinyu-skeleton-bar is-card"></div>
	<div class="jinyu-skeleton-bar is-wide"></div>
	<div class="jinyu-skeleton-bar is-mid"></div>
</div>
<?php
/*
这块骨架屏是 position:fixed 的实心遮罩，只有等 defer 脚本执行完（DOMContentLoaded）
	才会淡出——实测 DCL 落在 700ms 左右，等于把真实首屏压到那之后才绘制，
	FCP / Speed Index 都被它拖住。主题已内联 critical.min.css 且内容是服务端渲染的，
	首屏本来就有样式，骨架屏在这里纯属负担，所以在解析到它的瞬间直接摘掉。
	无脚本环境由紧随其后的 noscript 的 display:none 兜底。
	注意：<script> 内容是 raw text，HTML 实体在这里不会被解码，所以判断符必须写字面量 &&。 */
?>
<script<?php echo jinyu_csp_nonce_attr();  /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- nonce 属性，非动态内容 */ ?>>
document.getElementById('jinyu-skeleton')&&document.getElementById('jinyu-skeleton').parentNode.removeChild(document.getElementById('jinyu-skeleton'));</script>
<noscript><style<?php echo jinyu_csp_nonce_attr();  /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- nonce 属性，已 esc_attr */ ?>>.jinyu-skeleton{display:none!important}</style></noscript>

<?php if ( is_singular( 'post' ) ) : ?>
<div class="jinyu-read-bar" aria-hidden="true"><div class="jinyu-read-inner"></div></div>
<?php endif; ?>

<header class="jinyu-header">
	<div class="jinyu-container jinyu-header-inner">

	<button type="button" class="jinyu-nav-toggle" data-jinyu-nav-toggle
			data-label-open="<?php esc_attr_e( 'Expand menu', 'jinyu' ); ?>"
			data-label-close="<?php esc_attr_e( 'Close menu', 'jinyu' ); ?>"
			aria-label="<?php esc_attr_e( 'Expand menu', 'jinyu' ); ?>" aria-expanded="false" aria-controls="jinyu-primary-nav">
		<span class="jinyu-burger" aria-hidden="true"><span></span><span></span><span></span></span>
	</button>

	<?php
	// 站点品牌名：优先取主题「站点标题」，留空则回退 WordPress 站点标题.
	$brand_name = trim( (string) jinyu_get_option( 'web_title', '' ) );
	if ( '' === $brand_name ) {
		$brand_name = (string) get_bloginfo( 'name' );
	}
	$logo_light     = trim( (string) jinyu_get_option( 'web_logo', '' ) );
	$logo_dark      = trim( (string) jinyu_get_option( 'web_logo_dark', '' ) );
	$custom_logo_id = (int) get_theme_mod( 'custom_logo', 0 );
	$brand_alt      = esc_attr( $brand_name );
	// Logo 图本身已含站名，此时不再叠加文字站名（否则站名重复显示）.
	$has_logo_img = ( '' !== $logo_light || 0 < $custom_logo_id );
	// 仅当亮/暗两张 Logo 都配置时才启用双 Logo 切换.
	$logo_dual = ( '' !== $logo_light && '' !== $logo_dark );
	?>
	<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="jinyu-logo<?php echo $logo_dual ? ' jinyu-logo--dual' : ''; ?>" rel="home">
		<?php
		// 补原始宽高：图片加载前就能按比例占位，避免头部在 Logo 到位时发生 CLS.
		$logo_size_light = jinyu_logo_image_size( $logo_light );
		$logo_size_dark  = $logo_dual ? jinyu_logo_image_size( $logo_dark ) : array( 0, 0 );
		$logo_attrs      = function ( $size ) {
			return ( $size && $size[0] && $size[1] )
			? ' width="' . (int) $size[0] . '" height="' . (int) $size[1] . '"'
			: '';
		};
		?>
	<?php if ( '' !== $logo_light ) : ?>
		<img class="jinyu-logo-img jinyu-logo-light" src="<?php echo esc_url( jinyu_img_to_webp_url( $logo_light ) ); ?>" alt="<?php echo $brand_alt; ?>"<?php echo $logo_attrs( $logo_size_light );  /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 输出经 esc_html/esc_attr/wp_kses 处理或为核心传入值/整型，WPCS 追不到集中式委托故误报 */ ?>>
		<?php if ( $logo_dual ) : ?>
			<?php // 深色版在浅色主题下 display:none，加 loading="lazy" 后浏览器判定其永不可见 → 不下载。切到深色才按需拉取，首屏省掉一整套 Logo 字节。 ?>
			<img class="jinyu-logo-img jinyu-logo-dark" loading="lazy" src="<?php echo esc_url( jinyu_img_to_webp_url( $logo_dark ) ); ?>" alt="<?php echo $brand_alt; ?>"<?php echo $logo_attrs( $logo_size_dark );  /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 输出经 esc_html/esc_attr/wp_kses 处理或为核心传入值/整型，WPCS 追不到集中式委托故误报 */ ?>>
		<?php endif; ?>
		<?php elseif ( $custom_logo_id > 0 ) : ?>
			<?php
			echo wp_get_attachment_image(
				$custom_logo_id,
				'full',
				false,
				[
					'class' => 'jinyu-logo-img',
					'alt'   => $brand_name,
				]
			);
			?>
		<?php else : ?>
		<span class="jinyu-logo-icon"><i class="fa-solid fa-fire" aria-hidden="true"></i></span>
		<?php endif; ?>
		<?php if ( ! $has_logo_img ) : ?>
		<span class="jinyu-logo-text-wrap">
		<span class="jinyu-logo-text"><?php echo esc_html( $brand_name ); ?></span>
			<?php if ( get_bloginfo( 'description' ) ) : ?>
			<span class="jinyu-logo-sub"><?php echo esc_html( get_bloginfo( 'description' ) ); ?></span>
		<?php endif; ?>
		</span>
		<?php endif; ?>
	</a>

	<?php
	/*
	导航面板：桌面为头部内联菜单；≤1240 变为紧贴头部下沿的全屏浮层（见 common.less）。
		面板必须留在 header 内以复用同一份菜单 DOM，因此 ≤1240 断点移除了 .jinyu-header 的
		backdrop-filter —— 否则它成为固定定位的包含块，浮层会被压缩成头部高度。
		工具区（搜索/主题/登录）是 header 的独立子元素，≤1240 常驻标题栏右侧，不进浮层。 */
	?>
	<div class="jinyu-nav-panel" data-jinyu-nav-panel>
		<?php
		wp_nav_menu(
			[
				'theme_location' => 'primary',
				'menu_id'        => 'jinyu-primary-nav',
				'menu_class'     => 'jinyu-nav',
				'container'      => false,
				'fallback_cb'    => 'jinyu_default_nav',
			]
		);
		?>
	</div><!-- /.jinyu-nav-panel -->

	<?php
	/*
	工具区（搜索 / 主题 / 登录）：桌面在标题栏右侧与菜单并排；
		≤1240 不再塞进浮层底部，而是常驻标题栏右侧（用户要求移动端工具随手可及）。
		浮层 .jinyu-nav-panel 此时只承载菜单 DOM，结构更清晰。 */
	?>
	<div class="jinyu-header-tools">
		<?php if ( jinyu_is_checked( 'header_social_enable' ) ) : ?>
		<div class="jinyu-header-social">
			<?php foreach ( jinyu_header_social() as $s ) :  /* phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- 模板按需在局部覆盖全局，已 wp_reset_postdata */ ?>
			<a href="<?php echo esc_url( $s['url'] ); ?>" target="_blank" rel="noopener nofollow"
			title="<?php echo esc_attr( $s['title'] ); ?>" aria-label="<?php echo esc_attr( $s['title'] ); ?>">
				<?php echo jinyu_social_icon_markup( $s['icon'] );  /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 输出经 esc_html/esc_attr/wp_kses 处理或为核心传入值/整型，WPCS 追不到集中式委托故误报 */ ?>
			</a>
		<?php endforeach; ?>
		</div>
		<?php endif; ?>

		<button type="button" class="jinyu-icon-btn" data-jinyu-search-toggle
				aria-label="<?php esc_attr_e( 'Search', 'jinyu' ); ?>">
		<i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
		</button>

		<button type="button" class="jinyu-icon-btn" data-jinyu-theme-toggle
				aria-label="<?php esc_attr_e( 'Toggle light/dark mode', 'jinyu' ); ?>" aria-pressed="false">
		<i class="fa-solid fa-moon" aria-hidden="true"></i>
		</button>

		<?php if ( is_user_logged_in() ) : ?>
		<div class="jinyu-user-menu" data-jinyu-user-menu>
			<button type="button" class="jinyu-user-btn" data-jinyu-user-toggle
					title="<?php esc_attr_e( 'User menu', 'jinyu' ); ?>"
					aria-expanded="false" aria-label="<?php echo esc_attr( wp_get_current_user()->display_name ); ?>">
			<img class="jinyu-user-avatar"
				src="
				<?php
					$uid        = get_current_user_id();
					$avatar_url = jinyu_user_avatar_url( $uid, 64 );
					echo $avatar_url ? esc_url( $avatar_url ) : esc_attr( jinyu_avatar_default( $uid ) );
				?>
				"
				data-jinyu-fallback="<?php echo esc_attr( jinyu_avatar_default( get_current_user_id() ) ); ?>"
				alt="">
			</button>
			<div class="jinyu-user-drop" data-jinyu-user-drop hidden>
			<div class="jinyu-user-drop-head">
				<img class="jinyu-user-drop-avatar"
					src="
					<?php
						$uid        = get_current_user_id();
						$avatar_url = jinyu_user_avatar_url( $uid, 64 );
						echo $avatar_url ? esc_url( $avatar_url ) : esc_attr( jinyu_avatar_default( $uid ) );
					?>
					"
					data-jinyu-fallback="<?php echo esc_attr( jinyu_avatar_default( get_current_user_id() ) ); ?>"
					alt="">
				<div class="jinyu-user-drop-info">
				<b><?php echo esc_html( wp_get_current_user()->display_name ); ?></b>
				<span><?php echo esc_html( jinyu_user_role_label( get_current_user_id() ) ); ?></span>
				</div>
			</div>
			<div class="jinyu-user-drop-sep"></div>
			<?php if ( jinyu_is_checked( 'user_center_enable' ) ) : ?>
				<a href="<?php echo esc_url( jinyu_user_page_url() ); ?>"><i class="fa-solid fa-gauge-high" aria-hidden="true"></i><?php esc_html_e( 'User Center', 'jinyu' ); ?></a>
			<?php endif; ?>
			<?php if ( jinyu_is_checked( 'user_can_submit' ) ) : ?>
				<a href="
				<?php
				echo esc_url(
					add_query_arg(
						[
							'tab'     => 'posts',
							'compose' => '1',
						],
						jinyu_user_page_url()
					)
				);
				?>
							"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i><?php esc_html_e( 'Submit Post', 'jinyu' ); ?></a>
			<?php endif; ?>
			<a href="<?php echo esc_url( jinyu_user_page_url( 'profile' ) ); ?>"><i class="fa-solid fa-user-gear" aria-hidden="true"></i><?php esc_html_e( 'Account Settings', 'jinyu' ); ?></a>
			<a href="<?php echo esc_url( jinyu_user_page_url( 'notifications' ) ); ?>" class="jinyu-user-notif-link">
				<i class="fa-solid fa-bell" aria-hidden="true"></i><?php esc_html_e( 'Messages', 'jinyu' ); ?>
				<?php $jinyu_unread = (int) jinyu_ext_value( 'unread_count', 0, get_current_user_id() ); ?>
				<?php
				if ( $jinyu_unread > 0 ) :
					?>
					<span class="jinyu-badge"><?php echo $jinyu_unread > 99 ? '99+' : (int) $jinyu_unread; ?></span><?php endif; ?>
			</a>
			<div class="jinyu-user-drop-sep"></div>
			<a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>" class="jinyu-user-drop-warn" data-jinyu-confirm="<?php esc_attr_e( 'Are you sure you want to log out?', 'jinyu' ); ?>"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i><?php esc_html_e( 'Log out', 'jinyu' ); ?></a>
			</div>
		</div>
		<?php elseif ( jinyu_is_checked( 'user_center_enable' ) ) : ?>
		<button type="button" class="jinyu-icon-btn" data-jinyu-auth-open="login" aria-label="<?php esc_attr_e( 'Log in', 'jinyu' ); ?>">
			<i class="fa-solid fa-user" aria-hidden="true"></i>
		</button>
		<?php endif; ?>
	</div><!-- /.jinyu-header-tools -->

	</div>
</header>

<?php $notice = trim( (string) jinyu_get_option( 'top_notice', '' ) ); ?>
<?php if ( $notice ) : ?>
	<aside class="jinyu-top-notice" aria-label="<?php esc_attr_e( 'Site Notice', 'jinyu' ); ?>">
	<div class="jinyu-container">
		<div class="jinyu-top-notice-card">
		<i class="fa-solid fa-bullhorn" aria-hidden="true"></i>
		<span><?php echo wp_kses_post( $notice ); ?></span>
		</div>
	</div>
	</aside>
<?php endif; ?>

<div class="jinyu-mask jinyu-search-mask" hidden role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Site search', 'jinyu' ); ?>">
	<div class="jinyu-search-panel">
	<?php get_search_form(); ?>
	<p class="jinyu-search-tip"><?php esc_html_e( 'Press Esc to close', 'jinyu' ); ?></p>
	</div>
</div>
