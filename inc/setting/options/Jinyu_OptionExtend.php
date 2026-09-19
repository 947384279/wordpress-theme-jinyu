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
            'title'  => __('高级设置', JINYU),
            'desc'   => __('第三方服务接入、兼容性开关、AI 对话与主题更新等非常规配置。', JINYU),
            'icon'   => 'fa-solid fa-puzzle-piece',
            'fields' => [
                ['id'=>'close_xmlrpc','title'=>__('关闭 XML-RPC',JINYU),'type'=>'switch','sdt'=>true,'desc'=>__('XML-RPC 是常见的暴破与 pingback 攻击面，默认关闭更安全（仅在使用依赖它的第三方应用时才开启）',JINYU)],
                ['id'=>'close_rest_api','title'=>__('关闭 REST API',JINYU),'type'=>'switch','sdt'=>false,'desc'=>__('⚠️ 仅对未登录访客关闭 REST API；已登录用户在后台使用古登堡编辑器不受影响。依赖公开 REST 的第三方应用/接口可能失效，请确认无依赖后再关闭',JINYU)],
                ['id'=>'enable_pjax','title'=>__('启用 PJAX 无刷新导航',JINYU),'type'=>'switch','sdt'=>false,'desc'=>__('站内链接无刷新切换内容（实验性，建议先小流量验证）',JINYU)],
                ['id'=>'go_link_enable','title'=>__('启用短链跳转 go',JINYU),'type'=>'switch','sdt'=>false,'desc'=>__('将正文外链改写为 /go/ 短链统一跳转（便于统计与防泄漏）',JINYU)],
                ['id'=>'anti_spam_enable','title'=>__('启用评论防垃圾',JINYU),'type'=>'switch','sdt'=>true,'desc'=>__('对访客评论做关键词/频率/长度过滤，管理员评论不过滤',JINYU)],
                ['id'=>'anti_spam_words','title'=>__('垃圾评论关键词',JINYU),'type'=>'textarea','sdt'=>__('彩票,色情,赌博,代写,刷量,贷款,发票,办证,加微信,返利,兼职,代运营',JINYU),'desc'=>__('英文逗号分隔，评论内容命中即标记为垃圾',JINYU)],
                ['id'=>'close_comments_old','title'=>__('旧文章自动关评',JINYU),'type'=>'switch','sdt'=>true,'desc'=>__('超过指定天数的文章自动关闭评论',JINYU)],
                ['id'=>'close_comments_days','title'=>__('关评天数阈值',JINYU),'type'=>'number','sdt'=>30,'min'=>1,'unit'=>__('天',JINYU)],
                ['id'=>'comment_smiley','title'=>__('评论表情选择器',JINYU),'type'=>'switch','sdt'=>true,'desc'=>__('评论框提供表情面板，点击插入表情',JINYU)],

                ['id'=>'analytics_code','title'=>__('统计代码',JINYU),'type'=>'textarea','code'=>true,'sdt'=>'','rows'=>5,'desc'=>__('百度统计 / Google Analytics 等的完整代码（含 <script> 标签），将输出到 </head> 前。与「头部注入 JS」不同，此处仅用于统计',JINYU)],

                /* ─── AI 对话 ─── */
                ['id'=>'ai_api_url','title'=>__('API 地址',JINYU),'type'=>'text','sdt'=>'https://api.deepseek.com/v1/chat/completions','desc'=>__('兼容 OpenAI Chat Completions 格式的接口地址',JINYU)],
                ['id'=>'ai_api_key','title'=>__('API Key',JINYU),'type'=>'password','sdt'=>'','desc'=>__('仅存储于数据库，仅管理员可在后台查看',JINYU)],
                ['id'=>'ai_model','title'=>__('模型名称',JINYU),'type'=>'text','sdt'=>'deepseek-chat','desc'=>__('如 deepseek-chat / gpt-4o-mini 等',JINYU)],
                ['id'=>'ai_max_tokens','title'=>__('最大 Token 数',JINYU),'type'=>'number','sdt'=>2000,'min'=>1,'unit'=>'tokens'],
                ['id'=>'ai_system_prompt','title'=>__('系统提示词',JINYU),'type'=>'textarea','sdt'=>'你是一个专业、友好的 AI 助手，请用简洁清晰的语言回答用户问题。'],
            ],
        ];
    }
}
