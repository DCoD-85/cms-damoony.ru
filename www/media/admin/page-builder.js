document.addEventListener('DOMContentLoaded', () => {
    const dataEl = document.getElementById('pb-init-data');
    if (!dataEl) return;

    const initData = JSON.parse(dataEl.textContent);
    const state = {
        slots: initData.slots,
        available: initData.available
    };

    const elCanvas = document.getElementById('pb-canvas');
    const elJsonInput = document.querySelector('input[name="page_json"]');

    function renderAll() {
        elCanvas.innerHTML = '';
        state.available.forEach(slotName => {
            const slotData = state.slots[slotName] || { status: 'global', blocks: [] };
            
            const elSlot = document.createElement('div');
            elSlot.className = 'pb-slot';

            const header = document.createElement('div');
            header.className = 'pb-slot-header';
            header.innerHTML = `<span>Слот: ${slotName}</span>`;

            const select = document.createElement('select');
            ['global', 'local', 'disabled'].forEach(st => {
                const opt = document.createElement('option');
                opt.value = st;
                opt.textContent = st === 'global' ? 'Глобальный (базовый)' : (st === 'local' ? 'Локальный (свой)' : 'Отключить');
                if (slotData.status === st) opt.selected = true;
                select.appendChild(opt);
            });
            select.addEventListener('change', (e) => {
                slotData.status = e.target.value;
                renderAll();
            });
            header.appendChild(select);

            const body = document.createElement('div');
            body.className = `pb-slot-body ${slotData.status === 'disabled' ? 'disabled' : ''}`;
            
            if (slotData.status === 'disabled') {
                body.textContent = 'Слот отключен на этой странице';
            } else if (slotData.status === 'global') {
                body.textContent = 'Используется глобальный блок (без изменений)';
                body.style.color = '#888';
            } else {
                // Local: режим Drag & Drop блоков
                body.addEventListener('dragover', e => { e.preventDefault(); body.classList.add('drag-over'); });
                body.addEventListener('dragleave', () => body.classList.remove('drag-over'));
                body.addEventListener('drop', e => {
                    e.preventDefault();
                    body.classList.remove('drag-over');
                    const type = e.dataTransfer.getData('type');
                    if (type) {
                        slotData.blocks.push({ type, content: '' });
                        renderAll();
                    }
                });

                if (slotData.blocks.length === 0) {
                    body.innerHTML = '<div style="color:#888;text-align:center;">Перетащите сюда блоки справа</div>';
                }

                slotData.blocks.forEach((block, idx) => {
                    const elBlock = document.createElement('div');
                    elBlock.className = 'pb-block';
                    
                    const label = document.createElement('span');
                    label.textContent = `[${block.type}]`;
                    elBlock.appendChild(label);

                    const input = document.createElement('input');
                    input.type = 'text';
                    input.placeholder = 'Введите контент...';
                    input.value = block.content;
                    input.addEventListener('input', (e) => block.content = e.target.value);
                    elBlock.appendChild(input);

                    const delBtn = document.createElement('button');
                    delBtn.type = 'button';
                    delBtn.innerHTML = '✕';
                    delBtn.className = 'btn btn-danger';
                    delBtn.style.padding = '4px 8px';
                    delBtn.addEventListener('click', () => {
                        slotData.blocks.splice(idx, 1);
                        renderAll();
                    });
                    elBlock.appendChild(delBtn);

                    body.appendChild(elBlock);
                });
            }

            elSlot.appendChild(header);
            elSlot.appendChild(body);
            elCanvas.appendChild(elSlot);
        });
    }

    // Настройка инструментов (перетаскивание)
    document.querySelectorAll('.pb-tool').forEach(tool => {
        tool.addEventListener('dragstart', e => {
            e.dataTransfer.setData('type', tool.dataset.type);
            e.dataTransfer.effectAllowed = 'copy';
        });
    });

    // Сохранение
    const form = document.querySelector('.page-builder-app form');
    if (form) {
        form.addEventListener('submit', () => {
            elJsonInput.value = JSON.stringify({ slots: state.slots });
        });
    }

    renderAll();
});