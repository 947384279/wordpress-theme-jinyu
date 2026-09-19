<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if (jinyu_is_checked('related_enable')) {
    $num  = (int) jinyu_get_option('related_num', 4);
    $type = jinyu_get_option('related_type', 'tags');
    if ($q = jinyu_get_related_posts(0, $num, $type)) {
        if ($q->have_posts()) {
?>
<section class="jinyu-relevant">
    <h3 class="jinyu-widget-title">相关文章</h3>
    <div class="jinyu-relevant-grid">
        <?php while ($q->have_posts()): $q->the_post(); ?>
        <a class="jinyu-relevant-card" href="<?php the_permalink(); ?>">
            <div class="jinyu-relevant-cover">
                <?php $ph_rel = jinyu_get_post_cover(get_the_ID(), 'thumbnail'); ?>
                <img class="jinyu-blur-img" src="<?php echo esc_url(jinyu_get_post_cover()); ?>" alt=""<?php echo $ph_rel ? ' data-ph="' . esc_url($ph_rel) . '"' : ''; ?>>
            </div>
            <div class="jinyu-relevant-title"><?php the_title(); ?></div>
        </a>
        <?php endwhile; wp_reset_postdata(); ?>
    </div>
</section>
<?php } } } ?>