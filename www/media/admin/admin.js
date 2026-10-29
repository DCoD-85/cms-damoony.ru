document.addEventListener('DOMContentLoaded', () => {
    // 1. Индикатор надежности пароля (в профиле)
    const pwdInput = document.querySelector('input[name="new_password"]');
    const indicator = document.getElementById('pwd-strength');
    
    if (pwdInput && indicator) {
        pwdInput.addEventListener('input', () => {
            const val = pwdInput.value;
            let strength = 0;
            
            if (val.length >= 6) strength++;
            if (val.length >= 10) strength++;
            if (/[A-Z]/.test(val)) strength++;
            if (/[0-9]/.test(val)) strength++;
            if (/[^A-Za-z0-9]/.test(val)) strength++;

            if (val.length === 0) {
                indicator.style.display = 'none';
                return;
            }

            indicator.style.display = 'block';
            
            if (strength <= 2) {
                indicator.style.width = '33%';
                indicator.style.background = '#ef4444';
            } else if (strength <= 4) {
                indicator.style.width = '66%';
                indicator.style.background = '#f59e0b';
            } else {
                indicator.style.width = '100%';
                indicator.style.background = '#10b981';
            }
        });
    }

    // 2. Инициализация редактора кода (CodeMirror)
    const editorTextarea = document.getElementById('code-editor');
    if (editorTextarea && typeof CodeMirror !== 'undefined') {
        const mode = editorTextarea.dataset.mode || 'text/plain';
        
        const editor = CodeMirror.fromTextArea(editorTextarea, {
            lineNumbers: true,
            mode: mode,
            theme: "dracula",
            indentUnit: 4,
            matchBrackets: true,
            autoCloseBrackets: true,
            lineWrapping: true,
            height: "auto"
        });
        
        editor.setSize(null, 600);
    }

    // 3. Визуальный редактор схем (добавление/удаление слотов)
    const slotsContainer = document.getElementById('slots-container');
    const addSlotBtn = document.getElementById('add-slot-btn');

    if (slotsContainer && addSlotBtn) {
        addSlotBtn.addEventListener('click', () => {
            const slotIndex = slotsContainer.children.length;
            const firstRow = slotsContainer.querySelector('.editor-row');
            
            if (!firstRow) return; // Если нет строки-шаблона

            // Клонируем существующую строку со всеми полями
            const newRow = firstRow.cloneNode(true);
            
            newRow.querySelectorAll('select, input').forEach(el => {
                // 1. Меняем индексы в name (slots[0] -> slots[N])
                if (el.name) {
                    el.name = el.name.replace(/slots\[\d+\]/, `slots[${slotIndex}]`);
                }
                
                // 2. Генерируем уникальный ID для новой строки 
                // (используем timestamp, чтобы он точно не пересекся с серверными 0, 1, 2)
                if (el.type === 'hidden' && el.name.endsWith('[id]')) {
                    el.value = Date.now();
                }
                
                // 3. Сбрасываем значения для новой строки по умолчанию
                if (el.classList.contains('parent-select')) el.selectedIndex = 0;
                if (el.classList.contains('type-select')) el.selectedIndex = 0;
                if (el.classList.contains('content-input')) el.value = '';
                if (el.classList.contains('layout-select')) el.selectedIndex = 0;
            });

            slotsContainer.appendChild(newRow);
        });

        slotsContainer.addEventListener('click', (e) => {
            if (e.target.classList.contains('remove-slot-btn')) {
                e.target.closest('.editor-row').remove();
            }
        });
    }

    // 4. Сортировка (Вверх/Вниз) — работает для схем и страниц
    document.addEventListener('click', function(e) {
        if (e.target.closest('.move-up-btn')) {
            const row = e.target.closest('.editor-row');
            if (row && row.previousElementSibling) {
                row.parentNode.insertBefore(row, row.previousElementSibling);
            }
        }
        if (e.target.closest('.move-down-btn')) {
            const row = e.target.closest('.editor-row');
            if (row && row.nextElementSibling) {
                row.parentNode.insertBefore(row.nextElementSibling, row);
            }
        }
    });

    // 5. Удаление блоков (в любом контейнере)
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-block-btn')) {
            e.target.closest('.editor-row').remove();
        }
    });

    // 6. Переключение статуса слота (disabled/global/local) в редакторе страниц
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('slot-status-select')) {
            const card = e.target.closest('.editor-card');
            const body = card ? card.querySelector('.slot-body') : null;
            if (body) {
                body.style.display = (e.target.value === 'local') ? 'block' : 'none';
            }
        }
    });

    // 7. Добавление блока внутри конкретного слота (когда статус = local)
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('add-slot-block-btn')) {
            const btn = e.target;
            const prefix = btn.dataset.prefix;
            const container = btn.previousElementSibling;
            
            if (!container) return;
            
            const index = container.children.length;
            const html = `
                <div class="editor-row">
                    <select name="${prefix}[${index}][type]" class="type-select">
                        <option value="text">Текст</option>
                        <option value="h1">Заголовок H1</option>
                        <option value="h2">Заголовок H2</option>
                        <option value="img">Картинка (URL)</option>
                    </select>
                    <input type="text" name="${prefix}[${index}][content]" placeholder="Содержимое" class="content-input">
                    <button type="button" class="btn btn-danger remove-block-btn remove-btn">✕</button>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);
        }
    });
});