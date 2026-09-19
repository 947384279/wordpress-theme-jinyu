<?php

namespace Jinyu\Theme\setting\options;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Jinyu_OptionGlobal extends Jinyu_BaseOptionItem
{
    public function get_fields(): array
    {
        return [
            'key'    => 'global',
            'title'  => __('全局设置', JINYU),
            'desc'   => __('维护模式、登录页外观、社交账号与 Cookie 提示等全站行为。', JINYU),
            'icon'   => 'fa-solid fa-layer-group',
            'fields' => [
                ['id'=>'post_style','title'=>__('文章列表风格',JINYU),'type'=>'select','sdt'=>'card','options'=>[
                    ['label'=>__('列表风格',JINYU),'value'=>'list'],
                    ['label'=>(__('卡片风格',JINYU)),'value'=>'card'],
                    ['label'=>(__('大图通栏',JINYU)),'value'=>'big'],
                    ['label'=>(__('杂志混排',JINYU)),'value'=>'cms'],
                ]],
                ['id'=>'blog_show_load_more','title'=>__('博客模式显示加载更多',JINYU),'type'=>'switch','sdt'=>false],

                /* ─── 主题模式 ─── */
                /* ─── 兼容性 / 杂项开关 ─── */
                ['id'=>'use_widgets_block','title'=>__('使用区块小工具',JINYU),'type'=>'switch','sdt'=>false,'desc'=>__('关闭时强制经典小工具（默认），开启后使用 WordPress 区块小工具编辑器',JINYU)],
                ['id'=>'hide_post_views','title'=>__('隐藏文章浏览量',JINYU),'type'=>'switch','sdt'=>false],
                ['id'=>'close_post_comment','title'=>__('关闭全站评论功能',JINYU),'type'=>'switch','sdt'=>false],
                ['id'=>'maintenance_mode','title'=>__('维护模式',JINYU),'type'=>'switch','sdt'=>false,'desc'=>__('开启后非管理员访问站点将看到「网站维护中」页（HTTP 503）',JINYU)],
                ['id'=>'maintenance_text','title'=>__('维护提示文案',JINYU),'type'=>'textarea','sdt'=>__('网站维护中，敬请期待恢复。给您带来的不便，我们深表歉意。',JINYU),'rows'=>3,'desc'=>__('留空使用默认文案',JINYU)],

                /* ─── 页眉社交 / 登录页品牌化 / Cookie 合规 ─── */
                ['id'=>'header_social_enable','title'=>__('页眉显示社交图标',JINYU),'type'=>'switch','sdt'=>false,'desc'=>__('在页眉工具区展示社交账号（格式同页脚：每行 平台|链接）',JINYU)],
                ['id'=>'header_social','title'=>__('页眉社交账号',JINYU),'type'=>'textarea','sdt'=>'','rows'=>3,'desc'=>__('每行一个，格式：平台|链接。留空则复用页脚社交账号',JINYU)],
                ['id'=>'login_logo','title'=>__('登录页 Logo',JINYU),'type'=>'upload','sdt'=>'','desc'=>__('自定义 WP 登录页 Logo 图片',JINYU)],
                ['id'=>'login_bg','title'=>__('登录页背景图',JINYU),'type'=>'upload','sdt'=>'','desc'=>__('自定义 WP 登录页背景图',JINYU)],
                ['id'=>'cookie_consent','title'=>__('Cookie 合规提示条',JINYU),'type'=>'switch','sdt'=>false,'desc'=>__('向访客展示 Cookie/隐私提示，点击「同意」后不再显示（30 天）',JINYU)],
                ['id'=>'cookie_consent_text','title'=>__('提示文案',JINYU),'type'=>'textarea','sdt'=>__('我们使用 Cookie 来提升您的浏览体验。继续浏览即表示您同意我们使用 Cookie。',JINYU),'rows'=>2,'desc'=>__('留空使用默认文案',JINYU)],

                /* ─── 其他布局与杂项 ─── */
                ['id'=>'post_card_cols','title'=>__('文章列表列数',JINYU),'type'=>'select','sdt'=>'2','desc'=>__('作用于全站文章列表网格，有侧边栏时建议 2 列',JINYU),'options'=>[
                    ['label'=>'2 列','value'=>'2'],['label'=>'3 列','value'=>'3'],['label'=>'4 列','value'=>'4'],
                ]],
                ['id'=>'sidebar_pos','title'=>__('侧边栏位置',JINYU),'type'=>'select','sdt'=>'right','options'=>[
                    ['label'=>__('右侧',JINYU),'value'=>'right'],
                    ['label'=>(__('左侧',JINYU)),'value'=>'left'],
                    ['label'=>(__('不显示',JINYU)),'value'=>'none'],
                ]],
                ['id'=>'grey','title'=>__('全站灰度 (哀悼模式)',JINYU),'type'=>'switch','sdt'=>false],
                ['id'=>'load_more_infinite','title'=>__('滚动到底部自动加载',JINYU),'type'=>'switch','sdt'=>false],
                ['id'=>'views_wait_seconds','title'=>__('同一 IP 浏览量冷却秒数',JINYU),'type'=>'number','sdt'=>10,'desc'=>__('防止狂刷',JINYU)],
                ['id'=>'enable_ajax_comment','title'=>(__('评论 Ajax 提交（无刷新）',JINYU)),'type'=>'switch','sdt'=>true],
            ],
        ];
    }
}
