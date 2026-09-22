<?php

namespace Jinyu\Theme\setting\options;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Jinyu_OptionFooter extends Jinyu_BaseOptionItem
{
    public function get_fields(): array
    {
        return [
            'key'    => 'footer',
            'title'  => __('页脚设置', 'jinyu'),
            'desc'   => __('页脚栏目、社交链接与版权备案信息。', 'jinyu'),
            'icon'   => 'fa-solid fa-copyright',
            'fields' => [
                ['id'=>'footer_about_title','title'=>__('关于区块标题','jinyu'),'type'=>'text','sdt'=>__('关于本站','jinyu')],
                ['id'=>'footer_about','title'=>__('关于本站介绍','jinyu'),'type'=>'textarea','sdt'=>__('欢迎访问本站！这里分享 ……（用一两句话介绍你的站点定位、特色与更新方向）','jinyu'),'placeholder'=>__('一句话介绍你的站点，留空则显示站点副标题','jinyu'),'desc'=>__('留空则使用站点副标题','jinyu')],

                ['id'=>'flink_apply_url','title'=>__('友链申请链接','jinyu'),'type'=>'text','sdt'=>'','placeholder'=>__('如 /friend-link 或 https://xxx.com/apply','jinyu'),'desc'=>__('留空则自动指向使用「申请友链」模板的页面（新建页面 → 右侧模板选「申请友链」即可）；都没有则不显示入口。手填可覆盖自动识别','jinyu')],
                ['id'=>'flink_apply_form','title'=>__('开放前台申请表单','jinyu'),'type'=>'switch','sdt'=>1,'desc'=>__('开启后申请页显示在线表单，提交内容进入「友情链接」待审核队列，发布即上线；关闭则只展示申请要求与站长邮箱','jinyu')],
                ['id'=>'flink_apply_notify','title'=>__('新申请邮件通知','jinyu'),'type'=>'switch','sdt'=>1,'desc'=>__('有人提交友链申请时发一封邮件到站长邮箱（依赖站点发信配置）','jinyu')],
                ['id'=>'flink_apply_rules','title'=>__('友链申请要求','jinyu'),'type'=>'textarea','sdt'=>__("内容健康、原创为主，无违法违规与灰色内容\n网站能正常访问，非采集站、非纯导航站\n已添加本站友链，且友链位置在首页可见\n站点有一定内容积累，不做空壳站",'jinyu'),'placeholder'=>__('每行一条要求','jinyu'),'desc'=>__('每行一条，显示在申请页「申请要求」卡片；留空则隐藏该卡片','jinyu')],

                ['id'=>'footer_copyright','title'=>__('版权信息文案','jinyu'),'type'=>'textarea','sdt'=>__('© {year} {name} 版权所有 · 保留所有权利','jinyu'),'placeholder'=>__('如 © {year} {name} 版权所有，留空使用默认版权','jinyu'),'desc'=>__('留空则使用默认版权。支持占位符：{year} 年份、{name} 站点名','jinyu')],

                ['id'=>'footer_social','title'=>__('社交账号','jinyu'),'type'=>'textarea','sdt'=>'','desc'=>__('每行一个，格式：平台|链接。例如：GitHub|https://github.com/xxx 。自动识别图标：GitHub、Gitee、微博、微信、QQ、邮箱、Telegram、X、Twitter、知乎、B站、RSS、抖音、豆瓣、今日头条','jinyu')],

                ['id'=>'header_social_enable','title'=>__('页眉也显示社交图标','jinyu'),'type'=>'switch','sdt'=>false,'desc'=>__('在页眉工具区展示上方社交账号（页眉与页脚共用同一份社交列表）','jinyu')],

                ['id'=>'company_icp','title'=>__('ICP 备案号','jinyu'),'type'=>'text','sdt'=>'','placeholder'=>__('如 京ICP备12345678号','jinyu'),'desc'=>__('填后展示于页脚','jinyu')],
            ],
        ];
    }
}
