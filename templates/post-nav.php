<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 文章页底部：上一篇 / 下一篇 + 版权声明
 */
$prev = get_previous_post();
$next = get_next_post();
?>
<?php if (jinyu_is_checked('post_nav_enable')): ?>
<nav class="jinyu-post-nav" aria-label="<?php esc_attr_e('上一篇下一篇', JINYU); ?>">
    <?php if ($prev): ?>
        <a class="jinyu-post-nav-item jinyu-post-nav-prev" href="<?php echo esc_url(get_permalink($prev)); ?>">
            <span class="jinyu-post-nav-label"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> <?php esc_html_e('上一篇', JINYU); ?></span>
            <span class="jinyu-post-nav-title"><?php echo esc_html(get_the_title($prev)); ?></span>
        </a>
    <?php endif; ?>
    <?php if ($next): ?>
        <a class="jinyu-post-nav-item jinyu-post-nav-next" href="<?php echo esc_url(get_permalink($next)); ?>">
            <span class="jinyu-post-nav-label"><?php esc_html_e('下一篇', JINYU); ?> <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
            <span class="jinyu-post-nav-title"><?php echo esc_html(get_the_title($next)); ?></span>
        </a>
    <?php endif; ?>
</nav>
<?php endif; ?>

<?php
$cc = jinyu_get_option('single_copyright', '');
if ($cc === '') {
    $cc = sprintf(
        __('本文由 %1$s 创作，转载请注明来源：%2$s（%3$s）', JINYU),
        get_the_author(),
        get_the_title(),
        get_permalink()
    );
} else {
    $cc = str_replace(
        ['{url}', '{title}', '{author}', '{date}'],
        [get_permalink(), get_the_title(), get_the_author(), get_the_date('Y-m-d')],
        $cc
    );
}
?>
<div class="jinyu-copyright">
    <i class="fa-regular fa-copyright" aria-hidden="true"></i>
    <p class="jinyu-copyright-text"><?php echo wp_kses_post($cc); ?></p>
</div>
