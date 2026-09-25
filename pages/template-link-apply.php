<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
Template Name: 申请友链
*/
get_header();

$jinyu_site     = function_exists('jinyu_flink_site_profile') ? jinyu_flink_site_profile() : ['name' => '', 'url' => '', 'desc' => '', 'icon' => ''];
$jinyu_rules    = function_exists('jinyu_flink_multi_to_list') ? jinyu_flink_multi_to_list( (string) jinyu_get_option( 'flink_apply_rules', '' ) ) : [];
$jinyu_form_on  = jinyu_is_checked( 'flink_apply_form' );
$jinyu_my_mail  = is_user_logged_in() ? (string) wp_get_current_user()->user_email : '';
$jinyu_admin_mail = (string) get_option( 'admin_email' );

// 站点信息卡：键 => [显示值, 复制文案]（图标行单独渲染，值可能是图片）
$jinyu_profile_rows = [
	[ __( '网站名称', 'jinyu'), $jinyu_site['name'] ],
	[ __( '网站地址', 'jinyu'), $jinyu_site['url'] ],
	[ __( '网站描述', 'jinyu'), $jinyu_site['desc'] ],
];
?>
<div class="jinyu-container jinyu-main-wrap">
    <main id="jinyu-content" class="jinyu-content jinyu-single-wrap jinyu-wide-wrap">
        <?php jinyu_breadcrumbs(); ?>
        <article class="jinyu-single jinyu-flink-apply-page">
            <?php while ( have_posts() ) : the_post(); ?>
                <h1 class="jinyu-article-title"><?php the_title(); ?></h1>
                <?php if ( trim( (string) get_the_content() ) !== '' ) : ?>
                    <div class="jinyu-article-content"><?php the_content(); ?></div>
                <?php endif; ?>
            <?php endwhile; ?>

            <section class="jinyu-fa-card">
                <h2 class="jinyu-fa-title"><i class="fa-solid fa-id-card" aria-hidden="true"></i><?php esc_html_e( '本站信息', 'jinyu'); ?></h2>
                <p class="jinyu-fa-hint"><?php esc_html_e( '请先在贵站添加本站友链，再按下表填写本站信息。', 'jinyu'); ?></p>
                <ul class="jinyu-fa-profile">
                    <?php foreach ( $jinyu_profile_rows as $jinyu_row ) : ?>
                        <?php if ( '' === trim( $jinyu_row[1] ) ) { continue; } ?>
                        <li>
                            <span class="jinyu-fa-k"><?php echo esc_html( $jinyu_row[0] ); ?></span>
                            <span class="jinyu-fa-v"><?php echo esc_html( $jinyu_row[1] ); ?></span>
                            <button type="button" class="jinyu-fa-copy" data-jinyu-copy="<?php echo esc_attr( $jinyu_row[1] ); ?>">
                                <i class="fa-regular fa-copy" aria-hidden="true"></i><span><?php esc_html_e( '复制', 'jinyu'); ?></span>
                            </button>
                        </li>
                    <?php endforeach; ?>
                    <?php if ( '' !== $jinyu_site['icon'] ) : ?>
                        <li>
                            <span class="jinyu-fa-k"><?php esc_html_e( '网站图标', 'jinyu'); ?></span>
                            <span class="jinyu-fa-v">
                                <img class="jinyu-fa-thumb" src="<?php echo esc_url( $jinyu_site['icon'] ); ?>" alt="" loading="lazy" decoding="async" data-jinyu-fallback-remove>
                                <?php echo esc_html( $jinyu_site['icon'] ); ?>
                            </span>
                            <button type="button" class="jinyu-fa-copy" data-jinyu-copy="<?php echo esc_attr( $jinyu_site['icon'] ); ?>">
                                <i class="fa-regular fa-copy" aria-hidden="true"></i><span><?php esc_html_e( '复制', 'jinyu'); ?></span>
                            </button>
                        </li>
                    <?php endif; ?>
                </ul>
            </section>

            <?php if ( $jinyu_rules ) : ?>
                <section class="jinyu-fa-card">
                    <h2 class="jinyu-fa-title"><i class="fa-solid fa-list-check" aria-hidden="true"></i><?php esc_html_e( '申请要求', 'jinyu'); ?></h2>
                    <ul class="jinyu-fa-rules">
                        <?php foreach ( $jinyu_rules as $jinyu_rule ) : ?>
                            <li><?php echo esc_html( $jinyu_rule ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

            <section class="jinyu-fa-card">
                <h2 class="jinyu-fa-title"><i class="fa-solid fa-rotate" aria-hidden="true"></i><?php esc_html_e( '回链巡检说明', 'jinyu'); ?></h2>
                <ul class="jinyu-fa-rules">
                    <li><?php esc_html_e( '为保障友链质量，本站每 7 天自动巡检一次已发布的友链。', 'jinyu'); ?></li>
                    <li><?php printf( esc_html__( '巡检会抓取：① 您填写的「友链页地址」；② 贵站首页，检测页面是否含指向本站（%s）的链接。', 'jinyu' ), esc_html( parse_url( home_url(), PHP_URL_HOST ) ) ); ?></li>
                    <li><?php printf( esc_html__( '巡检请求 UA 为 %s（浏览器标识 + 站点署名），请确保其可正常访问友链页，勿在防火墙 / CDN / WAF 中封禁该 UA。', 'jinyu' ), esc_html( function_exists( 'jinyu_backlink_bot_name' ) ? jinyu_backlink_bot_name() : __( '本站巡检程序', 'jinyu' ) ) ); ?></li>
                    <li><?php esc_html_e( '若友链在二级页，请务必填写真实的「友链页地址」——只抓首页容易漏检。', 'jinyu'); ?></li>
                    <li><?php esc_html_e( '若巡检未检测到回链，系统会邮件通知站长并自动将该友链撤下（转为草稿）；补回链接后重新提交申请即可恢复。', 'jinyu'); ?></li>
                </ul>
            </section>

            <section class="jinyu-fa-card">
                <h2 class="jinyu-fa-title"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i><?php esc_html_e( '提交申请', 'jinyu'); ?></h2>
                <?php if ( ! $jinyu_form_on ) : ?>
                    <p class="jinyu-fa-hint"><?php esc_html_e( '本站当前只接受邮件申请，请把网站名称、网址、描述发送至：', 'jinyu'); ?>
                        <a href="mailto:<?php echo esc_attr( $jinyu_admin_mail ); ?>"><?php echo esc_html( $jinyu_admin_mail ); ?></a>
                    </p>
                <?php else : ?>
                    <form class="jinyu-fa-form" data-jinyu-flink-apply method="post">
                        <div class="jinyu-fa-grid">
                            <label class="jinyu-fa-field">
                                <span class="jinyu-fa-label"><?php esc_html_e( '网站名称', 'jinyu'); ?> <i class="jinyu-fa-req">*</i></span>
                                <input type="text" name="site_name" maxlength="50" autocomplete="off" placeholder="<?php esc_attr_e( '如：七彩云博客', 'jinyu'); ?>">
                            </label>
                            <label class="jinyu-fa-field">
                                <span class="jinyu-fa-label"><?php esc_html_e( '网站地址', 'jinyu'); ?> <i class="jinyu-fa-req">*</i></span>
                                <input type="url" name="site_url" maxlength="120" autocomplete="off" placeholder="https://example.com">
                            </label>
                            <label class="jinyu-fa-field">
                                <span class="jinyu-fa-label"><?php esc_html_e( '联系邮箱', 'jinyu'); ?> <i class="jinyu-fa-req">*</i></span>
                                <input type="email" name="site_email" maxlength="80" autocomplete="off"
                                       value="<?php echo esc_attr( $jinyu_my_mail ); ?>"
                                       placeholder="<?php esc_attr_e( '用于接收审核结果', 'jinyu'); ?>">
                            </label>
                            <label class="jinyu-fa-field">
                                <span class="jinyu-fa-label"><?php esc_html_e( '网站图标', 'jinyu'); ?></span>
                                <input type="url" name="site_icon" maxlength="200" autocomplete="off" placeholder="<?php esc_attr_e( '选填，如 https://example.com/logo.png', 'jinyu'); ?>">
                            </label>
                            <label class="jinyu-fa-field jinyu-fa-field-full">
                                <span class="jinyu-fa-label"><?php esc_html_e( '友链页地址', 'jinyu'); ?></span>
                                <input type="url" name="site_backlink_url" maxlength="200" autocomplete="off" placeholder="<?php esc_attr_e( '选填，挂着本站友链的页面，便于回链巡检', 'jinyu'); ?>">
                            </label>
                            <label class="jinyu-fa-field jinyu-fa-field-full">
                                <span class="jinyu-fa-label"><?php esc_html_e( '网站描述', 'jinyu'); ?></span>
                                <input type="text" name="site_desc" maxlength="60" autocomplete="off" placeholder="<?php esc_attr_e( '选填，一句话介绍（60 字内）', 'jinyu'); ?>">
                            </label>
                        </div>
                        <input type="text" name="jy_hp" class="jinyu-fa-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
                        <div class="jinyu-fa-foot">
                            <?php echo function_exists('jinyu_captcha_markup') ? jinyu_captcha_markup( 'flink_apply' ) : ''; ?>
                            <button type="submit" class="jinyu-btn jinyu-btn-primary"><?php esc_html_e( '提交申请', 'jinyu'); ?></button>
                        </div>
                        <p class="jinyu-auth-tip" data-jinyu-flink-tip></p>
                    </form>
                    <div class="jinyu-fa-done" data-jinyu-flink-done hidden>
                        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                        <h3><?php esc_html_e( '申请已提交', 'jinyu'); ?></h3>
                        <p><?php esc_html_e( '站长会人工审核，通过后你的站点会出现在本站友链列表中。', 'jinyu'); ?></p>
                    </div>
                <?php endif; ?>
            </section>
        </article>
    </main>
</div>
<?php get_footer(); ?>
