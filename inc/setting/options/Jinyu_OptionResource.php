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
            'title'  => __('资源优化', 'jinyu'),
            'desc'   => __('静态资源、脚本加载与性能优化选项。', 'jinyu'),
            'icon'   => 'fa-solid fa-compact-disc',
            'fields' => [
                ['id'=>'disable_gutenberg_editor','title'=>__('禁用古腾堡编辑器','jinyu'),'type'=>'switch','sdt'=>false,'desc'=>__('后台文章/页面改用经典编辑器撰写，关闭区块（Gutenberg）编辑器。仅影响撰写界面，不影响已发布内容','jinyu')],
                ['id'=>'cdn_url','title'=>__('静态资源 CDN 域名','jinyu'),'type'=>'text','sdt'=>'','desc'=>__('如 https://cdn.example.com ，仅替换上传目录附件 URL','jinyu')],

                /* ─── 整页缓存已迁至「性能优化」页统一管控（开关 + 有效期 + 一键清理） ─── */
            ],
        ];
    }
}
