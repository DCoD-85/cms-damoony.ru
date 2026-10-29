document.addEventListener('DOMContentLoaded', () => {
    const dataEl = document.getElementById('pb-init-data');
    if (!dataEl) return;

    const initData = JSON.parse(dataEl.textContent);
    const state = {
        slots: initData.slots,
        available: initData.available,
        auth: initData.auth || false,
        activeTab: 'visual' // 'visual' | 'settings'
    };

    const elCanvas = document.getElementById('pb-canvas');
    const elJsonInput = document.querySelector('input[name="page_json"]');

    function renderAll() {
        elCanvas.innerHTML = '';

        // 1. Главные табы
        const mainTabs = document.createElement('div');
        mainTabs.className = 'pb-main-tabs';
        mainTabs.innerHTML = `
            <div class="pb-main-tab ${state.activeTab === 'visual' ? 'active' : ''}" data-tab="visual">Визуализация</div>
            <div class="pb-main-tab ${state.activeTab === 'settings' ? 'active' : ''}" data-tab="settings">Настройки страницы</div>
        `;
        elCanvas.appendChild(mainTabs);

        mainTabs.querySelectorAll('.pb-main-tab').forEach(tab => {
            tab.addEventListener('click', (e) => {
                state.activeTab = e.target.dataset.tab;
                renderAll();
            });
        });

        // 2. Контент
        if (state.activeTab === 'settings') {
            const contentArea = document.createElement('div');
            contentArea.innerHTML = `
                <div class="pb-settings">
                    <h3>Глобальные настройки</h3>
                    <label style="display:flex; align-items:center; gap:10px; cursor:pointer; font-weight:bold; margin-top:15px;">
                        <input type="checkbox" id="pb-auth-toggle" ${state.auth ? 'checked' : ''}>
                        Только для авторизованных пользователей (Auth)
                    </label>
                </div>
            `;
            elCanvas.appendChild(contentArea);
            document.getElementById('pb-auth-toggle').addEventListener('change', (e) => state.auth = e.target.checked);
            return;
        }

        // 3. Визуализация (просто список слотов друг под другом)
        state.available.forEach(slotName => {
            const slotData = state.slots[slotName] || { status: 'global', blocks: [] };
            if (!slotData.blocks) slotData.blocks = [];

            const elSlot = document.createElement('div');
            elSlot.className = 'pb-slot';

            const header = document.createElement('div');
            header.className = 'pb-slot-header';
            header.innerHTML = `<span>Слот: ${slotName}</span>`;

            const select = document.createElement('select');
            ['global', 'local', 'disabled'].forEach(st => {
                const opt = document.createElement('option');
                opt.value = st;
                opt.textContent = st === 'global' ? 'Глобальный' : (st === 'local' ? 'Локальный' : 'Отключить');
                if (slotData.status === st) opt.selected = true;
                select.appendChild(opt);
            });
            select.addEventListener('change', (e) => {
                slotData.status = e.target.value;
                renderAll();
            });
            header.appendChild(select);
            elSlot.appendChild(header);

            const body = document.createElement('div');
            body.className = `pb-slot-body ${slotData.status === 'disabled' ? 'disabled' : ''}`;
            
            if (slotData.status === 'disabled') {
                body.textContent = 'Слот отключен';
            } else if (slotData.status === 'global') {
                body.textContent = 'Используется глобальный блок';
                body.style.color = '#888';
            } else {
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
                    body.innerHTML = '<div style="color:#888;text-align:center;padding:15px;">Перетащите блоки сюда</div>';
                }

                slotData.blocks.forEach((block, idx) => {
                    const elBlock = document.createElement('div');
                    elBlock.className = 'pb-block-row';
                    elBlock.innerHTML = `<div class="pb-block-label">[${block.type}]</div>`;

                    if (block.type === 'code') {
                        const ta = document.createElement('textarea');
                        ta.className = 'pb-code-editor';
                        ta.value = block.content;
                        ta.addEventListener('input', (e) => block.content = e.target.value);
                        elBlock.appendChild(ta);
                    } else {
                        const input = document.createElement('input');
                        input.type = 'text';
                        input.className = 'pb-text-input';
                        input.value = block.content;
                        input.addEventListener('input', (e) => block.content = e.target.value);
                        elBlock.appendChild(input);
                    }

                    const delBtn = document.createElement('button');
                    delBtn.type = 'button';
                    delBtn.innerHTML = '✕';
                    delBtn.className = 'btn btn-danger';
                    delBtn.addEventListener('click', () => {
                        slotData.blocks.splice(idx, 1);
                        renderAll();
                    });
                    elBlock.appendChild(delBtn);
                    body.appendChild(elBlock);
                });
            }

            elSlot.appendChild(body);
            elCanvas.appendChild(elSlot);
        });
    }

    document.querySelectorAll('.pb-tool').forEach(tool => {
        tool.addEventListener('dragstart', e => {
            e.dataTransfer.setData('type', tool.dataset.type);
            e.dataTransfer.effectAllowed = 'copy';
        });
    });

    const form = document.querySelector('.page-builder-app form');
    if (form) form.addEventListener('submit', () => {
        elJsonInput.value = JSON.stringify({ slots: state.slots, auth: state.auth });
    });

    renderAll();
});