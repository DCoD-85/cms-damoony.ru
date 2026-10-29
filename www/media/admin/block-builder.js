document.addEventListener('DOMContentLoaded', () => {
    const addBtn = document.getElementById('add-block-btn');
    const container = document.getElementById('blocks-container');

    if (!addBtn || !container) return;

    addBtn.addEventListener('click', () => {
        const rows = container.querySelectorAll('.editor-block-row');
        const newIndex = rows.length;

        // Берем HTML-шаблон из скрытого тега (мы добавим его в PHP)
        const template = document.getElementById('block-template');
        if (!template) return;

        let html = template.innerHTML.replace(/9999/g, newIndex);
        
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = html.trim();
        
        container.appendChild(tempDiv.firstChild);
    });

    // Удаление блока
    container.addEventListener('click', (e) => {
        if (e.target.closest('.remove-block-btn')) {
            const row = e.target.closest('.editor-block-row');
            if (row && confirm('Удалить этот блок?')) {
                row.remove();
            }
        }
    });
});
