<?php

namespace Jinyu\Theme\setting\options;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Jinyu_OptionResource extends Jinyu_BaseOptionItem
{
    public function get_fields(): array
    {
        return [
            'key'    => 'resource',
            'title'  => __('资源优化', JINYU),
            'desc'   => __('静态资源、脚本加载与性能优化选项。', JINYU),
            'icon'   => 'fa-solid fa-compact-disc',
            'fields' => [
                ['id'=>'disable_dashicons','title'=>__('非管理员移除 Dashicons',JINYU),'type'=>'switch','sdt'=>false],
                ['id'=>'disable_wp_embed','title'=>__('禁用 wp-embed',JINYU),'type'=>'switch','sdt'=>false,'desc'=>__('停止加载 wp-embed.min.js',JINYU)],
                ['id'=>'disable_gutenberg_css','title'=>__('禁用前台古登堡样式',JINYU),'type'=>'switch','sdt'=>false],
                ['id'=>'disable_gutenberg_editor','title'=>__('禁用古腾堡编辑器',JINYU),'type'=>'switch','sdt'=>false,'desc'=>__('后台文章/页面改用经典编辑器撰写，关闭区块（Gutenberg）编辑器。仅影响撰写界面，不影响已发布内容',JINYU)],
                ['id'=>'cdn_url','title'=>__('静态资源 CDN 域名',JINYU),'type'=>'text','sdt'=>'','desc'=>__('如 https://cdn.example.com ，仅替换上传目录附件 URL',JINYU)],
                ['id'=>'webp_enable','title'=>__('WebP 图片优化',JINYU),'type'=>'switch','sdt'=>true,'desc'=>__('需服务器开启 GD 扩展。开启后封面/卡片等主题图片输出自动替换为 WebP（按需生成副本，存量图片无需手动处理），关闭则回退原图',JINYU)],

                /* ─── 图片优化（前台生效） ─── */
                ['id'=>'auto_img_alt','title'=>__('自动补充图片 alt',JINYU),'type'=>'switch','sdt'=>true,'desc'=>__('图片缺 alt 时用文章标题补全（利于 SEO）',JINYU)],

                /* ─── 页面缓存 ─── */
                ['id'=>'page_cache_enable','title'=>__('启用页面缓存',JINYU),'type'=>'switch','sdt'=>true,'desc'=>__('对未登录访客输出整页缓存，首屏 TTFB 从数百毫秒降至数毫秒；内容更新自动失效',JINYU)],
                ['id'=>'page_cache_ttl','title'=>__('缓存有效期 (秒)',JINYU),'type'=>'number','sdt'=>3600],
                ['id'=>'page_cache_clear','title'=>__('清理缓存',JINYU),'type'=>'info','desc'=>__('点击下方「维护工具 › 清理主题缓存」可立即清空全部主题缓存',JINYU)],
            ],
        ];
    }
}
