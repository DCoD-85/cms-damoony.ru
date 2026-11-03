document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('chat-form');
    if (!form) return;

    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const input = document.getElementById('chat-input');
        const history = document.getElementById('chat-history');
        const message = input.value.trim();
        
        if (!message) return;
        
        // 1. Dobavlyaem soobschenie polzovatelya
        history.innerHTML += `<div class="chat-message user">${message}</div>`;
        input.value = '';
        history.scrollTop = history.scrollHeight;

        // 2. Pokazyvaem indikator zagruzki
        const loadingId = 'loading-' + Date.now();
        history.innerHTML += `<div class="chat-message ai" id="${loadingId}">Печатает...</div>`;
        history.scrollTop = history.scrollHeight;

        try {
            // 3. Otpravlyaem dannye na AJAX
            const response = await fetch('/ajax', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({
                    action: 'chat',
                    message: message
                })
            });

            const data = await response.json();
            document.getElementById(loadingId).remove();

            // 4. Vyvodim resultat
            if (data.ok) {
                history.innerHTML += `<div class="chat-message ai">${data.data.reply}</div>`;
            } else {
                history.innerHTML += `<div class="chat-message error">Ошибка: ${data.error}</div>`;
            }
        } catch (err) {
            document.getElementById(loadingId).remove();
            history.innerHTML += `<div class="chat-message error">Ошибка сети. Попробуйте еще раз.</div>`;
        }
        
        history.scrollTop = history.scrollHeight;
    });
});