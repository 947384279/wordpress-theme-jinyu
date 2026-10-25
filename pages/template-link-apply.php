<?php
/**
 * 自定义页面：友情链接申请
 *
 * @package         WordPress
 * @subpackage      Jinyu
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
Template Name: Friend Link Application
*/
get_header();

// 站点档案：主题自持实现（名称/地址/描述/图标），任意插件可经 jinyu_flink_site_profile 覆盖.
$jinyu_site_icon = (string) get_site_icon_url( 192 );
if ( '' === $jinyu_site_icon ) {
	$jinyu_logo_id = (int) get_theme_mod( 'custom_logo' );
	if ( $jinyu_logo_id ) {
		$jinyu_site_icon = (string) wp_get_attachment_image_url( $jinyu_logo_id, 'full' );
	}
}
if ( '' === $jinyu_site_icon ) {
	$jinyu_site_icon = home_url( '/favicon.ico' );
}
$jinyu_site = wp_parse_args(
	(array) apply_filters(
		'jinyu_flink_site_profile',
		[
			'name' => (string) get_bloginfo( 'name' ),
			'url'  => home_url( '/' ),
			'desc' => (string) get_bloginfo( 'description' ),
			'icon' => $jinyu_site_icon,
		]
	),
	[
		'name' => '',
		'url'  => '',
		'desc' => '',
		'icon' => '',
	]
);
// 申请规则：后台多行文本按行切分，插件可经 jinyu_flink_apply_rules 整体接管.
$jinyu_rules      = (array) apply_filters(
	'jinyu_flink_apply_rules',
	preg_split( '/\R+/u', trim( (string) jinyu_get_option( 'flink_apply_rules', '' ) ), -1, PREG_SPLIT_NO_EMPTY ) ?: []
);
$jinyu_form_on    = jinyu_is_checked( 'flink_apply_form' );
$jinyu_my_mail    = is_user_logged_in() ? (string) wp_get_current_user()->user_email : '';
$jinyu_admin_mail = (string) get_option( 'admin_email' );

// 站点信息卡：键 => [显示值, 复制文案]（图标行单独渲染，值可能是图片）.
$jinyu_profile_rows = [
	[ __( 'Site name', 'jinyu' ), $jinyu_site['name'] ],
	[ __( 'Site URL', 'jinyu' ), $jinyu_site['url'] ],
	[ __( 'Site description', 'jinyu' ), $jinyu_site['desc'] ],
];
?>
<div class="jinyu-container jinyu-main-wrap">
	<main id="jinyu-content" class="jinyu-content jinyu-single-wrap jinyu-wide-wrap">
		<?php jinyu_breadcrumbs(); ?>
		<article class="jinyu-single jinyu-flink-apply-page">
			<?php
			while ( have_posts() ) :
				the_post();
				?>
				<h1 class="jinyu-article-title"><?php the_title(); ?></h1>
				<?php if ( trim( (string) get_the_content() ) !== '' ) : ?>
					<div class="jinyu-article-content"><?php the_content(); ?></div>
				<?php endif; ?>
			<?php endwhile; ?>

			<section class="jinyu-fa-card">
				<h2 class="jinyu-fa-title"><i class="fa-solid fa-id-card" aria-hidden="true"></i><?php esc_html_e( 'Our site details', 'jinyu' ); ?></h2>
				<p class="jinyu-fa-hint"><?php esc_html_e( 'Please add our site to your blogroll first, then fill in your site details in the form below.', 'jinyu' ); ?></p>
				<ul class="jinyu-fa-profile">
					<?php foreach ( $jinyu_profile_rows as $jinyu_row ) : ?>
						<?php
						if ( '' === trim( $jinyu_row[1] ) ) {
							continue; }
						?>
						<li>
							<span class="jinyu-fa-k"><?php echo esc_html( $jinyu_row[0] ); ?></span>
							<span class="jinyu-fa-v"><?php echo esc_html( $jinyu_row[1] ); ?></span>
							<button type="button" class="jinyu-fa-copy" data-jinyu-copy="<?php echo esc_attr( $jinyu_row[1] ); ?>">
								<i class="fa-regular fa-copy" aria-hidden="true"></i><span><?php esc_html_e( 'Copy', 'jinyu' ); ?></span>
							</button>
						</li>
					<?php endforeach; ?>
					<?php if ( '' !== $jinyu_site['icon'] ) : ?>
						<li>
							<span class="jinyu-fa-k"><?php esc_html_e( 'Site icon', 'jinyu' ); ?></span>
							<span class="jinyu-fa-v">
								<img class="jinyu-fa-thumb" src="<?php echo esc_url( $jinyu_site['icon'] ); ?>" alt="" loading="lazy" decoding="async" data-jinyu-fallback-remove>
								<?php echo esc_html( $jinyu_site['icon'] ); ?>
							</span>
							<button type="button" class="jinyu-fa-copy" data-jinyu-copy="<?php echo esc_attr( $jinyu_site['icon'] ); ?>">
								<i class="fa-regular fa-copy" aria-hidden="true"></i><span><?php esc_html_e( 'Copy', 'jinyu' ); ?></span>
							</button>
						</li>
					<?php endif; ?>
				</ul>
			</section>

			<?php if ( $jinyu_rules ) : ?>
				<section class="jinyu-fa-card">
					<h2 class="jinyu-fa-title"><i class="fa-solid fa-list-check" aria-hidden="true"></i><?php esc_html_e( 'Requirements', 'jinyu' ); ?></h2>
					<ul class="jinyu-fa-rules">
						<?php foreach ( $jinyu_rules as $jinyu_rule ) : ?>
							<li><?php echo esc_html( $jinyu_rule ); ?></li>
						<?php endforeach; ?>
					</ul>
				</section>
			<?php endif; ?>

			<section class="jinyu-fa-card">
				<h2 class="jinyu-fa-title"><i class="fa-solid fa-rotate" aria-hidden="true"></i><?php esc_html_e( 'About backlink checks', 'jinyu' ); ?></h2>
				<ul class="jinyu-fa-rules">
					<li><?php esc_html_e( 'To keep the blogroll high-quality, published friend links are re-checked automatically every 7 days.', 'jinyu' ); ?></li>
					<?php /* translators: %s: 占位符 */ ?>
					<li><?php printf( esc_html__( 'The check fetches 1) the friend-link page URL you provided and 2) your homepage, to detect a link pointing back to this site (%s).', 'jinyu' ), esc_html( parse_url( home_url(), PHP_URL_HOST ) ) );  /* phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- 解析已知格式 URL，wp_parse_url 与其等价 */ ?></li>
					<?php /* translators: %s: 占位符 */ ?>
					<li><?php printf( esc_html__( 'The check uses the user agent %s (browser identifier + site signature). Make sure it can access the friend-link page, and do not block this UA in your firewall / CDN / WAF.', 'jinyu' ), esc_html( function_exists( 'jinyu_backlink_bot_name' ) ? jinyu_backlink_bot_name() : __( 'this site\'s link checker', 'jinyu' ) ) ); ?></li>
					<li><?php esc_html_e( 'If your friend links live on a subpage, enter the real friend-link page URL — checking the homepage alone often misses them.', 'jinyu' ); ?></li>
					<li><?php esc_html_e( 'If no backlink is detected, the site owner is notified by email and the friend link is removed automatically (set to draft). Re-submit the application after restoring the link to have it published again.', 'jinyu' ); ?></li>
				</ul>
			</section>

			<section class="jinyu-fa-card">
				<h2 class="jinyu-fa-title"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i><?php esc_html_e( 'Submit application', 'jinyu' ); ?></h2>
				<?php if ( ! $jinyu_form_on ) : ?>
					<p class="jinyu-fa-hint"><?php esc_html_e( 'We currently only accept applications by email. Please send your site name, URL, and description to:', 'jinyu' ); ?>
						<a href="mailto:<?php echo esc_attr( $jinyu_admin_mail ); ?>"><?php echo esc_html( $jinyu_admin_mail ); ?></a>
					</p>
				<?php else : ?>
					<form class="jinyu-fa-form" data-jinyu-flink-apply method="post">
						<div class="jinyu-fa-grid">
							<label class="jinyu-fa-field">
								<span class="jinyu-fa-label"><?php esc_html_e( 'Site name', 'jinyu' ); ?> <i class="jinyu-fa-req">*</i></span>
								<input type="text" name="site_name" maxlength="50" autocomplete="off" placeholder="<?php esc_attr_e( 'e.g. Example Blog', 'jinyu' ); ?>">
							</label>
							<label class="jinyu-fa-field">
								<span class="jinyu-fa-label"><?php esc_html_e( 'Site URL', 'jinyu' ); ?> <i class="jinyu-fa-req">*</i></span>
								<input type="url" name="site_url" maxlength="120" autocomplete="off" placeholder="https://example.com">
							</label>
							<label class="jinyu-fa-field">
								<span class="jinyu-fa-label"><?php esc_html_e( 'Contact email', 'jinyu' ); ?> <i class="jinyu-fa-req">*</i></span>
								<input type="email" name="site_email" maxlength="80" autocomplete="off"
										value="<?php echo esc_attr( $jinyu_my_mail ); ?>"
										placeholder="<?php esc_attr_e( 'Used to receive the review result', 'jinyu' ); ?>">
							</label>
							<label class="jinyu-fa-field">
								<span class="jinyu-fa-label"><?php esc_html_e( 'Site icon', 'jinyu' ); ?></span>
								<input type="url" name="site_icon" maxlength="200" autocomplete="off" placeholder="<?php esc_attr_e( 'Optional, e.g. https://example.com/logo.png', 'jinyu' ); ?>">
							</label>
							<label class="jinyu-fa-field jinyu-fa-field-full">
								<span class="jinyu-fa-label"><?php esc_html_e( 'Friend-link page URL', 'jinyu' ); ?></span>
								<input type="url" name="site_backlink_url" maxlength="200" autocomplete="off" placeholder="<?php esc_attr_e( 'Optional. The page that carries our link, for easier backlink checks', 'jinyu' ); ?>">
							</label>
							<label class="jinyu-fa-field jinyu-fa-field-full">
								<span class="jinyu-fa-label"><?php esc_html_e( 'Site description', 'jinyu' ); ?></span>
								<input type="text" name="site_desc" maxlength="60" autocomplete="off" placeholder="<?php esc_attr_e( 'Optional, one-line intro (within 60 characters)', 'jinyu' ); ?>">
							</label>
						</div>
						<input type="text" name="jy_hp" class="jinyu-fa-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
						<div class="jinyu-fa-foot">
							<?php echo jinyu_ext_markup( 'captcha', 'flink_apply' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 扩展插槽 HTML 由插件自行转义输出 ?>
							<button type="submit" class="jinyu-btn jinyu-btn-primary"><?php esc_html_e( 'Submit application', 'jinyu' ); ?></button>
						</div>
						<p class="jinyu-auth-tip" data-jinyu-flink-tip aria-live="polite"></p>
					</form>
					<div class="jinyu-fa-done" data-jinyu-flink-done hidden>
						<i class="fa-solid fa-circle-check" aria-hidden="true"></i>
						<h3><?php esc_html_e( 'Application submitted', 'jinyu' ); ?></h3>
						<p><?php esc_html_e( 'The site owner reviews applications manually. Once approved, your site will appear in the blogroll.', 'jinyu' ); ?></p>
					</div>
				<?php endif; ?>
			</section>
		</article>
	</main>
</div>
<?php
get_footer();
