<?php
declare(strict_types=1);
// TODO: require admin auth
$domain = $_GET['domain'] ?? 'damoony3test.ru';
$alias = $_GET['alias'] ?? 'ai_form_test_v2';
$page = $_GET['page'] ?? 'contacts';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>AIUI Editor</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: system-ui, sans-serif; }
        body { display: flex; height: 100vh; background: #f0f2f5; }
        #preview { flex: 1; border-right: 1px solid #ccc; background: #fff; }
        #preview iframe { width: 100%; height: 100%; border: none; }
        #chat { width: 400px; display: flex; flex-direction: column; background: #fff; }
        #messages { flex: 1; padding: 20px; overflow-y: auto; display: flex; flex-direction: column; gap: 15px; }
        .msg { padding: 10px 15px; border-radius: 8px; max-width: 90%; font-size: 14px; }
        .msg.user { background: #007bff; color: #fff; align-self: flex-end; }
        .msg.ai { background: #e9ecef; color: #333; align-self: flex-start; }
        .msg.error { background: #f8d7da; color: #721c24; }
        #input-area { padding: 15px; border-top: 1px solid #ccc; display: flex; gap: 10px; }
        #prompt { flex: 1; padding: 10px; border: 1px solid #ccc; border-radius: 4px; resize: none; }
        #send { padding: 10px 20px; background: #28a745; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
        #send:disabled { background: #6c757d; }
    </style>
</head>
<body>

<div id="preview">
    <iframe id="site-frame" src="http://<?= htmlspecialchars($alias) ?>.<?= htmlspecialchars($domain) ?>/"></iframe>
</div>

<div id="chat">
    <div id="messages">
        <div class="msg ai">Привет! Я AIUI. Что изменить на странице? Например: "Добавь заголовок H1 сверху".</div>
    </div>
    <div id="input-area">
        <textarea id="prompt" rows="3" placeholder="Напишите команду..."></textarea>
        <button id="send" onclick="sendPrompt()">→</button>
    </div>
</div>

<script>
    const domain = "<?= $domain ?>";
    const alias = "<?= $alias ?>";
    const page = "<?= $page ?>";

    async function sendPrompt() {
        const input = document.getElementById('prompt');
        const btn = document.getElementById('send');
        const prompt = input.value.trim();
        if (!prompt) return;

        addMessage(prompt, 'user');
        input.value = '';
        btn.disabled = true;

        try {
            // Запрос к нашему endpoint
            const res = await fetch('/admin/ai_edit.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ domain, alias, page, prompt, apply: true })
            });

            const data = await res.json();

            if (data.ok) {
                addMessage('Готово! Изменения применены. Обновляю превью...', 'ai');
                // Перезагружаем iframe (сбрасываем кэш)
                const iframe = document.getElementById('site-frame');
                iframe.src = iframe.src;
            } else {
                addMessage('Ошибка: ' + data.error, 'error');
            }
        } catch (e) {
            addMessage('Сетевая ошибка: ' + e.message, 'error');
        } finally {
            btn.disabled = false;
        }
    }

    function addMessage(text, type) {
        const div = document.createElement('div');
        div.className = 'msg ' + type;
        div.innerText = text;
        document.getElementById('messages').appendChild(div);
        document.getElementById('messages').scrollTop = document.getElementById('messages').scrollHeight;
    }
</script>
</body>
</html>
