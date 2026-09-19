<?php

namespace Jinyu\Theme\setting\options;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Jinyu_OptionCarousel extends Jinyu_BaseOptionItem
{
    public function get_fields(): array
    {
        return [
            'key'    => 'home_modules',
            'title'  => __('首页模块', JINYU),
            'icon'   => 'fa-solid fa-layer-group',
            'desc'   => __('首页顶部版块：幻灯片、四宫格、分类两栏，以及首页主循环排除分类。', JINYU),
            'fields' => [
                /* ─── 首页幻灯片 ─── */
                ['id'=>'home_carousel','title'=>(__('启用首页轮播',JINYU)),'type'=>'switch','sdt'=>false],
                ['id'=>'home_carousel_mousewheel','title'=>(__('鼠标滚轮切换',JINYU)),'type'=>'switch','sdt'=>true,'showRefId'=>'home_carousel'],
                ['id'=>'home_carousel_hide_title','title'=>(__('隐藏标题',JINYU)),'type'=>'switch','sdt'=>false,'showRefId'=>'home_carousel'],
                ['id'=>'home_carousel_loop','title'=>(__('循环播放',JINYU)),'type'=>'switch','sdt'=>true,'showRefId'=>'home_carousel'],
                ['id'=>'home_carousel_delay','title'=>(__('自动播放间隔 (ms)',JINYU)),'type'=>'number','sdt'=>3000,'min'=>0,'desc'=>__('0 为不自动播放',JINYU),'showRefId'=>'home_carousel'],
                ['id'=>'home_carousel_count','title'=>(__('自动轮播数量',JINYU)),'type'=>'number','sdt'=>5,'min'=>1,'max'=>20,'desc'=>__('未手动指定轮播时，自动取最新且含封面的文章数量',JINYU),'showRefId'=>'home_carousel'],
                ['id'=>'home_carousel_effect','title'=>(__('切换效果',JINYU)),'type'=>'select','sdt'=>'','options'=>[
                    ['label'=>__('默认',JINYU),'value'=>''],
                    ['label'=>(__('淡入淡出',JINYU)),'value'=>'fade'],
                    ['label'=>(__('立方体',JINYU)),'value'=>'cube'],
                    ['label'=>(__('快速翻转',JINYU)),'value'=>'flip'],
                    ['label'=>(__('覆盖流',JINYU)),'value'=>'coverflow'],
                    ['label'=>(__('卡片',JINYU)),'value'=>'cards'],
                ],'showRefId'=>'home_carousel'],
                ['id'=>'home_carousel_manual','title'=>(__('手动指定轮播（可选）',JINYU)),'type'=>'textarea','sdt'=>'','desc'=>(__('每行一条，格式：标题|图片URL|链接。留空则自动取最新文章。',JINYU)),'showRefId'=>'home_carousel'],

                /* ─── 首页版块（四宫格 / 两栏）── */
                // 首页主循环（最新文章）始终展示，cms_new_exclude_cats 用于从主循环排除指定分类（在 functions.php pre_get_posts 生效）。
                ['id'=>'cms_new_exclude_cats','title'=>__('最新文章排除分类',JINYU),'type'=>'category-multi','sdt'=>'','desc'=>__('勾选的分类不会出现在首页主循环（最新文章）中',JINYU)],

                ['id'=>'cms_show_four_grid','title'=>(__('显示首页四宫格',JINYU)),'type'=>'switch','sdt'=>false,'desc'=>(__('仅首页第一页显示，位置在幻灯片下方',JINYU))],
                ['id'=>'cms_four_grid_list','title'=>__('首页四宫格列表',JINYU),'type'=>'dynamic-list','sdt'=>[],'draggable'=>true,'max'=>4,'showRefId'=>'cms_show_four_grid','dynamicModel'=>[
                    ['id'=>'title','label'=>__('标题',JINYU),'sdt'=>'','desc'=>__('用于图片替代文本',JINYU)],
                    ['id'=>'img','label'=>__('图片',JINYU),'type'=>'img','sdt'=>'','desc'=>__('建议四张图片尺寸比例一致',JINYU)],
                    ['id'=>'link','label'=>__('指向链接',JINYU),'sdt'=>''],
                    ['id'=>'blank','label'=>__('新标签打开',JINYU),'type'=>'switch','sdt'=>false],
                    ['id'=>'hide','label'=>__('隐藏',JINYU),'type'=>'switch','sdt'=>false,'desc'=>__('隐藏后将不会显示',JINYU)],
                ],'desc'=>__('最多显示前 4 个未隐藏且已设置图片的项目，可拖拽排序',JINYU)],
                ['id'=>'cms_show_2box','title'=>(__('显示分类两栏',JINYU)),'type'=>'switch','sdt'=>true],
                ['id'=>'cms_show_2box_id','title'=>(__('分类两栏·分类',JINYU)),'type'=>'category-multi','sdt'=>'','desc'=>(__('勾选要展示的分类（按分类分两栏）',JINYU))],
                ['id'=>'cms_show_2box_num','title'=>(__('分类两栏·每栏数量',JINYU)),'type'=>'number','sdt'=>6,'min'=>1],
            ],
        ];
    }
}
