document.addEventListener('DOMContentLoaded', () => {
    const app = document.getElementById('app');
    if (!app) return;

    app.innerHTML = `
        <div class="aiui-container">
            <div class="preview-area">
                <div class="preview-header">
                    <span style="margin-right: 10px;">🔴 🟡 🟢</span> <span id="address">http://damoony3.ru/</span>
                </div>
                <div class="preview-content">
                    <iframe id="site-frame" src="http://damoony3.ru/"></iframe>
                </div>
            </div>
            <div class="chat-area">
                <div style="padding: 15px; border-bottom: 1px solid #30363d; font-weight: bold;">AIUI Editor</div>
                <div class="chat-messages" id="messages">
                    <div class="msg ai">Привет! Напиши, что изменить на странице.</div>
                </div>
                <div class="chat-input-area">
                    <textarea id="prompt" rows="2" placeholder="Например: Добавь H1..."></textarea>
                    <button id="send">➤</button>
                </div>
            </div>
        </div>
    `;

    const input = document.getElementById('prompt');
    const btn = document.getElementById('send');
    const messages = document.getElementById('messages');
    const iframe = document.getElementById('site-frame');

    async function send() {
        const text = input.value.trim();
        if (!text) return;
        
        addMsg(text, 'user');
        input.value = '';
        btn.disabled = true;

        try {
            const res = await fetch('/admin/ai_edit.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ domain: 'damoony3.ru', alias: 'www', page: '__root__', prompt: text, apply: true })
            });
            const data = await res.json();
            
            if (data.ok) {
                addMsg('Готово! Обновляю превью.', 'ai');
                iframe.src = iframe.src.split('?')[0] + '?t=' + Date.now();
            } else {
                addMsg('Ошибка: ' + data.error, 'error');
            }
        } catch (e) {
            addMsg('Сетевая ошибка: ' + e.message, 'error');
        } finally {
            btn.disabled = false;
        }
    }

    function addMsg(text, type) {
        const div = document.createElement('div');
        div.className = 'msg ' + type;
        div.innerText = text;
        messages.appendChild(div);
        messages.scrollTop = messages.scrollHeight;
    }

    btn.onclick = send;
    input.onkeydown = (e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); } };
});
