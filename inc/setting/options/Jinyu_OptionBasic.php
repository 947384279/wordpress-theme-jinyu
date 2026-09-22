<?php

namespace Jinyu\Theme\setting\options;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Jinyu_OptionBasic extends Jinyu_BaseOptionItem
{
    public function get_fields(): array
    {
        return [
            'key'    => 'basic',
            'title'  => __('基础设置', 'jinyu'),
            'desc'   => __('站点信息、Logo、顶部公告、文末版权与文章打赏。', 'jinyu'),
            'icon'   => 'fa-solid fa-gear',
            'fields' => [
                ['id'=>'web_title','title'=>__('站点标题','jinyu'),'type'=>'string','sdt'=>'','desc'=>__('留空则使用 WordPress 站点标题','jinyu')],
                ['id'=>'web_logo','title'=>__('站点 Logo（亮色模式）','jinyu'),'type'=>'upload','sdt'=>'','desc'=>__('建议使用透明背景 PNG；暗色模式会自动切换下方「暗色模式」Logo，未填则复用本图','jinyu')],
                ['id'=>'web_logo_dark','title'=>__('站点 Logo（暗色模式）','jinyu'),'type'=>'upload','sdt'=>'','desc'=>__('留空则暗色模式复用亮色 Logo','jinyu')],
                ['id'=>'top_notice','title'=>__('顶部公告','jinyu'),'type'=>'string','sdt'=>'','desc'=>__('留空则不显示公告条','jinyu')],
                ['id'=>'single_copyright','title'=>__('文末版权声明','jinyu'),'type'=>'textarea','sdt'=>"本文链接：{url}\n转载请注明出处：{title}（作者：{author}）",'desc'=>__('留空则使用默认声明。支持占位符：{url} 文章链接、{title} 标题、{author} 作者、{date} 日期','jinyu')],
                ['id'=>'reward_title','title'=>__('打赏标题','jinyu'),'type'=>'string','sdt'=>__('赞赏作者','jinyu')],
                ['id'=>'reward_text','title'=>__('打赏文案','jinyu'),'type'=>'textarea','sdt'=>__('如果觉得文章对你有帮助，欢迎打赏支持～','jinyu')],
                ['id'=>'reward_wechat','title'=>__('微信收款码','jinyu'),'type'=>'upload','sdt'=>'','desc'=>__('上传微信收款二维码图片；留空则不显示该渠道','jinyu')],
                ['id'=>'reward_alipay','title'=>__('支付宝收款码','jinyu'),'type'=>'upload','sdt'=>'','desc'=>__('上传支付宝收款二维码图片；留空则不显示该渠道','jinyu')],
            ],
        ];
    }
}
