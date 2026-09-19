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
            'title'  => __('用户与登录', JINYU),
            'desc'   => __('评论头像、UA 徽标、登录注册与用户互动。', JINYU),
            'icon'   => 'fa-solid fa-user-shield',
            'fields' => [
                ['id'=>'user_center_enable','title'=>__('启用用户中心',JINYU),'type'=>'switch','sdt'=>true,'desc'=>__('关闭后将不再显示登录/注册入口',JINYU)],
                ['id'=>'user_center_page','title'=>__('用户中心页面',JINYU),'type'=>'select','sdt'=>'','options'=>$this->all_pages(),'desc'=>__('选择一个「用户中心」页面模板，未选则跳转后台个人资料',JINYU)],

                ['id'=>'captcha_policy','title'=>__('验证码策略',JINYU),'type'=>'select','sdt'=>'smart','desc'=>__('统一控制登录 / 注册 / 找回密码的验证码：智能=注册与找回始终要求、登录仅失败 3 次后要求（推荐）；始终=三个场景都始终要求；关闭=全部不验证。',JINYU),'options'=>[
                    ['label'=>__('智能（推荐）',JINYU),'value'=>'smart'],
                    ['label'=>__('始终要求',JINYU),'value'=>'always'],
                    ['label'=>__('关闭',JINYU),'value'=>'off'],
                ]],
                ['id'=>'reg_notify_admin','title'=>__('注册时通知管理员',JINYU),'type'=>'switch','sdt'=>false],
                ['id'=>'login_brute_force','title'=>__('登录防暴破',JINYU),'type'=>'switch','sdt'=>true,'desc'=>__('同一 IP 连续登录失败 5 次锁定 15 分钟，防范暴力破解',JINYU)],

                ['id'=>'comment_show_ua','title'=>__('评论显示 UA',JINYU),'type'=>'switch','sdt'=>true,'desc'=>__('在评论中展示评论者的浏览器与操作系统（基于 User-Agent 解析）',JINYU)],
                ['id'=>'author_box_enable','title'=>__('文末作者信息卡',JINYU),'type'=>'switch','sdt'=>true,'desc'=>__('文章底部展示作者头像、简介与文章/评论数',JINYU)],
                ['id'=>'comment_avatar_src','title'=>__('头像来源',JINYU),'type'=>'select','sdt'=>'gravatar','options'=>[
                    ['label'=>__('Gravatar（默认）',JINYU),'value'=>'gravatar'],
                    ['label'=>__('Cravatar（国内）',JINYU),'value'=>'cravatar'],
                    ['label'=>__('WeAvatar（国内）',JINYU),'value'=>'weavatar'],
                    ['label'=>__('V2EX（国内）',JINYU),'value'=>'v2ex'],
                    ['label'=>__('Loli（国内）',JINYU),'value'=>'loli'],
                    ['label'=>__('七牛云（国内）',JINYU),'value'=>'qiniu'],
                    ['label'=>__('WebP.se（国内）',JINYU),'value'=>'webpse'],
                    ['label'=>__('首字母占位图',JINYU),'value'=>'letter'],
                ],'desc'=>__('国内源（Cravatar / WeAvatar / 七牛云 / WebP.se 等）兼容 Gravatar 协议、国内访问更快，评论与作者头像均生效；letter 模式不依赖任何头像服务器，离线也能显示',JINYU)],

                ['id'=>'comment_pages_enable','title'=>__('评论分页',JINYU),'type'=>'switch','sdt'=>false,'desc'=>__('评论较多时分页展示，避免单页过长',JINYU)],
                ['id'=>'comments_per_page','title'=>__('每页评论数',JINYU),'type'=>'number','sdt'=>10,'min'=>1,'max'=>50],

                ['id'=>'user_can_submit','title'=>__('允许前台投稿',JINYU),'type'=>'switch','sdt'=>false,'desc'=>__('开启后登录用户可在用户中心投稿，文章进入待审核队列',JINYU)],

                ['id'=>'oauth_enable','title'=>__('启用第三方登录',JINYU),'type'=>'switch','sdt'=>false,'desc'=>__('开启后前台登录弹窗显示第三方登录入口；具体平台在下方「第三方登录账号」中添加',JINYU)],
                ['id'=>'oauth_accounts','title'=>__('第三方登录账号',JINYU),'type'=>'dynamic-list','sdt'=>[],'draggable'=>true,'desc'=>__('添加需要开启的第三方登录（QQ / GitHub / Gitee），填 App ID 与密钥后前台自动出现对应入口；可添加多个。密钥留空表示不修改（保存后保持原值）。',JINYU),'dynamicModel'=>[
                    ['id'=>'platform','label'=>__('平台',JINYU),'type'=>'select','sdt'=>'qq','options'=>[
                        ['label'=>__('QQ',JINYU),'value'=>'qq'],
                        ['label'=>__('GitHub',JINYU),'value'=>'github'],
                        ['label'=>__('Gitee',JINYU),'value'=>'gitee'],
                    ]],
                    ['id'=>'client_id','label'=>__('App ID / Client ID',JINYU),'type'=>'text','sdt'=>''],
                    ['id'=>'client_secret','label'=>__('App Key / Client Secret',JINYU),'type'=>'password','sdt'=>'','placeholder'=>__('已设置则留空',JINYU)],
                ]],
            ],
        ];
    }
}
