<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
Template Name: 关于
*/
get_header();
?>
<div class="jinyu-container jinyu-main-wrap">
    <main id="jinyu-content" class="jinyu-content jinyu-single-wrap jinyu-wide-wrap">
        <?php jinyu_breadcrumbs(); ?>
        <article class="jinyu-single jinyu-about">
            <?php while ( have_posts() ) : the_post(); ?>
                <?php
                // 作者信息必须在 the_post() 之后取（依赖 $authordata），否则 get_the_author_meta() 拿不到值。
                $jinyu_about_uid  = (int) get_the_author_meta( 'ID' );
                $jinyu_about_url  = jinyu_user_avatar_url( $jinyu_about_uid, 96 );
                $jinyu_about_tiny = jinyu_avatar_default( $jinyu_about_uid );
                $jinyu_about_bio  = trim( (string) get_the_author_meta( 'description' ) );
                ?>
                <div class="jinyu-about-header">
                    <img class="jinyu-about-avatar"
                         src="<?php echo $jinyu_about_url ? esc_url( $jinyu_about_url ) : esc_attr( $jinyu_about_tiny ); ?>"
                         onerror="this.onerror=null;this.src='<?php echo esc_attr( $jinyu_about_tiny ); ?>';"
                         alt="">
                    <div class="jinyu-about-id">
                        <h1 class="jinyu-article-title"><?php echo esc_html( get_the_author() ); ?></h1>
                        <?php if ( '' !== $jinyu_about_bio ) : ?>
                            <div class="jinyu-term-desc"><?php echo wp_kses_post( $jinyu_about_bio ); ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ( trim( (string) get_the_content() ) !== '' ) : ?>
                    <div class="jinyu-article-content jinyu-about-content"><?php the_content(); ?></div>
                <?php elseif ( current_user_can( 'edit_pages' ) ) : ?>
                    <?php /* 正文为空时只对可编辑用户提示，访客看到的就是纯个人信息卡。 */ ?>
                    <div class="jinyu-about-empty">
                        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                        <p>
                            <?php
                            printf(
                                wp_kses(
                                    /* translators: 1: 本页编辑链接，2: 个人资料链接。 */
                                    __( '本页正文为空，访客只能看到上方头像与简介。请到 <a href="%1$s">后台编辑本页</a> 填写「关于」内容；头像与个人说明取自 <a href="%2$s">个人资料</a>。', 'jinyu'),
                                    [ 'a' => [ 'href' => [] ] ]
                                ),
                                esc_url( (string) get_edit_post_link() ),
                                esc_url( admin_url( 'profile.php' ) )
                            );
                            ?>
                        </p>
                    </div>
                <?php endif; ?>
            <?php endwhile; ?>
        </article>
    </main>
</div>
<?php get_footer(); ?>
