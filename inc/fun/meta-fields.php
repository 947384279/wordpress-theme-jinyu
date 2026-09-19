<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 文章自定义字段（Meta Box）：副标题 / 来源 / 外链 / 隐藏封面
 */

add_action('add_meta_boxes', function () {
    add_meta_box(
        'jinyu_post_meta',
        __('金玉 · 文章扩展字段', JINYU),
        'jinyu_post_meta_box',
        ['post', 'page'],
        'normal',
        'high'
    );
});

function jinyu_post_meta_box($post): void
{
    wp_nonce_field('jinyu_post_meta_nonce', 'jinyu_post_meta_nonce_field');
    $subtitle = get_post_meta($post->ID, '_jinyu_subtitle', true);
    $source   = get_post_meta($post->ID, '_jinyu_source', true);
    $ext      = get_post_meta($post->ID, '_jinyu_external_url', true);
    $hide     = (int) get_post_meta($post->ID, '_jinyu_hide_cover', true);
    ?>
    <p>
        <label for="jinyu_subtitle"><?php esc_html_e('文章副标题', JINYU); ?></label><br>
        <input type="text" id="jinyu_subtitle" name="jinyu_subtitle" value="<?php echo esc_attr($subtitle); ?>" class="widefat" placeholder="<?php esc_attr_e('显示在标题下方', JINYU); ?>">
    </p>
    <p>
        <label for="jinyu_source"><?php esc_html_e('文章来源', JINYU); ?></label><br>
        <input type="text" id="jinyu_source" name="jinyu_source" value="<?php echo esc_attr($source); ?>" class="widefat" placeholder="<?php esc_attr_e('如：转载自xxx / 原创', JINYU); ?>">
    </p>
    <p>
        <label for="jinyu_external_url"><?php esc_html_e('外链地址（跳转）', JINYU); ?></label><br>
        <input type="url" id="jinyu_external_url" name="jinyu_external_url" value="<?php echo esc_url($ext); ?>" class="widefat" placeholder="https://">
    </p>
    <p>
        <label><input type="checkbox" name="jinyu_hide_cover" value="1" <?php checked($hide, 1); ?>> <?php esc_html_e('隐藏正文特色封面图', JINYU); ?></label>
    </p>
    <?php
}

add_action('save_post', function ($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!isset($_POST['jinyu_post_meta_nonce_field']) || !wp_verify_nonce($_POST['jinyu_post_meta_nonce_field'], 'jinyu_post_meta_nonce')) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $fields = [
        '_jinyu_subtitle'      => 'jinyu_subtitle',
        '_jinyu_source'        => 'jinyu_source',
        '_jinyu_external_url'  => 'jinyu_external_url',
    ];
    foreach ($fields as $meta => $key) {
        if (isset($_POST[$key])) {
            update_post_meta($post_id, $meta, sanitize_text_field(wp_unslash($_POST[$key])));
        }
    }
    update_post_meta($post_id, '_jinyu_hide_cover', isset($_POST['jinyu_hide_cover']) ? 1 : 0);
});
