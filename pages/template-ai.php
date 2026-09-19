<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
Template Name: AI 对话
*/
get_header();
?>
<div class="jinyu-container jinyu-main-wrap">
    <main id="jinyu-content" class="jinyu-content jinyu-ai-page">
        <div class="jinyu-ai-header">
            <div class="jinyu-ai-title">🤖 AI 智能助手</div>
            <div class="jinyu-ai-subtitle">对接大模型 API · 可后台配置</div>
        </div>
        <div class="jinyu-ai-messages" id="jyAiMessages">
            <div class="jinyu-ai-msg jinyu-ai-bot">
                <div class="jinyu-ai-avatar">🤖</div>
                <div class="jinyu-ai-bubble">你好！我是金玉 AI 助手，有什么可以帮你的？</div>
            </div>
        </div>
        <div class="jinyu-ai-input-area">
            <textarea id="jyAiInput" rows="3" placeholder="输入问题，Enter 发送，Shift+Enter 换行..."></textarea>
            <button id="jyAiSend" class="jinyu-ai-send">发送</button>
        </div>
    </main>
</div>
<?php get_footer(); ?>
