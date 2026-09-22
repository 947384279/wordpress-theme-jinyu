<?php

namespace Jinyu\Theme\setting\options;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Jinyu_OptionComment extends Jinyu_BaseOptionItem
{
    public function get_fields(): array
    {
        return [
            'key'    => 'comment',
            'title'  => __('评论互动', 'jinyu'),
            'desc'   => __('评论开关、分页、旧文关评、表情与防垃圾关键词。', 'jinyu'),
            'icon'   => 'fa-solid fa-comments',
            'fields' => [
                ['id'=>'close_post_comment','title'=>__('关闭全站评论功能','jinyu'),'type'=>'switch','sdt'=>false],
                ['id'=>'comment_pages_enable','title'=>__('评论分页','jinyu'),'type'=>'switch','sdt'=>false,'desc'=>__('评论较多时分页展示，避免单页过长','jinyu')],
                ['id'=>'comments_per_page','title'=>__('每页评论数','jinyu'),'type'=>'number','sdt'=>10,'min'=>1,'max'=>50],
                ['id'=>'close_comments_old','title'=>__('旧文章自动关评','jinyu'),'type'=>'switch','sdt'=>true,'desc'=>__('超过指定天数的文章自动关闭评论','jinyu')],
                ['id'=>'close_comments_days','title'=>__('关评天数阈值','jinyu'),'type'=>'number','sdt'=>30,'min'=>1,'unit'=>__('天','jinyu')],
                ['id'=>'comment_smiley','title'=>__('评论表情选择器','jinyu'),'type'=>'switch','sdt'=>true,'desc'=>__('评论框提供表情面板，点击插入表情','jinyu')],
                ['id'=>'comment_show_ua','title'=>__('评论显示 UA','jinyu'),'type'=>'switch','sdt'=>true,'desc'=>__('在评论中展示评论者的浏览器与操作系统（基于 User-Agent 解析）','jinyu')],
                ['id'=>'anti_spam_words','title'=>__('垃圾评论关键词','jinyu'),'type'=>'textarea','sdt'=> defined('JINYU_DEFAULT_SPAM_WORDS') ? JINYU_DEFAULT_SPAM_WORDS : __('彩票,色情,赌博,代写,刷量,贷款,发票,办证,加微信,返利,兼职,代运营','jinyu'),'desc'=>__('英文逗号分隔，评论内容命中即标记为垃圾。已内置一批常见垃圾词，可在此增删','jinyu')],
            ],
        ];
    }
}
