<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 金玉友情链接 - 自定义 post type + 后台管理
add_action('init', function(){
    if (!post_type_exists('jy_link')) {
        register_post_type('jy_link', [
            'labels' => ['name'=>'友情链接','singular_name'=>'链接','menu_name'=>'友情链接'],
            'public' => false, 'show_ui' => true, 'show_in_menu' => true,
            'menu_icon' => 'dashicons-admin-links', 'show_in_rest' => false,
            'supports' => ['title','thumbnail'],
            'menu_position' => 56,
        ]);
    }
    register_taxonomy('jy_link_cat', 'jy_link', [
        'label'=>'链接分类','public'=>false,'show_ui'=>true,'hierarchical'=>true,'show_in_rest'=>false,
    ]);
});

// 链接 meta box
add_action('add_meta_boxes', function(){
    add_meta_box('jy_link_meta', '链接信息', function($post){
        $url = get_post_meta($post->ID, 'jy_link_url', true);
        $desc = get_post_meta($post->ID, 'jy_link_desc', true);
        echo '<p>URL <input type="text" name="jy_link_url" value="'.esc_attr($url).'" class="widefat"></p>';
        echo '<p>描述 <textarea name="jy_link_desc" rows="2" class="widefat">'.esc_textarea($desc).'</textarea></p>';
    }, 'jy_link', 'normal', 'high');
});
add_action('save_post_jy_link', function($pid){
    if (isset($_POST['jy_link_url'])) update_post_meta($pid, 'jy_link_url', esc_url_raw($_POST['jy_link_url']));
    if (isset($_POST['jy_link_desc'])) update_post_meta($pid, 'jy_link_desc', sanitize_textarea_field($_POST['jy_link_desc']));
});

// 前端输出
function jinyu_get_links($cat = '')
{
    $args = ['post_type'=>'jy_link','posts_per_page'=>-1,'orderby'=>'menu_order title','order'=>'ASC','no_found_rows'=>true];
    if ($cat) $args['tax_query'] = [['taxonomy'=>'jy_link_cat','field'=>'slug','terms'=>$cat]];
    return get_posts($args);
}