document.addEventListener('DOMContentLoaded', () => {
    const app = document.getElementById('app');
    if (!app) return;

    const DOMAIN = 'damoony3.ru';
    const ALIAS = 'www';
    const PAGE = '__root__';

    // tab_id per-tab, survives F5
    let tabId = sessionStorage.getItem('aiui_tab');
    if (!tabId) {
        tabId = (crypto.randomUUID ? crypto.randomUUID() : String(Date.now())).replace(/-/g, '').slice(0, 16);
        sessionStorage.setItem('aiui_tab', tabId);
    }

    const previewBase = '/admin/preview/' + tabId + '/' + ALIAS + '/';
    let sessionStarted = false;

    app.innerHTML = `
        <div class="aiui-container">
            <div class="preview-area">
                <div class="preview-header">
                    <span style="margin-right: 10px;">🔴 🟡 🟢</span>
                    <span id="address">http://${DOMAIN}/</span>
                    <button id="btn-apply" style="display:none; margin-left:20px; background:#238636; color:#fff; border:none; padding:4px 10px; border-radius:4px; cursor:pointer;">Применить</button>
                    <span id="tab-badge" style="margin-left:auto; font-size:11px; color:#8b949e;">tab: ${tabId}</span>
                </div>
                <div class="preview-content">
                    <iframe id="site-frame" src="//${DOMAIN}/"></iframe>
                </div>
            </div>
            <div class="chat-area">
                <div style="padding: 15px; border-bottom: 1px solid #30363d; font-weight: bold;">AIUI Editor</div>
                <div class="chat-messages" id="messages">
                    <div class="msg ai">Привет! Напиши, что изменить на странице.</div>
                </div>
                <div class="chat-input-area">
                    <div style="display:flex; gap:10px; margin-bottom:10px; align-items:center;">
                        <select id="target-block" style="padding:6px; background:#0d1117; color:#c9d1d9; border:1px solid #30363d; border-radius:4px; outline:none; flex:1;">
                            <option value="all">Вся страница</option>
                            <option value="center">Только CENTER (Контент)</option>
                            <option value="top">Только TOP (Шапка)</option>
                            <option value="bottom">Только BOTTOM (Футер)</option>
                        </select>
                        <label style="color:#c9d1d9; font-size:12px; display:flex; align-items:center; gap:5px; cursor:pointer;">
                            <input type="checkbox" id="wireframe-toggle"> Структура (Wireframe)
                        </label>
                    </div>
                    <div style="display:flex; gap:10px;">
                        <textarea id="prompt" rows="2" placeholder="Например: Добавь H1 'Привет'..." style="flex:1;"></textarea>
                        <button id="send">➤</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    const input = document.getElementById('prompt');
    const btnSend = document.getElementById('send');
    const btnApply = document.getElementById('btn-apply');
    const messages = document.getElementById('messages');
    const iframe = document.getElementById('site-frame');
    const address = document.getElementById('address');

    async function api(payload) {
        const res = await fetch('/admin/ai_edit.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload),
        });
        return res.json();
    }

    async function ensureSession() {
        if (sessionStarted) return true;
        const data = await api({action: 'start', domain: DOMAIN, alias: ALIAS, page: PAGE, tab: tabId});
        if (data.ok) {
            sessionStarted = true;
            return true;
        }
        addMsg('Ошибка старта сессии: ' + data.error, 'error');
        return false;
    }

    async function send() {
        const text = input.value.trim();
        if (!text) return;
        addMsg(text, 'user');
        input.value = '';
        btnSend.disabled = true;
        btnApply.style.display = 'none';

        try {
            if (!(await ensureSession())) return;
            const targetBlock = document.getElementById('target-block').value;
            const wireframe = document.getElementById('wireframe-toggle').checked;
            
            const data = await api({action: 'preview', domain: DOMAIN, alias: ALIAS, page: PAGE, tab: tabId, prompt: text, target_block: targetBlock});
            if (data.ok) {
                addMsg('Черновик готов! Смотри превью. Если всё нравится — "Применить".', 'ai');
                let previewUrl = previewBase + '?t=' + Date.now();
                if (wireframe) previewUrl += '&mode=wireframe';
                iframe.src = previewUrl;
                address.innerText = 'PREVIEW · ' + text.slice(0, 40);
                btnApply.style.display = 'inline-block';
            } else {
                addMsg('Ошибка: ' + data.error, 'error');
            }
        } catch (e) {
            addMsg('Сетевая ошибка: ' + e.message, 'error');
        } finally {
            btnSend.disabled = false;
        }
    }

    async function apply() {
        btnApply.disabled = true;
        try {
            const data = await api({action: 'apply', domain: DOMAIN, alias: ALIAS, page: PAGE, tab: tabId});
            if (data.ok) {
                addMsg('Изменения применены к production!', 'ai');
                sessionStarted = false;
                iframe.src = '//' + DOMAIN + '/?t=' + Date.now();
                address.innerText = 'http://' + DOMAIN + '/';
                btnApply.style.display = 'none';
            } else {
                addMsg('Ошибка apply: ' + data.error, 'error');
            }
        } catch (e) {
            addMsg('Сетевая ошибка: ' + e.message, 'error');
        } finally {
            btnApply.disabled = false;
        }
    }

    function addMsg(text, type) {
        const div = document.createElement('div');
        div.className = 'msg ' + type;
        div.innerText = text;
        messages.appendChild(div);
        messages.scrollTop = messages.scrollHeight;
    }

    btnSend.onclick = send;
    btnApply.onclick = apply;
    input.onkeydown = (e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); } };
});
