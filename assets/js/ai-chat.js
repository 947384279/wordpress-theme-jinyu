/**
 * AI 对话页面交互
 * 依赖：JINYU_CONFIG.ajax_url（由 jinyu-main 注入）
 * 纯原生 JS，不依赖 jQuery，兼容无 regenerator 运行时的构建产物。
 */
(function () {
    if (typeof JINYU_CONFIG === 'undefined' || !JINYU_CONFIG.ajax_url) {
        if (window.console) console.error('[jinyu-ai] JINYU_CONFIG 未就绪');
        return;
    }

    var input = document.getElementById('jyAiInput');
    var send  = document.getElementById('jyAiSend');
    var box   = document.getElementById('jyAiMessages');
    if (!input || !send || !box) return;

    var history = [];

    function addMsg(role, text) {
        var div = document.createElement('div');
        div.className = 'jinyu-ai-msg jinyu-ai-' + role;
        div.innerHTML = role === 'bot'
            ? '<div class="jinyu-ai-avatar">🤖</div><div class="jinyu-ai-bubble"></div>'
            : '<div class="jinyu-ai-avatar"><i class="fa-regular fa-user"></i></div><div class="jinyu-ai-bubble"></div>';
        div.querySelector('.jinyu-ai-bubble').textContent = text;
        box.appendChild(div);
        box.scrollTop = box.scrollHeight;
    }

    function chat() {
        var text = input.value.trim();
        if (!text) return;
        addMsg('user', text);
        input.value = '';
        send.disabled = true;
        send.textContent = '思考中...';

        fetch(JINYU_CONFIG.ajax_url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=jinyu_ai_chat&_ajax_nonce=' + encodeURIComponent(JINYU_CONFIG.nonce || '') + '&messages=' + encodeURIComponent(
                JSON.stringify(history.concat([{ role: 'user', content: text }]))
            ),
            credentials: 'same-origin'
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data && data.success) {
                    addMsg('bot', data.data);
                    history.push({ role: 'user', content: text });
                    history.push({ role: 'assistant', content: data.data });
                    if (history.length > 20) history.splice(0, history.length - 20);
                } else {
                    addMsg('bot', '❌ ' + (data && data.data ? data.data : '请求失败'));
                }
            })
            .catch(function () { addMsg('bot', '❌ 网络错误'); })
            .then(function () {
                send.disabled = false;
                send.textContent = '发送';
            });
    }

    send.addEventListener('click', chat);
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); chat(); }
    });
})();
