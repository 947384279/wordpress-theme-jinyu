<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 分类 SEO：为每个分类补充「自定义关键词 / 描述」字段。
 * 数据存入 term meta（WP 4.4+ 原生能力，写法更规范），
 * 由 jinyu_seo_meta() 在分类归档页的 <head> 中输出。
 */
add_action('category_add_form_fields', 'jinyu_category_seo_add_fields');
add_action('category_edit_form_fields', 'jinyu_category_seo_edit_fields');
add_action('created_category', 'jinyu_category_seo_save', 10, 1);
add_action('edited_category', 'jinyu_category_seo_save', 10, 1);

function jinyu_category_seo_add_fields(): void
{
    $label_kw   = __('SEO 关键字', JINYU);
    $label_desc = __('SEO 描述', JINYU);
    $tip_kw     = __('多个关键字使用英文逗号分隔，留空则默认显示分类名称', JINYU);
    $tip_desc   = __('留空则默认显示分类描述', JINYU);
    echo <<<HTML
<div class="form-field">
    <label for="jinyu_seo_cat_keywords">{$label_kw}</label>
    <input name="jinyu_seo_cat_keywords" id="jinyu_seo_cat_keywords" type="text" value="" size="40">
    <p>{$tip_kw}</p>
</div>
<div class="form-field">
    <label for="jinyu_seo_cat_desc">{$label_desc}</label>
    <input name="jinyu_seo_cat_desc" id="jinyu_seo_cat_desc" type="text" value="" size="40">
    <p>{$tip_desc}</p>
</div>
HTML;
}

function jinyu_category_seo_edit_fields($term): void
{
    $kw   = get_term_meta($term->term_id, 'jinyu_seo_cat_keywords', true);
    $desc = get_term_meta($term->term_id, 'jinyu_seo_cat_desc', true);
    $kw_html   = esc_attr($kw);
    $desc_html = esc_attr($desc);
    $label_kw   = __('SEO 关键字', JINYU);
    $label_desc = __('SEO 描述', JINYU);
    $tip_kw     = __('多个关键字使用英文逗号分隔，留空则默认显示分类名称', JINYU);
    $tip_desc   = __('留空则默认显示分类描述', JINYU);
    echo <<<HTML
<tr class="form-field">
    <th scope="row"><label for="jinyu_seo_cat_keywords">{$label_kw}</label></th>
    <td>
        <input name="jinyu_seo_cat_keywords" id="jinyu_seo_cat_keywords" type="text" value="{$kw_html}" size="40"><br>
        <span class="description">{$tip_kw}</span>
    </td>
</tr>
<tr class="form-field">
    <th scope="row"><label for="jinyu_seo_cat_desc">{$label_desc}</label></th>
    <td>
        <input name="jinyu_seo_cat_desc" id="jinyu_seo_cat_desc" type="text" value="{$desc_html}" size="40"><br>
        <span class="description">{$tip_desc}</span>
    </td>
</tr>
HTML;
}

function jinyu_category_seo_save(int $term_id): void
{
    if (!current_user_can('manage_categories')) {
        return;
    }
    if (isset($_POST['jinyu_seo_cat_keywords'])) {
        update_term_meta($term_id, 'jinyu_seo_cat_keywords', sanitize_text_field($_POST['jinyu_seo_cat_keywords']));
    }
    if (isset($_POST['jinyu_seo_cat_desc'])) {
        update_term_meta($term_id, 'jinyu_seo_cat_desc', sanitize_text_field($_POST['jinyu_seo_cat_desc']));
    }
}
