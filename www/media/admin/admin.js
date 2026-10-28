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
                indicator.style.background = '#ef4444'; // Красный
            } else if (strength <= 4) {
                indicator.style.width = '66%';
                indicator.style.background = '#f59e0b'; // Желтый
            } else {
                indicator.style.width = '100%';
                indicator.style.background = '#10b981'; // Зеленый
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
        
        // Устанавливаем высоту редактора
        editor.setSize(null, 600);
    }

    // 3. Визуальный редактор страниц (добавление/удаление блоков)
    const blocksContainer = document.getElementById('blocks-container');
    const addBlockBtn = document.getElementById('add-block-btn');

    if (blocksContainer && addBlockBtn) {
        let blockIndex = blocksContainer.children.length;

        addBlockBtn.addEventListener('click', () => {
            const html = `
                <div class="block-row" style="display:flex; gap:10px; margin-bottom:10px; padding:10px; border:1px solid #ddd; border-radius:8px; background:#fafafa;">
                    <select name="blocks[${blockIndex}][type]" style="width: 200px; padding:8px; border-radius:6px; border:1px solid #ccc;">
                        <option value="h1">Заголовок H1</option>
                        <option value="h2">Заголовок H2</option>
                        <option value="text">Текст</option>
                        <option value="img">Картинка (URL)</option>
                    </select>
                    <input type="text" name="blocks[${blockIndex}][content]" placeholder="Содержимое" style="flex-grow:1; padding:8px; border-radius:6px; border:1px solid #ccc;">
                    <button type="button" class="btn btn-danger remove-block-btn" style="padding: 0 15px;">✕</button>
                </div>
            `;
            blocksContainer.insertAdjacentHTML('beforeend', html);
            blockIndex++;
        });

        blocksContainer.addEventListener('click', (e) => {
            if (e.target.classList.contains('remove-block-btn')) {
                e.target.closest('.block-row').remove();
            }
        });
    }

    // 4. Визуальный редактор схем (добавление/удаление слотов)
    const slotsContainer = document.getElementById('slots-container');
    const addSlotBtn = document.getElementById('add-slot-btn');

    if (slotsContainer && addSlotBtn) {
        let slotIndex = slotsContainer.children.length;

        addSlotBtn.addEventListener('click', () => {
            const html = `
                <div class="block-row" style="display:flex; gap:10px; margin-bottom:10px; padding:10px; border:1px solid #ddd; border-radius:8px; background:#fafafa; align-items:center;">
                    <select name="slots[${slotIndex}][name]" style="width: 150px; padding:8px; border-radius:6px; border:1px solid #ccc;">
                        <option value="center">center</option>
                        <option value="top">top</option>
                        <option value="left">left</option>
                        <option value="right">right</option>
                        <option value="bottom">bottom</option>
                        <option value="footer">footer</option>
                    </select>
                    <select name="slots[${slotIndex}][tag]" style="width: 120px; padding:8px; border-radius:6px; border:1px solid #ccc;">
                        <option value="div">&lt;div&gt;</option>
                        <option value="header">&lt;header&gt;</option>
                        <option value="main">&lt;main&gt;</option>
                        <option value="footer">&lt;footer&gt;</option>
                        <option value="aside">&lt;aside&gt;</option>
                        <option value="section">&lt;section&gt;</option>
                    </select>
                    <input type="text" name="slots[${slotIndex}][class]" placeholder="CSS класс (например, main-wrap)" style="flex-grow:1; padding:8px; border-radius:6px; border:1px solid #ccc;">
                    <button type="button" class="btn btn-danger remove-slot-btn" style="padding: 0 15px;">✕</button>
                </div>
            `;
            slotsContainer.insertAdjacentHTML('beforeend', html);
            slotIndex++;
        });

        slotsContainer.addEventListener('click', (e) => {
            if (e.target.classList.contains('remove-slot-btn')) {
                e.target.closest('.block-row').remove();
            }
        });
    }
	    // Сортировка слотов (Вверх/Вниз) для схем и страниц
    document.addEventListener('click', function(e) {
        if (e.target.closest('.move-up-btn')) {
            const row = e.target.closest('.block-row');
            if (row && row.previousElementSibling) {
                row.parentNode.insertBefore(row, row.previousElementSibling);
            }
        }
        if (e.target.closest('.move-down-btn')) {
            const row = e.target.closest('.block-row');
            if (row && row.nextElementSibling) {
                row.parentNode.insertBefore(row.nextElementSibling, row);
            }
        }
    });
});