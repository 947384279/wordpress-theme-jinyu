<?php
/**
 * 站点底部
 *
 * @package WordPress
 * @subpackage Jinyu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<footer class="jinyu-footer">
	<div class="jinyu-container">
	<?php
	// 页脚小工具区（外观 › 小工具 › 底部小工具）：没放东西时一个节点都不输出.
	if ( is_active_sidebar( 'sidebar-footer' ) ) :
		?>
	<div class="jinyu-footer-widgets">
		<?php dynamic_sidebar( 'sidebar-footer' ); ?>
	</div>
	<?php endif; ?>

	<?php
	// 页脚区块改为「有内容自动显示」（不再需要各自的总开关）：.
	// 品牌区=有介绍文案；导航列=已分配页脚菜单。皆无时不输出内区容器.
	$jinyu_footer_about = trim( (string) jinyu_get_option( 'footer_about', '' ) );
	if ( '' !== $jinyu_footer_about || has_nav_menu( 'footer' ) ) :
		?>
	<div class="jinyu-footer-inner">

		<?php if ( '' !== $jinyu_footer_about ) : ?>
		<div class="jinyu-footer-brand">
		<div class="jinyu-footer-brand-name">
			<i class="fa-solid fa-gem" aria-hidden="true"></i>
			<span><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span>
		</div>
		<p class="jinyu-footer-brand-desc"><?php echo wp_kses_post( jinyu_get_option( 'footer_about', get_bloginfo( 'description' ) ) ); ?></p>
		</div>
		<?php endif; ?>

		<?php if ( has_nav_menu( 'footer' ) ) : ?>
		<div class="jinyu-footer-links">
		<div class="jinyu-footer-col">
			<h4><i class="fa-solid fa-compass" aria-hidden="true"></i><?php esc_html_e( 'Quick Links', 'jinyu' ); ?></h4>
			<?php
			wp_nav_menu(
				[
					'theme_location' => 'footer',
					'menu_class'     => '',
					'container'      => false,
					'depth'          => 1,
					'fallback_cb'    => false,
				]
			);
			?>
		</div>
		</div>
		<?php endif; ?>

	</div>
	<?php endif; ?>

	<div class="jinyu-footer-bottom">
		<div class="jinyu-footer-meta">
		<div class="jinyu-footer-info">
			<?php
			$copy = trim( (string) jinyu_get_option( 'footer_copyright', '' ) );
			if ( $copy ) {
				echo wp_kses_post( str_replace( [ '{year}', '{name}' ], [ date( 'Y' ), get_bloginfo( 'name' ) ], $copy ) ); /* phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date -- date() 符合业务逻辑，或后续改 wp_date() */
			} else {
				echo wp_kses_post( jinyu_footer_copyright() );
			}
			$icp = trim( (string) jinyu_get_option( 'company_icp', '' ) );
			?>
			<?php if ( $icp ) : ?>
			<span class="jinyu-footer-icp"><a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener nofollow"><?php echo esc_html( $icp ); ?></a></span>
			<?php endif; ?>

			<?php if ( jinyu_get_option( 'footer_runinfo', 0 ) ) : ?>
				<?php
				$ri_t0  = (float) ( $_SERVER['REQUEST_TIME_FLOAT'] ?? 0 ); /* phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- 只读计时基准，不落库不回显 */
				$ri_gen = $ri_t0 > 0 ? max( 0.0, microtime( true ) - $ri_t0 ) : 0.0;
				?>
			<span class="jinyu-footer-runinfo" data-jinyu-runinfo>
				<span class="jinyu-runinfo-item"><i class="fa-solid fa-database" aria-hidden="true" title="<?php esc_attr_e( 'Queries', 'jinyu' ); ?>"></i> <b data-ri="q"><?php echo (int) get_num_queries(); ?></b> <?php esc_html_e( '次', 'jinyu' ); ?></span>
				<span class="jinyu-runinfo-sep">·</span>
				<span class="jinyu-runinfo-item"><i class="fa-solid fa-stopwatch" aria-hidden="true" title="<?php esc_attr_e( 'Page generation time', 'jinyu' ); ?>"></i> <b data-ri="ms"><?php echo esc_html( number_format_i18n( $ri_gen, 3 ) ); ?></b> <?php esc_html_e( 'sec', 'jinyu' ); ?></span>
			</span>
			<?php endif; ?>
		</div>

		<?php /* 主题署名：硬编码静态输出，无后台开关、无链接；社交图标与 WP 圆标内联在右侧 */ ?>
		<div class="jinyu-footer-credit">
			<a class="jinyu-wp-official" href="https://cn.wordpress.org/" target="_blank" rel="noopener nofollow" title="<?php esc_attr_e( 'Official WordPress website', 'jinyu' ); ?>">
			<i class="fa-brands fa-wordpress" aria-hidden="true"></i>
			</a>
			<?php $social = jinyu_footer_social(); ?>
			<?php if ( $social ) : ?>
			<span class="jinyu-social-row">
				<?php foreach ( $social as $s ) :  /* phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- 模板按需在局部覆盖全局，已 wp_reset_postdata */ ?>
				<a href="<?php echo esc_url( $s['url'] ); ?>" target="_blank" rel="noopener nofollow"
				title="<?php echo esc_attr( $s['title'] ); ?>" aria-label="<?php echo esc_attr( $s['title'] ); ?>">
					<?php echo jinyu_social_icon_markup( $s['icon'] );  /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 输出经 esc_html/esc_attr/wp_kses 处理或为核心传入值/整型，WPCS 追不到集中式委托故误报 */ ?>
				</a>
			<?php endforeach; ?>
			</span>
			<?php endif; ?>
		</div>
		</div>
	</div>
	</div>
</footer>

<div class="jinyu-scroll-util" aria-hidden="true">
	<button type="button" class="jinyu-scroll-btn jinyu-go-bottom" title="<?php esc_attr_e( 'To bottom', 'jinyu' ); ?>" aria-label="<?php esc_attr_e( 'To bottom', 'jinyu' ); ?>">
	<i class="fa-solid fa-arrow-down" aria-hidden="true"></i>
	</button>
	<button type="button" class="jinyu-scroll-btn jinyu-back-top" title="<?php esc_attr_e( 'Back to top', 'jinyu' ); ?>" aria-label="<?php esc_attr_e( 'Back to top', 'jinyu' ); ?>">
	<i class="fa-solid fa-arrow-up" aria-hidden="true"></i>
	</button>
</div>

<?php get_template_part( 'templates/auth', 'modal' ); ?>

<?php wp_footer(); ?>
</body>
</html>
