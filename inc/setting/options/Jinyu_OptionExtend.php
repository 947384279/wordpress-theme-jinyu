<?php

namespace Jinyu\Theme\setting\options;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Jinyu_OptionExtend extends Jinyu_BaseOptionItem
{
    public function get_fields(): array
    {
        return [
            'key'    => 'extend',
            'title'  => __('高级设置', 'jinyu'),
            'desc'   => __('第三方服务接入、兼容性开关、AI 对话与主题更新等非常规配置。', 'jinyu'),
            'icon'   => 'fa-solid fa-puzzle-piece',
            'fields' => [
                ['id'=>'close_rest_api','title'=>__('关闭 REST API','jinyu'),'type'=>'switch','sdt'=>false,'desc'=>__('⚠️ 仅对未登录访客关闭 REST API；已登录用户在后台使用古登堡编辑器不受影响。依赖公开 REST 的第三方应用/接口可能失效，请确认无依赖后再关闭','jinyu')],
                ['id'=>'enable_pjax','title'=>__('启用 PJAX 无刷新导航','jinyu'),'type'=>'switch','sdt'=>false,'desc'=>__('站内链接无刷新切换内容（实验性，建议先小流量验证）','jinyu')],
                ['id'=>'live_ip_location','title'=>__('侧栏访客归属地查询','jinyu'),'type'=>'switch','sdt'=>false,'desc'=>__('⚠️ 隐私提示：开启后侧栏实时数据会把访客 IP 发往第三方服务（whois.pconline.com.cn）查询归属地。默认关闭，仅显示访客本机可得的 IP/系统/浏览器信息。结果缓存 12 小时，关闭后立即停止外发','jinyu')],
            ],
        ];
    }
}
