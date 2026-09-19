<?php

namespace Jinyu\Theme\setting\options;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Jinyu_OptionSales extends Jinyu_BaseOptionItem
{
    public function get_fields(): array
    {
        return [
            'key'    => 'sales',
            'title'  => __('销售与变现', JINYU),
            'icon'   => 'fa-solid fa-bullhorn',
            'desc'   => __('联盟点击追踪、广告位管理、全站转化触点与邮件订阅。所有数据在「变现数据」页面查看。', JINYU),
            'fields' => [
                /* ─── 联盟点击追踪 ─── */
                ['id'=>'track_clicks_enable','title'=>__('联盟点击追踪',JINYU),'type'=>'switch','sdt'=>true,'desc'=>__('记录 /go/ 外链点击的域名、来源文章与来源渠道，用于衡量哪篇文章带来转化。后台「变现数据」页面查看',JINYU)],
                ['id'=>'track_clicks_note','title'=>__('查看数据',JINYU),'type'=>'info','desc'=>__('联盟点击 / 广告点击 / CTA 点击 / 订阅 / 流量来源，统一在后台「金玉主题配置 → 变现数据」查看。',JINYU)],

                /* ─── 广告位管理（从「自定义代码」迁入，独立成一级变现面板） ─── */
                ['id'=>'ad_badge_enable','title'=>__('广告位显示「广告」标识',JINYU),'type'=>'switch','sdt'=>false,'desc'=>__('按《广告法》第十四条，广告应可识别。开启后各广告位左上角显示标识角标（文案见下）',JINYU)],
                ['id'=>'ad_badge_text','title'=>__('标识文案',JINYU),'type'=>'string','sdt'=>__('广告',JINYU),'desc'=>__('留空则用「广告」',JINYU)],
                ['id'=>'ad_global_top','title'=>__('全站顶部广告代码',JINYU),'type'=>'textarea','code'=>true,'sdt'=>'','desc'=>__('插入 wp_body_open 之后',JINYU)],
                ['id'=>'ad_global_bottom','title'=>__('全站底部广告代码',JINYU),'type'=>'textarea','code'=>true,'sdt'=>''],
                ['id'=>'ad_page_inner','title'=>__('文章中部广告',JINYU),'type'=>'textarea','code'=>true,'sdt'=>'','desc'=>__('自动插入到单篇文章正文第一段之后',JINYU)],
                ['id'=>'ad_comment_top','title'=>__('评论区上方广告',JINYU),'type'=>'textarea','code'=>true,'sdt'=>''],

                /* ─── 全站悬浮 CTA 条 ─── */
                ['id'=>'cta_bar_enable','title'=>__('全站悬浮 CTA 条',JINYU),'type'=>'switch','sdt'=>false,'desc'=>__('页面底部常驻引导条，点击跳转指定链接（如加微信 / 领优惠 / 购买）',JINYU)],
                ['id'=>'cta_bar_text','title'=>__('CTA 文案',JINYU),'type'=>'string','sdt'=>__('有问题？加微信领资料',JINYU)],
                ['id'=>'cta_bar_link','title'=>__('CTA 链接',JINYU),'type'=>'text','sdt'=>'','desc'=>__('点击后跳转的 URL',JINYU)],
                ['id'=>'cta_bar_delay','title'=>__('出现延迟(秒)',JINYU),'type'=>'number','sdt'=>3,'min'=>0,'max'=>30,'desc'=>__('进入页面多少秒后滑出，0=立即',JINYU)],

                /* ─── 侧边悬浮客服/微信 ─── */
                ['id'=>'float_contact_enable','title'=>__('侧边悬浮客服/微信',JINYU),'type'=>'switch','sdt'=>false,'desc'=>__('右侧悬浮按钮，点击展开二维码（引流到私域）',JINYU)],
                ['id'=>'float_contact_img','title'=>__('二维码图片',JINYU),'type'=>'upload','sdt'=>'','desc'=>__('微信/客服二维码，留空则不显示该按钮',JINYU)],
                ['id'=>'float_contact_title','title'=>__('按钮标题',JINYU),'type'=>'string','sdt'=>__('联系我们',JINYU)],

                /* ─── 文末统一引导卡 ─── */
                ['id'=>'post_guide_enable','title'=>__('文末统一引导卡',JINYU),'type'=>'switch','sdt'=>false,'desc'=>__('每篇文末展示一张统一引导卡（行动号召）',JINYU)],
                ['id'=>'post_guide_title','title'=>__('引导卡标题',JINYU),'type'=>'string','sdt'=>__('喜欢这篇？',JINYU)],
                ['id'=>'post_guide_text','title'=>__('引导卡文案',JINYU),'type'=>'textarea','sdt'=>__('如果对你有帮助，欢迎加微信深入交流~',JINYU),'rows'=>2],
                ['id'=>'post_guide_btn_text','title'=>__('按钮文案',JINYU),'type'=>'string','sdt'=>__('立即加微信',JINYU)],
                ['id'=>'post_guide_btn_link','title'=>__('按钮链接',JINYU),'type'=>'text','sdt'=>'','desc'=>__('点击按钮跳转的 URL（如微信名片 / 企微 / 商品页）',JINYU)],

                /* ─── 邮件订阅 ─── */
                ['id'=>'subscribe_enable','title'=>__('开启邮件订阅',JINYU),'type'=>'switch','sdt'=>false,'desc'=>__('提供 [subscribe] 短代码与「金玉·邮件订阅」小工具；订阅名单在「变现数据」页面导出 CSV',JINYU)],
                ['id'=>'subscribe_title','title'=>__('订阅框标题',JINYU),'type'=>'string','sdt'=>__('订阅更新',JINYU)],
                ['id'=>'subscribe_desc','title'=>__('订阅框说明',JINYU),'type'=>'textarea','sdt'=>__('留下邮箱，第一时间获取新品与优惠信息',JINYU),'rows'=>2],
                ['id'=>'subscribe_success','title'=>__('订阅成功提示',JINYU),'type'=>'string','sdt'=>__('订阅成功，感谢支持！',JINYU)],
                ['id'=>'subscribe_auto_end','title'=>__('文章末尾自动显示订阅框',JINYU),'type'=>'switch','sdt'=>false,'desc'=>__('开启后无需手动放短代码，单篇文章底部自动追加订阅框',JINYU)],
            ],
        ];
    }
}
