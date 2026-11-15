document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('chat-form');
    if (!form) return;

    let chatHistory = [
        { role: 'assistant', content: 'Здравствуйте! Я помогу вам создать сайт. Расскажите, что это будет за проект?' }
    ];

    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        const input = document.getElementById('chat-input');
        const historyDiv = document.getElementById('chat-history');
        const message = input.value.trim();
        if (!message) return;

        historyDiv.innerHTML += `<div class="chat-message user">${message}</div>`;
        input.value = '';
        historyDiv.scrollTop = historyDiv.scrollHeight;

        // ПРОВЕРКА НА СБРОС
        const resetWords = ['удали', 'сброс', 'отмена', 'начнем заново', 'начнём заново'];
        if (resetWords.some(w => message.toLowerCase().includes(w))) {
            chatHistory = [
                { role: 'assistant', content: 'Здравствуйте! Я помогу вам создать сайт. Расскажите, что это будет за проект?' }
            ];
            historyDiv.innerHTML = `<div class="chat-message ai">Здравствуйте! Я помогу вам создать сайт. Расскажите, что это будет за проект?</div>`;
            historyDiv.scrollTop = historyDiv.scrollHeight;
            return; 
        }

        // ПЕРЕХВАТ КОМАНДЫ "СОЗДАВАЙ"
        if (message.toLowerCase() === 'создавай') {
            historyDiv.innerHTML += `<div class="chat-message ai" id="loading-gen" data-status="Запуск агента...">⏳ Запуск... <button id="cancel-gen" style="margin-left:15px; background:none; border:none; color:#e74c3c; cursor:pointer; font-weight:bold; text-decoration:underline;">Остановить</button></div>`;
            
            try {
                const response = await fetch('/ajax/', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ action: 'generate', history: JSON.stringify(chatHistory) })
                });
                const data = await response.json();
                
                if (data.ok) {
                    // Кнопка Остановить
                    document.getElementById('cancel-gen').addEventListener('click', async () => {
                        const btn = document.getElementById('cancel-gen');
                        btn.disabled = true;
                        btn.innerText = 'Отмена...';
                        await fetch('/ajax/', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams({ action: 'cancel', session_id: data.session_id })
                        });
                    });

                    // ЗАПУСКАЕМ ТАЙМЕР
                    let secondsElapsed = 0;
                    const timerId = setInterval(() => {
                        secondsElapsed++;
                        const mins = Math.floor(secondsElapsed / 60);
                        const secs = secondsElapsed % 60;
                        const timeStr = `${mins}:${secs.toString().padStart(2, '0')}`;
                        
                        const el = document.getElementById('loading-gen');
                        if (el) {
                            const statusText = el.getAttribute('data-status') || 'Подготовка...';
                            const cancelBtn = el.querySelector('#cancel-gen') ? el.querySelector('#cancel-gen').outerHTML : '';
                            el.innerHTML = `⚙️ ${statusText} <strong style="font-family:monospace; font-size:16px; margin-left:8px; color:#007bff;">[${timeStr}]</strong> ${cancelBtn}`;
                            // Перепривязываем событие, так как innerHTML перезаписал кнопку
                            if (el.querySelector('#cancel-gen') && !el.querySelector('#cancel-gen').disabled) {
                                el.querySelector('#cancel-gen').addEventListener('click', async () => {
                                    const btn = el.querySelector('#cancel-gen');
                                    btn.disabled = true;
                                    btn.innerText = 'Отмена...';
                                    await fetch('/ajax/', {
                                        method: 'POST',
                                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                        body: new URLSearchParams({ action: 'cancel', session_id: data.session_id })
                                    });
                                });
                            }
                        }
                    }, 1000);

                    // ОПРОС СТАТУСА
                    const poll = setInterval(async () => {
                        const st = await fetch('/ajax/', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams({ action: 'status', session_id: data.session_id })
                        }).then(r => r.json());

                        if (st.ok && st.done) {
                            clearInterval(poll);
                            clearInterval(timerId);
                            document.getElementById('loading-gen').remove();
                            
                            const mins = Math.floor(secondsElapsed / 60);
                            const secs = secondsElapsed % 60;
                            const timeStr = `${mins}:${secs.toString().padStart(2, '0')}`;
                            
                            historyDiv.innerHTML += `<div class="chat-message ai">🎉 Сайт готов за ${timeStr}! <br><br> <a href="${st.link}" target="_blank" style="display:inline-block; padding:10px 20px; background:#007bff; color:white; text-decoration:none; border-radius:8px; font-weight:bold;">Открыть ${st.link}</a></div>`;
                            historyDiv.scrollTop = historyDiv.scrollHeight;
                        } else if (!st.ok) {
                            clearInterval(poll);
                            clearInterval(timerId);
                            document.getElementById('loading-gen').remove();
                            historyDiv.innerHTML += `<div class="chat-message error">Ошибка: ${st.error}</div>`;
                            historyDiv.scrollTop = historyDiv.scrollHeight;
                        } else {
                            // Обновляем статус
                            const el = document.getElementById('loading-gen');
                            if (el && st.status_text) {
                                el.setAttribute('data-status', st.status_text);
                            }
                        }
                    }, 3000);
                } else {
                    document.getElementById('loading-gen').remove();
                    historyDiv.innerHTML += `<div class="chat-message error">Ошибка запуска: ${data.error}</div>`;
                }
            } catch (err) {
                const el = document.getElementById('loading-gen');
                if (el) el.remove();
                historyDiv.innerHTML += `<div class="chat-message error">Сбой сети: ${err.message}</div>`;
            }
            return;
        }

        // ОБЫЧНЫЙ ЧАТ
        chatHistory.push({ role: 'user', content: message });
        const loadingId = 'loading-' + Date.now();
        historyDiv.innerHTML += `<div class="chat-message ai" id="${loadingId}">Думает...</div>`;
        
        try {
            const response = await fetch('/ajax/', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'chat', history: JSON.stringify(chatHistory) })
            });

            const json = await response.json();
            document.getElementById(loadingId).remove();

            if (json.ok === true) {
                const reply = (json.data && json.data.reply) ? json.data.reply : (json.reply || 'Пустой ответ');
                
                // Безопасное добавление текста (экранирование HTML)
                const safeReply = reply.replace(/</g, "&lt;").replace(/>/g, "&gt;");
                historyDiv.innerHTML += `<div class="chat-message ai">${safeReply}</div>`;
                
                if (reply.includes('сбросил все настройки')) {
                    chatHistory = [{ role: 'assistant', content: reply }];
                } else {
                    chatHistory.push({ role: 'assistant', content: reply });
                }
                
                if (reply.includes('Создавай')) {
                    input.value = 'Создавай';
                    input.focus();
                }
            } else {
                historyDiv.innerHTML += `<div class="chat-message error">Ошибка: ${json.error}</div>`;
            }
        } catch (err) {
            document.getElementById(loadingId).remove();
            historyDiv.innerHTML += `<div class="chat-message error">Сбой: ${err.message}</div>`;
        }
        historyDiv.scrollTop = historyDiv.scrollHeight;
    });
});