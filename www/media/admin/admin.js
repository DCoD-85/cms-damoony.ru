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
        let slotIndex = slotsContainer.children.length;

        addSlotBtn.addEventListener('click', () => {
            const html = `
                <div class="editor-row">
                    <div class="move-buttons">
                        <button type="button" class="btn btn-outline move-up-btn">▲</button>
                        <button type="button" class="btn btn-outline move-down-btn">▼</button>
                    </div>
                    <select name="slots[${slotIndex}][name]" class="type-select">
                        <option value="center">center</option>
                        <option value="top">top</option>
                        <option value="left">left</option>
                        <option value="right">right</option>
                        <option value="bottom">bottom</option>
                        <option value="footer">footer</option>
                    </select>
                    <select name="slots[${slotIndex}][tag]" class="tag-select">
                        <option value="div">&lt;div&gt;</option>
                        <option value="header">&lt;header&gt;</option>
                        <option value="main">&lt;main&gt;</option>
                        <option value="footer">&lt;footer&gt;</option>
                        <option value="aside">&lt;aside&gt;</option>
                        <option value="section">&lt;section&gt;</option>
                    </select>
                    <input type="text" name="slots[${slotIndex}][class]" placeholder="CSS класс" class="content-input">
                    <button type="button" class="btn btn-danger remove-slot-btn remove-btn">✕</button>
                </div>
            `;
            slotsContainer.insertAdjacentHTML('beforeend', html);
            slotIndex++;
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