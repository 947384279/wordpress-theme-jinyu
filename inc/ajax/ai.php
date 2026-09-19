<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 金玉 AI 对话 - 对接 OpenAI / 国内兼容 API
add_action('wp_ajax_jinyu_ai_chat', 'jinyu_ai_chat');
add_action('wp_ajax_nopriv_jinyu_ai_chat', 'jinyu_ai_chat');
function jinyu_ai_chat()
{
    // 防滥用：校验来源 nonce + 每 IP 速率限制，避免匿名用户刷爆站长付费 API 额度
    check_ajax_referer('jinyu_front', '_ajax_nonce');
    if (!jinyu_rate_limit_check('ai_chat', 12, MINUTE_IN_SECONDS)) {
        wp_send_json_error('请求过于频繁，请稍后再试');
    }

    $api_url   = jinyu_get_option('ai_api_url', 'https://api.deepseek.com/v1/chat/completions');
    $api_key   = jinyu_get_option('ai_api_key', '');
    $model     = jinyu_get_option('ai_model', 'deepseek-chat');
    $max_tokens = (int)jinyu_get_option('ai_max_tokens', 2000);
    if (!$api_key) wp_send_json_error('请先在后台配置 AI API Key');

    $messages = json_decode(wp_unslash($_POST['messages'] ?? ''), true);
    if (!$messages || !is_array($messages)) wp_send_json_error('参数错误');

    // 限制条数与总字符数：messages 由前端透传，不设上限可被塞入超大 payload 烧掉站长付费额度
    if (count($messages) > 20) {
        $messages = array_slice($messages, -20); // 只保留最近的 20 轮
    }
    $clean = [];
    $total = 0;
    foreach ($messages as $m) {
        if (!is_array($m)) continue;
        $role    = in_array($m['role'] ?? '', ['user', 'assistant'], true) ? $m['role'] : 'user';
        $content = isset($m['content']) && is_string($m['content']) ? $m['content'] : '';
        if ($content === '') continue;
        $total += strlen($content);
        if ($total > 8000) break;
        $clean[] = ['role' => $role, 'content' => $content];
    }
    if (!$clean) wp_send_json_error('参数错误');
    $messages = $clean;

    $system_prompt = jinyu_get_option('ai_system_prompt', '你是一个专业、友好的 AI 助手，请用简洁清晰的语言回答用户问题。');
    array_unshift($messages, ['role'=>'system', 'content'=>$system_prompt]);

    $body = [
        'model'       => $model,
        'messages'    => $messages,
        'max_tokens'  => $max_tokens,
        'temperature' => 0.7,
    ];

    $r = wp_remote_post($api_url, [
        'headers' => [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        ],
        'body'    => json_encode($body),
        'timeout' => 60,
    ]);

    if (is_wp_error($r)) wp_send_json_error('网络错误: ' . $r->get_error_message());
    $data = json_decode(wp_remote_retrieve_body($r), true);
    $reply = $data['choices'][0]['message']['content'] ?? '';
    if (!$reply) wp_send_json_error('AI 无回复');

    wp_send_json_success(['content' => $reply]);
}