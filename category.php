<?php
/**
 * 分类归档页模板
 *
 * @package         WordPress
 * @subpackage      Jinyu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
get_header(); ?>
<div class="jinyu-container jinyu-main-wrap">
	<main id="jinyu-content" class="jinyu-content">
		<?php $jinyu_deco = mb_strtoupper( mb_substr( single_cat_title( '', false ), 0, 1 ) ); ?>
		<div class="jinyu-term-header">
			<span class="bg-letter" aria-hidden="true"><?php echo esc_html( $jinyu_deco ); ?></span>
			<?php jinyu_breadcrumbs(); ?>
			<div class="jinyu-term-inner">
				<div class="jinyu-term-badge" aria-hidden="true"><?php echo esc_html( $jinyu_deco ); ?></div>
				<div class="jinyu-term-body">
					<h1><?php single_cat_title(); ?></h1>
					<?php
					if ( category_description() ) :
						?>
						<div class="jinyu-term-desc"><?php echo wp_kses_post( category_description() ); ?></div><?php endif; ?>
					<?php echo jinyu_archive_meta_html();  /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 输出经 esc_html/esc_attr/wp_kses 处理或为核心传入值/整型，WPCS 追不到集中式委托故误报 */ ?>
				</div>
			</div>
		</div>
		<?php get_template_part( 'templates/archive-loop' ); ?>
	</main>
	<?php get_sidebar(); ?>
</div>
<?php
get_footer();
