<?php
/**
 * 自定义页面：AI 对话
 *
 * @package         WordPress
 * @subpackage      Jinyu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
Template Name: AI Chat
*/
get_header();
?>
<div class="jinyu-container jinyu-main-wrap">
	<main id="jinyu-content" class="jinyu-content jinyu-ai-page">
		<div class="jinyu-ai-header">
			<div class="jinyu-ai-title">🤖 <?php esc_html_e( 'AI Assistant', 'jinyu' ); ?></div>
			<div class="jinyu-ai-subtitle"><?php esc_html_e( 'Powered by your LLM API · configurable in the admin', 'jinyu' ); ?></div>
		</div>
		<div class="jinyu-ai-messages" id="jyAiMessages">
			<div class="jinyu-ai-msg jinyu-ai-bot">
				<div class="jinyu-ai-avatar">🤖</div>
				<div class="jinyu-ai-bubble"><?php esc_html_e( "Hi! I'm the Jinyu AI assistant. How can I help you?", 'jinyu' ); ?></div>
			</div>
		</div>
		<div class="jinyu-ai-input-area">
			<textarea id="jyAiInput" rows="3" aria-label="<?php esc_attr_e( 'AI chat input', 'jinyu' ); ?>" placeholder="<?php echo esc_attr__( 'Type your question. Enter to send, Shift+Enter for a new line...', 'jinyu' ); ?>"></textarea>
			<button id="jyAiSend" class="jinyu-ai-send"><?php esc_html_e( 'Send', 'jinyu' ); ?></button>
		</div>
	</main>
</div>
<?php
get_footer();
