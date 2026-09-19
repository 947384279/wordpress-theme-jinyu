<?php

namespace Jinyu\Theme\setting\options;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Jinyu_OptionEmail extends Jinyu_BaseOptionItem
{
    public function get_fields(): array
    {
        return [
            'key'    => 'email',
            'title'  => __('邮件 SMTP', JINYU),
            'icon'   => 'fa-solid fa-envelope',
            'desc'   => __('配置站点发信（评论通知、测试邮件等）。常见服务商：QQ 邮箱 smtp.qq.com / 端口 465 / SSL；163 邮箱 smtp.163.com / 端口 465 / SSL；Gmail smtp.gmail.com / 端口 465 / SSL。注意：QQ、163 等国内邮箱的「密码」需填邮箱网页端设置里开启 SMTP 后生成的「授权码」，不是邮箱登录密码。填好后点右侧「发送测试邮件」即可验证，会直接读取本表单当前值，无需先保存。', JINYU),
            'fields' => [
                ['id'=>'smtp_host','title'=>__('SMTP 主机',JINYU),'type'=>'string','sdt'=>'',
                    'desc'=>__('邮箱服务商的 SMTP 服务器地址，如 QQ 邮箱为 smtp.qq.com，163 邮箱为 smtp.163.com，Gmail 为 smtp.gmail.com。',JINYU)],
                ['id'=>'smtp_port','title'=>__('端口',JINYU),'type'=>'number','sdt'=>465,
                    'desc'=>__('与下方「加密方式」配对：465 对应 SSL，587 对应 TLS（STARTTLS）。配错会连接失败或报 530/535。',JINYU)],
                ['id'=>'smtp_user','title'=>__('用户名',JINYU),'type'=>'string','sdt'=>'',
                    'desc'=>__('完整邮箱地址，如 123456@qq.com。',JINYU)],
                ['id'=>'smtp_pwd','title'=>__('密码',JINYU),'type'=>'password','sdt'=>'',
                    'desc'=>__('多数邮箱（QQ、163 等）此处填「授权码」而非登录密码：需先在网页邮箱「设置 → 账户」中开启 SMTP 服务并获取授权码。Gmail 同理使用应用专用密码。',JINYU)],
                ['id'=>'smtp_secure','title'=>__('加密方式',JINYU),'type'=>'select','sdt'=>'ssl','options'=>[
                    ['label'=>'SSL','value'=>'ssl'],
                    ['label'=>'TLS','value'=>'tls'],
                    ['label'=>__('无',JINYU),'value'=>'none'],
                ],
                    'desc'=>__('与端口配对：选 SSL 时端口用 465，选 TLS 时端口用 587；选「无」可能被服务商拒绝发信。',JINYU)],
                ['id'=>'smtp_from','title'=>__('发件人地址',JINYU),'type'=>'string','sdt'=>'',
                    'desc'=>__('可选。留空则默认用「用户名」作为发件地址；部分服务商要求发件人与登录账号一致，否则发信失败。',JINYU)],
            ],
        ];
    }
}
