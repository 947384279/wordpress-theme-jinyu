<?php

namespace Jinyu\Theme\setting\options;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Jinyu_OptionContent extends Jinyu_BaseOptionItem
{
    public function get_fields(): array
    {
        return [
            'key'    => 'content',
            'title'  => __('内容增强', JINYU),
            'icon'   => 'fa-solid fa-wand-magic-sparkles',
            'desc'   => __('文章页的阅读增强（目录/面包屑/进度条等）与底部模块开关。', JINYU),
            'fields' => [
                /* ─── 阅读增强 ─── */
                ['id'=>'toc_enable','title'=>__('文章目录 TOC',JINYU),'type'=>'switch','sdt'=>true,'desc'=>__('自动提取正文 H2/H3/H4 生成目录并滚动高亮',JINYU)],
                ['id'=>'toc_depth','title'=>__('目录标题层级',JINYU),'type'=>'select','sdt'=>'3','options'=>[
                    ['label'=>__('仅 H2',JINYU),'value'=>'2'],
                    ['label'=>__('H2–H3',JINYU),'value'=>'3'],
                    ['label'=>__('H2–H4',JINYU),'value'=>'4'],
                ]],
                ['id'=>'breadcrumb_enable','title'=>__('面包屑导航',JINYU),'type'=>'switch','sdt'=>true],
                ['id'=>'back_top_enable','title'=>__('返回顶部按钮',JINYU),'type'=>'switch','sdt'=>true],
                ['id'=>'code_highlight_enable','title'=>__('代码高亮',JINYU),'type'=>'switch','sdt'=>true],
                ['id'=>'read_progress_enable','title'=>__('阅读进度条',JINYU),'type'=>'switch','sdt'=>true],
                ['id'=>'show_cover','title'=>__('文章封面图（全局）',JINYU),'type'=>'switch','sdt'=>true,'desc'=>__('关闭后全站不展示特色图；单篇仍可用「隐藏封面」覆盖',JINYU)],
                ['id'=>'default_thumbnail','title'=>__('默认缩略图',JINYU),'type'=>'upload','sdt'=>'','desc'=>__('无特色图且正文无图片时的兜底缩略图（留空即用主题内置的「七彩云」渐变默认图，此处在需要换图时覆盖）',JINYU)],
                ['id'=>'excerpt_length','title'=>__('摘要字数',JINYU),'type'=>'number','sdt'=>120,'min'=>20,'max'=>300,'unit'=>'字','desc'=>__('列表/归档页自动摘要长度',JINYU)],

                /* ─── 文章底部模块 ─── */
                ['id'=>'related_enable','title'=>__('相关文章',JINYU),'type'=>'switch','sdt'=>true],
                ['id'=>'related_num','title'=>__('相关文章数量',JINYU),'type'=>'number','sdt'=>4,'min'=>1,'max'=>12],
                ['id'=>'related_type','title'=>__('相关文章依据',JINYU),'type'=>'select','sdt'=>'tags','options'=>[
                    ['label'=>__('相同标签',JINYU),'value'=>'tags'],
                    ['label'=>__('相同分类',JINYU),'value'=>'cats'],
                    ['label'=>__('随机',JINYU),'value'=>'random'],
                ]],
                ['id'=>'post_nav_enable','title'=>__('上一篇 / 下一篇',JINYU),'type'=>'switch','sdt'=>true],

                /* ─── 文章操作栏 ─── */
                ['id'=>'like_enable','title'=>__('点赞',JINYU),'type'=>'switch','sdt'=>false],
                ['id'=>'fav_enable','title'=>__('收藏',JINYU),'type'=>'switch','sdt'=>true],
                ['id'=>'share_enable','title'=>__('分享',JINYU),'type'=>'switch','sdt'=>true],
                ['id'=>'share_channels','title'=>__('分享渠道',JINYU),'type'=>'checkboxes','sdt'=>'weibo,qq,qzone,link','options'=>[
                    ['label'=>__('微博',JINYU),'value'=>'weibo'],
                    ['label'=>__('QQ',JINYU),'value'=>'qq'],
                    ['label'=>__('QQ空间',JINYU),'value'=>'qzone'],
                    ['label'=>__('复制链接',JINYU),'value'=>'link'],
                ],'desc'=>__('多选，留空则全部显示',JINYU)],
                ['id'=>'poster_enable','title'=>__('海报生成',JINYU),'type'=>'switch','sdt'=>true],
                ['id'=>'qr_enable','title'=>__('二维码',JINYU),'type'=>'switch','sdt'=>false],
            ],
        ];
    }
}
