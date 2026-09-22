<?php

namespace Jinyu\Theme\setting\options;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Jinyu_OptionUser extends Jinyu_BaseOptionItem
{
    public function get_fields(): array
    {
        return [
            'key'    => 'user',
            'title'  => __('用户与登录', 'jinyu'),
            'desc'   => __('评论头像、UA 徽标、登录注册与用户互动。', 'jinyu'),
            'icon'   => 'fa-solid fa-user-shield',
            'fields' => [
                ['id'=>'user_center_enable','title'=>__('启用用户中心','jinyu'),'type'=>'switch','sdt'=>true,'desc'=>__('关闭后将不再显示登录/注册入口','jinyu')],
                ['id'=>'user_center_page','title'=>__('用户中心页面','jinyu'),'type'=>'select','sdt'=>'','options'=>$this->all_pages(),'desc'=>__('选择一个「用户中心」页面模板，未选则跳转后台个人资料','jinyu')],

                ['id'=>'login_logo','title'=>__('登录页 Logo','jinyu'),'type'=>'upload','sdt'=>'','desc'=>__('自定义 WP 登录页 Logo 图片','jinyu')],
                ['id'=>'login_bg','title'=>__('登录页背景图','jinyu'),'type'=>'upload','sdt'=>'','desc'=>__('自定义 WP 登录页背景图','jinyu')],

                ['id'=>'captcha_policy','title'=>__('验证码策略','jinyu'),'type'=>'select','sdt'=>'smart','desc'=>__('统一控制登录 / 注册 / 找回密码 / 友链申请的验证码：智能=除登录外（注册、找回、友链申请）始终要求、登录仅失败 3 次后要求（推荐）；始终=全部场景都始终要求；关闭=全部不验证。','jinyu'),'options'=>[
                    ['label'=>__('智能（推荐）','jinyu'),'value'=>'smart'],
                    ['label'=>__('始终要求','jinyu'),'value'=>'always'],
                    ['label'=>__('关闭','jinyu'),'value'=>'off'],
                ]],
                ['id'=>'reg_notify_admin','title'=>__('注册时通知管理员','jinyu'),'type'=>'switch','sdt'=>false],

                ['id'=>'author_box_enable','title'=>__('文末作者信息卡','jinyu'),'type'=>'switch','sdt'=>true,'desc'=>__('文章底部展示作者头像、简介与文章/评论数','jinyu')],
                ['id'=>'comment_avatar_src','title'=>__('头像来源','jinyu'),'type'=>'select','sdt'=>'gravatar','options'=>[
                    ['label'=>__('Gravatar（默认）','jinyu'),'value'=>'gravatar'],
                    ['label'=>__('Cravatar（国内）','jinyu'),'value'=>'cravatar'],
                    ['label'=>__('WeAvatar（国内）','jinyu'),'value'=>'weavatar'],
                    ['label'=>__('V2EX（国内）','jinyu'),'value'=>'v2ex'],
                    ['label'=>__('Loli（国内）','jinyu'),'value'=>'loli'],
                    ['label'=>__('七牛云（国内）','jinyu'),'value'=>'qiniu'],
                    ['label'=>__('WebP.se（国内）','jinyu'),'value'=>'webpse'],
                    ['label'=>__('首字母占位图','jinyu'),'value'=>'letter'],
                ],'desc'=>__('国内源（Cravatar / WeAvatar / 七牛云 / WebP.se 等）兼容 Gravatar 协议、国内访问更快，评论与作者头像均生效；letter 模式不依赖任何头像服务器，离线也能显示','jinyu')],

                ['id'=>'user_can_submit','title'=>__('允许前台投稿','jinyu'),'type'=>'switch','sdt'=>false,'desc'=>__('开启后登录用户可在用户中心投稿，文章进入待审核队列','jinyu')],

                // 第三方登录（QQ / GitHub / Gitee / Apple）逻辑已迁至「金玉增强插件」，
                // 配置入口在 WP 后台「设置 → 金玉社交登录」。此处不再保留主题侧开关。
            ],
        ];
    }
}
