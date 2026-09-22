<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 文章页操作栏：点赞 / 收藏 / 分享
 * 点赞数存 post meta（jinyu_likes）；收藏对登录用户存 user meta，未登录降级到 localStorage。
 */
$pid   = get_the_ID();
$liked = jinyu_is_liked($pid);
$faved = in_array($pid, jinyu_get_user_favs(), true);
?>
<div class="jinyu-post-actions">

    <?php if (jinyu_is_checked('like_enable')): ?>
    <button type="button"
            class="jinyu-action-btn jinyu-like-btn<?php echo $liked ? ' jinyu-liked' : ''; ?>"
            data-post-id="<?php echo esc_attr($pid); ?>"
            aria-pressed="<?php echo $liked ? 'true' : 'false'; ?>">
        <i class="fa-solid fa-heart" aria-hidden="true"></i>
        <span><?php esc_html_e('点赞', 'jinyu'); ?></span>
        <span class="jinyu-like-count"><?php echo esc_html(jinyu_get_post_likes($pid)); ?></span>
    </button>
    <?php endif; ?>

    <?php if (jinyu_is_checked('fav_enable')): ?>
    <button type="button"
            class="jinyu-action-btn jinyu-fav-btn<?php echo $faved ? ' jinyu-faved' : ''; ?>"
            data-post-id="<?php echo esc_attr($pid); ?>"
            data-title="<?php the_title_attribute(); ?>"
            aria-pressed="<?php echo $faved ? 'true' : 'false'; ?>">
        <i class="fa-solid fa-star" aria-hidden="true"></i>
        <span class="jinyu-fav-label"><?php echo $faved ? esc_html__('已收藏', 'jinyu') : esc_html__('收藏', 'jinyu'); ?></span>
    </button>
    <?php endif; ?>

    <?php if (jinyu_is_checked('share_enable')): ?>
    <button type="button" class="jinyu-action-btn jinyu-share-btn" data-jinyu-share>
        <i class="fa-solid fa-share-nodes" aria-hidden="true"></i>
        <span><?php esc_html_e('分享', 'jinyu'); ?></span>
    </button>
    <?php endif; ?>

    <?php if (jinyu_is_checked('poster_enable')): ?>
    <button type="button" class="jinyu-action-btn jinyu-poster-btn" data-jinyu-poster data-post-id="<?php echo esc_attr($pid); ?>" data-cover="<?php echo esc_url(jinyu_get_post_cover($pid, 'large', false)); ?>">
        <i class="fa-solid fa-image" aria-hidden="true"></i>
        <span><?php esc_html_e('海报', 'jinyu'); ?></span>
    </button>
    <?php endif; ?>

    <?php if (jinyu_is_checked('qr_enable')): ?>
    <button type="button" class="jinyu-action-btn jinyu-qr-btn" data-jinyu-qr data-post-id="<?php echo esc_attr($pid); ?>" data-url="<?php echo esc_url(get_permalink()); ?>">
        <i class="fa-solid fa-qrcode" aria-hidden="true"></i>
        <span><?php esc_html_e('二维码', 'jinyu'); ?></span>
    </button>
    <?php endif; ?>

    <?php
        // 打赏改为「上传任一收款码即显示」，不再需要单独的总开关（原开关与「是否配置收款码」完全等价）
        $wx  = jinyu_get_option('reward_wechat', '');
        $ali = jinyu_get_option('reward_alipay', '');
        if ($wx || $ali) : ?>
            <button type="button" class="jinyu-action-btn jinyu-donate-btn" data-jinyu-donate
                    data-wx="<?php echo esc_url($wx); ?>" data-ali="<?php echo esc_url($ali); ?>">
                <i class="fa-solid fa-mug-hot" aria-hidden="true"></i>
                <span><?php esc_html_e('打赏', 'jinyu'); ?></span>
            </button>
        <?php endif; ?>

</div>
