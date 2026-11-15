document.addEventListener('DOMContentLoaded', () => {
    // Тосты
    const btnSuccess = document.getElementById('btn-toast-success');
    if (btnSuccess) btnSuccess.addEventListener('click', () => CLIEngine.UI.toast('Данные успешно сохранены!', 'success'));

    const btnError = document.getElementById('btn-toast-error');
    if (btnError) btnError.addEventListener('click', () => CLIEngine.UI.toast('Произошла ошибка сервера!', 'error'));

    const btnWarning = document.getElementById('btn-toast-warning');
    if (btnWarning) btnWarning.addEventListener('click', () => CLIEngine.UI.toast('Внимание, лимит запросов!', 'warning'));

    const btnInfo = document.getElementById('btn-toast-info');
    if (btnInfo) btnInfo.addEventListener('click', () => CLIEngine.UI.toast('Справка: это тест.', 'info'));

    // Fetch API
    const btnGet = document.getElementById('btn-fetch-get');
    if (btnGet) {
        btnGet.addEventListener('click', async () => {
            try {
                await CLIEngine.Core.request("/admin/ru/domains/damoony.ru/db");
                CLIEngine.UI.toast("GET запрос прошел успешно!", "success");
            } catch(e) {}
        });
    }

    const btnPost = document.getElementById('btn-fetch-post');
    if (btnPost) {
        btnPost.addEventListener('click', async () => {
            const formData = new FormData();
            formData.append("table_name", "123invalid!");
            try {
                await CLIEngine.Core.post("/admin/ru/domains/damoony.ru/db", formData);
                CLIEngine.UI.toast("POST запрос прошел успешно!", "success");
            } catch(e) {}
        });
    }
});
