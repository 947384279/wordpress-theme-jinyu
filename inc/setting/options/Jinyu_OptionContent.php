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
            'title'  => __('内容增强', 'jinyu'),
            'icon'   => 'fa-solid fa-wand-magic-sparkles',
            'desc'   => __('文章页的阅读增强（目录/面包屑/进度条等）与底部模块开关。', 'jinyu'),
            'fields' => [
                ['type'=>'subhead','title'=>__('阅读增强','jinyu')],
                /* ─── 阅读增强 ─── */
                ['id'=>'toc_enable','title'=>__('文章目录 TOC','jinyu'),'type'=>'switch','sdt'=>true,'desc'=>__('自动提取正文 H2/H3/H4 生成目录并滚动高亮','jinyu')],
                ['id'=>'toc_depth','title'=>__('目录标题层级','jinyu'),'type'=>'select','sdt'=>'3','options'=>[
                    ['label'=>__('仅 H2','jinyu'),'value'=>'2'],
                    ['label'=>__('H2–H3','jinyu'),'value'=>'3'],
                    ['label'=>__('H2–H4','jinyu'),'value'=>'4'],
                ]],
                ['id'=>'breadcrumb_enable','title'=>__('面包屑导航','jinyu'),'type'=>'switch','sdt'=>true],
                ['id'=>'back_top_enable','title'=>__('返回顶部按钮','jinyu'),'type'=>'switch','sdt'=>true],
                ['id'=>'code_highlight_enable','title'=>__('代码高亮','jinyu'),'type'=>'switch','sdt'=>true],
                ['id'=>'read_progress_enable','title'=>__('阅读进度条','jinyu'),'type'=>'switch','sdt'=>true],
                ['id'=>'show_cover','title'=>__('文章封面图（全局）','jinyu'),'type'=>'switch','sdt'=>true,'desc'=>__('关闭后全站不展示特色图；单篇仍可用「隐藏封面」覆盖','jinyu')],
                ['id'=>'default_thumbnail','title'=>__('默认缩略图','jinyu'),'type'=>'upload','sdt'=>'','desc'=>__('无特色图且正文无图片时的兜底缩略图（留空即用主题内置的「七彩云」渐变默认图，此处在需要换图时覆盖）','jinyu')],
                ['id'=>'excerpt_length','title'=>__('摘要字数','jinyu'),'type'=>'number','sdt'=>120,'min'=>20,'max'=>300,'unit'=>'字','desc'=>__('列表/归档页自动摘要长度','jinyu')],

                ['type'=>'subhead','title'=>__('列表卡片信息行','jinyu')],
                /* ─── 列表卡片信息行 ─── */
                ['id'=>'card_series_enable','title'=>__('卡片显示所属系列','jinyu'),'type'=>'switch','sdt'=>true,'desc'=>__('信息行显示「系列名 · 第 N/M 篇」，引导连载阅读；未归入系列的文章不显示','jinyu')],
                ['id'=>'card_updated_enable','title'=>__('卡片显示最近更新','jinyu'),'type'=>'switch','sdt'=>true,'desc'=>__('文章修改晚于发布 3 天以上才提示「更新于 X」，避免小改动造成噪音','jinyu')],

                ['type'=>'subhead','title'=>__('文章底部模块','jinyu')],
                /* ─── 文章底部模块 ─── */
                ['id'=>'related_enable','title'=>__('相关文章','jinyu'),'type'=>'switch','sdt'=>true],
                ['id'=>'related_num','title'=>__('相关文章数量','jinyu'),'type'=>'number','sdt'=>4,'min'=>1,'max'=>12],
                ['id'=>'related_type','title'=>__('相关文章依据','jinyu'),'type'=>'select','sdt'=>'tags','options'=>[
                    ['label'=>__('相同标签','jinyu'),'value'=>'tags'],
                    ['label'=>__('相同分类','jinyu'),'value'=>'cats'],
                    ['label'=>__('随机','jinyu'),'value'=>'random'],
                ]],
                ['id'=>'post_nav_enable','title'=>__('上一篇 / 下一篇','jinyu'),'type'=>'switch','sdt'=>true],

                ['type'=>'subhead','title'=>__('文章操作栏','jinyu')],
                /* ─── 文章操作栏 ─── */
                ['id'=>'like_enable','title'=>__('点赞','jinyu'),'type'=>'switch','sdt'=>false],
                ['id'=>'fav_enable','title'=>__('收藏','jinyu'),'type'=>'switch','sdt'=>true],
                ['id'=>'share_enable','title'=>__('分享','jinyu'),'type'=>'switch','sdt'=>true],
                ['id'=>'share_channels','title'=>__('分享渠道','jinyu'),'type'=>'checkboxes','sdt'=>'weibo,qq,qzone,wechat,link','options'=>[
                    ['label'=>__('微博','jinyu'),'value'=>'weibo'],
                    ['label'=>__('QQ','jinyu'),'value'=>'qq'],
                    ['label'=>__('QQ空间','jinyu'),'value'=>'qzone'],
                    ['label'=>__('微信','jinyu'),'value'=>'wechat'],
                    ['label'=>__('复制链接','jinyu'),'value'=>'link'],
                ],'desc'=>__('多选，留空则全部显示','jinyu')],
                ['id'=>'poster_enable','title'=>__('海报生成','jinyu'),'type'=>'switch','sdt'=>true],
                ['id'=>'qr_enable','title'=>__('二维码','jinyu'),'type'=>'switch','sdt'=>false],
            ],
        ];
    }
}
