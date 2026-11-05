document.addEventListener('DOMContentLoaded', () => {
    const domain = "damoony3.ru";
    const alias = "www";
    const page = "__root__";
    
    let tabId = sessionStorage.getItem('aiui_tab');
    if (!tabId) {
        tabId = (crypto.randomUUID ? crypto.randomUUID() : String(Date.now())).replace(/-/g, '').slice(0, 16);
        sessionStorage.setItem('aiui_tab', tabId);
    }
    const tabBadge = document.getElementById('tab-badge');
    if (tabBadge) tabBadge.innerText = 'tab: ' + tabId;
    
    const previewBase = '/admin/preview/' + tabId + '/' + alias + '/';
    let sessionStarted = false;

    const promptInput = document.getElementById('prompt');
    const sendBtn = document.getElementById('send');
    const btnCreate = document.getElementById('btn-create');
    const createRow = btnCreate ? btnCreate.closest('.create-row') : null;
    const applyBtn = document.getElementById('btn-apply');
    const frame = document.getElementById('site-frame');
    const address = document.getElementById('address');
    const messages = document.getElementById('messages');
    const briefPanel = document.getElementById('brief-panel');
    const briefText = document.getElementById('brief-text');

    async function api(payload) {
        const res = await fetch('/admin/ai_edit.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        return res.json();
    }

    async function ensureSession() {
        if (sessionStarted) return true;
        const data = await api({action: 'start', domain, alias, page, tab: tabId});
        if (data.ok) {
            sessionStarted = true;
            return true;
        }
        addMessage('Ошибка старта сессии: ' + data.error, 'error');
        return false;
    }

    async function sendPrompt() {
        const prompt = promptInput.value.trim();
        if (!prompt) return;

        addMessage(prompt, 'user');
        promptInput.value = '';
        sendBtn.disabled = true;

        try {
            if (!(await ensureSession())) return;
            
            const data = await api({
                action: 'preview', 
                domain, alias, page, tab: tabId, 
                prompt: prompt
            });

            if (data.ok) {
                // Показываем бриф, если он пришел
                if (data.brief && briefPanel && briefText) {
                    briefPanel.style.display = 'block';
                    briefText.innerText = data.brief;
                    // Показываем кнопку "Создать сайт", если бриф не пустой
                    if (createRow && data.brief.trim() !== '' && data.brief !== 'Пока нет.') {
                        createRow.classList.add('visible');
                    }
                }

                if (data.chat_only) {
                    addMessage(data.reply, 'ai');
                } else {
                    addMessage('Черновик готов! Смотри превью. Если всё нравится — "Применить".', 'ai');
                    frame.src = previewBase + '?t=' + Date.now();
                    address.innerText = 'PREVIEW · ' + prompt.slice(0, 40);
                    if (applyBtn) applyBtn.style.display = 'inline-block';
                }
            } else {
                addMessage('Ошибка: ' + data.error, 'error');
            }
        } catch (e) {
            addMessage('Сетевая ошибка: ' + e.message, 'error');
        } finally {
            sendBtn.disabled = false;
        }
    }

    async function applyChanges() {
        if (applyBtn) applyBtn.disabled = true;
        try {
            const data = await api({action: 'apply', domain, alias, page, tab: tabId});
            if (data.ok) {
                addMessage('Изменения применены к production!', 'ai');
                sessionStarted = false;
                if (frame) frame.src = '//' + domain + '/?t=' + Date.now();
                if (address) address.innerText = '//' + domain + '/';
                if (applyBtn) applyBtn.style.display = 'none';
            } else {
                addMessage('Ошибка apply: ' + data.error, 'error');
            }
        } catch (e) {
            addMessage('Сетевая ошибка: ' + e.message, 'error');
        } finally {
            if (applyBtn) applyBtn.disabled = false;
        }
    }

    function addMessage(text, type) {
        const div = document.createElement('div');
        div.className = 'msg ' + type;
        div.innerText = text;
        if (messages) {
            messages.appendChild(div);
            messages.scrollTop = messages.scrollHeight;
        }
    }

    async function buildSite() {
        if (!(await ensureSession())) return;
        if (btnCreate) btnCreate.disabled = true;
        if (sendBtn) sendBtn.disabled = true;
        addMessage('🚀 Генерирую сайт по брифу... (может занять 1–3 мин)', 'ai');
        try {
            const data = await api({action: 'build', domain, alias, page, tab: tabId});
            if (data.ok) {
                addMessage('✅ Сайт сгенерирован! Смотри превью.', 'ai');
                frame.src = previewBase + '?t=' + Date.now();
                address.innerText = 'PREVIEW · ' + (data.pages_total ? data.pages_ok + '/' + data.pages_total + ' страниц' : 'готово');
                if (applyBtn) applyBtn.style.display = 'inline-block';
            } else {
                addMessage('❌ Ошибка: ' + data.error, 'error');
            }
        } catch (e) {
            addMessage('Сетевая ошибка: ' + e.message, 'error');
        } finally {
            if (btnCreate) btnCreate.disabled = false;
            if (sendBtn) sendBtn.disabled = false;
        }
    }

    if (btnCreate) btnCreate.onclick = buildSite;
    if (sendBtn) sendBtn.onclick = sendPrompt;
    if (applyBtn) applyBtn.onclick = applyChanges;
    if (promptInput) promptInput.onkeydown = (e) => { 
        if (e.key === 'Enter' && !e.shiftKey) { 
            e.preventDefault(); 
            sendPrompt(); 
        } 
    };
});
