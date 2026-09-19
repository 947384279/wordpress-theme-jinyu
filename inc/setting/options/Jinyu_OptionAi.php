<?php

namespace Jinyu\Theme\setting\options;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Jinyu_OptionAi extends Jinyu_BaseOptionItem
{
    public function get_fields(): array
    {
        return [
            'key'    => 'ai',
            'title'  => __('AI 对话', JINYU),
            'desc'   => __('接入大模型 API（DeepSeek 等），配置接口地址、密钥与模型。', JINYU),
            'icon'   => 'fa-solid fa-robot',
            'fields' => [
                ['id'=>'ai_api_url','title'=>__('API 地址',JINYU),'type'=>'text','sdt'=>'https://api.deepseek.com/v1/chat/completions','desc'=>__('兼容 OpenAI Chat Completions 格式的接口地址',JINYU)],
                ['id'=>'ai_api_key','title'=>__('API Key',JINYU),'type'=>'password','sdt'=>'','desc'=>__('仅存储于数据库，仅管理员可在后台查看',JINYU)],
                ['id'=>'ai_model','title'=>__('模型名称',JINYU),'type'=>'text','sdt'=>'deepseek-chat','desc'=>__('如 deepseek-chat / gpt-4o-mini 等',JINYU)],
                ['id'=>'ai_max_tokens','title'=>__('最大 Token 数',JINYU),'type'=>'number','sdt'=>2000,'min'=>1,'unit'=>'tokens'],
                ['id'=>'ai_system_prompt','title'=>__('系统提示词',JINYU),'type'=>'textarea','sdt'=>'你是一个专业、友好的 AI 助手，请用简洁清晰的语言回答用户问题。'],
            ],
        ];
    }
}
