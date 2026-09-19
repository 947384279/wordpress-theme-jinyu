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
            'title'  => __('页脚设置', JINYU),
            'desc'   => __('页脚栏目、社交链接与版权备案信息。', JINYU),
            'icon'   => 'fa-solid fa-copyright',
            'fields' => [
                ['id'=>'footer_about_title','title'=>__('关于区块标题',JINYU),'type'=>'text','sdt'=>__('关于本站',JINYU)],
                ['id'=>'footer_about','title'=>__('关于本站介绍',JINYU),'type'=>'textarea','sdt'=>__('欢迎访问本站！这里分享 ……（用一两句话介绍你的站点定位、特色与更新方向）',JINYU),'placeholder'=>__('一句话介绍你的站点，留空则显示站点副标题',JINYU),'desc'=>__('留空则使用站点副标题',JINYU)],

                ['id'=>'footer_copyright_title','title'=>__('版权信息标题',JINYU),'type'=>'text','sdt'=>__('站点信息',JINYU)],
                ['id'=>'footer_copyright','title'=>__('版权信息文案',JINYU),'type'=>'textarea','sdt'=>__('© {year} {name} 版权所有 · 保留所有权利',JINYU),'placeholder'=>__('如 © {year} {name} 版权所有，留空使用默认版权',JINYU),'desc'=>__('留空则使用默认版权。支持占位符：{year} 年份、{name} 站点名',JINYU)],

                ['id'=>'footer_social','title'=>__('社交账号',JINYU),'type'=>'textarea','sdt'=>'','desc'=>__('每行一个，格式：平台|链接。例如：GitHub|https://github.com/xxx 。自动识别图标：GitHub、Gitee、微博、微信、QQ、邮箱、Telegram、X、Twitter、知乎、B站、RSS、抖音、豆瓣、今日头条',JINYU)],

                ['id'=>'company_icp','title'=>__('ICP 备案号',JINYU),'type'=>'text','sdt'=>'','placeholder'=>__('如 京ICP备12345678号',JINYU),'desc'=>__('填后展示于页脚',JINYU)],

                ['id'=>'footer_runinfo','title'=>__('页脚显示运行信息',JINYU),'type'=>'switch','sdt'=>0,'desc'=>__('在前台页脚输出一行实时运行信息：查询数 / 内存 / 渲染耗时。数值由 JS 实时拉取，不会被整页缓存冻结；开启后建议在「维护工具」清理一次缓存使其生效',JINYU)],
            ],
        ];
    }
}
