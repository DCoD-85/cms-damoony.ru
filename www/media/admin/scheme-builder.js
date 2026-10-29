document.addEventListener('DOMContentLoaded', () => {
    const dataEl = document.getElementById('sb-init-data');
    if (!dataEl) return;

    let initData = JSON.parse(dataEl.textContent);
    
    let scheme = initData.scheme || { children: [], scripts: [] };
    if (!scheme.children) scheme.children = [];
    if (!scheme.scripts) scheme.scripts = [];

    const state = {
        scheme: scheme,
        fields: initData.fields || [],
        available: [],
        dataBlocks: [],
        selectedNode: null
    };

    function syncAvailable() {
        const usedSlots = [];
        const collectSlots = (node) => {
            if (node.slot) usedSlots.push(node.slot);
            if (node.children) node.children.forEach(collectSlots);
        };
        if (state.scheme.children) state.scheme.children.forEach(collectSlots);
        const usedScripts = state.scheme.scripts || [];

        state.available = [];
        state.dataBlocks = [];

        state.fields.forEach(block => {
            if (block.type === 'script') {
                if (usedScripts.includes(block.name)) {
                    state.dataBlocks.push(block);
                } else {
                    state.available.push(block);
                }
            } else {
                if (!usedSlots.includes(block.name)) {
                    state.available.push(block);
                }
            }
        });
    }
    syncAvailable();

    const elCanvas = document.getElementById('sb-canvas');
    const elZoneAvailable = document.getElementById('sb-zone-available');
    const elZoneData = document.getElementById('sb-zone-data');
    const elProps = document.getElementById('sb-properties');
    const elFileView = document.getElementById('sb-file-view');
    const elJsonInput = document.querySelector('input[name="scheme_json"]');

    function renderAvailable() {
        elZoneAvailable.querySelectorAll('.sb-item').forEach(e => e.remove());
        state.available.forEach(block => {
            const div = document.createElement('div');
            div.className = `sb-item sb-item-${block.type}`;
            div.textContent = `${block.name} [${block.type}]`;
            div.draggable = true;
            div.addEventListener('dragstart', e => {
                e.stopPropagation();
                window.draggedSource = 'available';
                window.draggedBlock = block;
                e.dataTransfer.setData('text/plain', 'available');
            });
            elZoneAvailable.appendChild(div);
        });
    }

    function renderDataZone() {
        elZoneData.querySelectorAll('.sb-item').forEach(e => e.remove());
        state.dataBlocks.forEach(block => {
            const div = document.createElement('div');
            div.className = `sb-item sb-item-${block.type}`;
            div.textContent = `${block.name} [${block.type}]`;
            div.draggable = true;
            div.addEventListener('dragstart', e => {
                e.stopPropagation();
                window.draggedSource = 'tree-data';
                window.draggedNode = block;
                e.dataTransfer.setData('text/plain', 'tree-data');
            });
            elZoneData.appendChild(div);
        });
    }

    function renderTree(node, parentEl) {
        if (node === state.scheme) {
            if (node.children) node.children.forEach(child => renderTree(child, parentEl));
            return;
        }

        const wrapper = document.createElement('div');
        wrapper.className = 'sb-node-wrapper';
        const el = document.createElement('div');
        el.className = `sb-node sb-type-${node.type || 'mixed'}`;
        if (state.selectedNode === node) el.classList.add('selected');

        const header = document.createElement('div');
        header.className = 'sb-node-header';
        header.draggable = true;
        
        header.addEventListener('dragstart', e => {
            e.stopPropagation();
            window.draggedSource = 'tree';
            window.draggedNode = node;
            e.dataTransfer.setData('text/plain', 'tree');
        });
        
        let labelText = `<${node.tag || 'div'}>`;
        if (node.slot) labelText += ` slot:${node.slot}`;
        if (node.class) labelText += ` .${node.class}`;
        labelText += ` [${node.type || 'mixed'}]`;
        
        const labelSpan = document.createElement('span');
        labelSpan.textContent = labelText;
        header.appendChild(labelSpan);

        const delBtn = document.createElement('button');
        delBtn.type = 'button'; delBtn.className = 'sb-delete-node'; delBtn.innerHTML = '✕';
        delBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            returnToAvailable(node);
            renderAll();
        });
        header.appendChild(delBtn);

        header.addEventListener('click', e => {
            e.stopPropagation();
            state.selectedNode = node;
            renderAll();
        });

        el.appendChild(header);

        // Контейнер для детей — ТОЛЬКО для VISUAL. У MIXED и SCRIPT его нет.
        if (node.type === 'visual') {
            const childrenContainer = document.createElement('div');
            childrenContainer.className = 'sb-children';

            childrenContainer.addEventListener('dragover', e => {
                e.preventDefault(); e.stopPropagation();
                if (window.draggedSource === 'tree-data') return;
                if (window.draggedSource === 'tree' && (window.draggedNode === node || isDescendant(window.draggedNode, node))) return;
                childrenContainer.classList.add('drag-over-inside'); 
            });
            childrenContainer.addEventListener('dragleave', e => { e.stopPropagation(); childrenContainer.classList.remove('drag-over-inside'); });
            childrenContainer.addEventListener('drop', e => {
                e.preventDefault(); e.stopPropagation(); 
                childrenContainer.classList.remove('drag-over-inside');
                handleDrop(node, 'inside');
            });

            if (node.children) node.children.forEach(child => renderTree(child, childrenContainer));
            el.appendChild(childrenContainer);
        }

        wrapper.appendChild(el);

        header.addEventListener('dragover', e => {
            e.preventDefault(); e.stopPropagation();
            if (window.draggedSource === 'tree-data') return;
            if (window.draggedSource === 'tree' && (window.draggedNode === node || isDescendant(window.draggedNode, node))) return;
            const rect = header.getBoundingClientRect();
            wrapper.classList.add('drag-over-header');
            if ((e.clientY - rect.top) > (rect.height / 2)) wrapper.classList.add('drag-after'); 
            else wrapper.classList.remove('drag-after');
        });
        header.addEventListener('dragleave', e => { if (!wrapper.contains(e.relatedTarget)) wrapper.classList.remove('drag-over-header', 'drag-after'); });
        header.addEventListener('drop', e => {
            e.preventDefault(); e.stopPropagation();
            const isAfter = wrapper.classList.contains('drag-after');
            wrapper.classList.remove('drag-over-header', 'drag-after');
            handleDrop(node, isAfter ? 'after' : 'before');
        });

        parentEl.appendChild(wrapper);
    }

    function renderAll() {
        elCanvas.innerHTML = '';
        renderTree(state.scheme, elCanvas);
        renderAvailable();
        renderDataZone();
        renderProps();
        renderFile();
    }

    function isDescendant(parent, child) {
        if (!parent.children) return false;
        for (const c of parent.children) { if (c === child || isDescendant(c, child)) return true; }
        return false;
    }
    function removeNode(root, target) {
        if (!root.children) return false;
        const idx = root.children.indexOf(target);
        if (idx !== -1) { root.children.splice(idx, 1); return true; }
        return root.children.some(child => removeNode(child, target));
    }
    function findParent(root, target) {
        if (!root.children) return null;
        if (root.children.includes(target)) return root;
        for (const child of root.children) { const p = findParent(child, target); if (p) return p; }
        return null;
    }
    function returnToAvailable(node) {
        if (state.selectedNode === node) state.selectedNode = null;
        removeNode(state.scheme, node);
        syncAvailable();
    }

    function handleDrop(targetNode, position) {
        if (!window.draggedSource) return;

        if (window.draggedSource === 'tree-data' && targetNode === 'available') {
            const name = window.draggedNode.name;
            state.scheme.scripts = (state.scheme.scripts || []).filter(s => s !== name);
            syncAvailable(); renderAll(); resetDrag(); return;
        }

		if (position === 'inside' && targetNode !== state.scheme && targetNode.type === 'mixed') {
            alert('В блок mixed нельзя вкладывать другие элементы!');
            resetDrag(); return;
        }
        if (window.draggedSource === 'available') {
            const block = window.draggedBlock;
            
            if (targetNode === 'data-zone') {
                if (block.type !== 'script') { alert('Сюда можно бросать только Script-блоки!'); resetDrag(); return; }
                if (!state.scheme.scripts) state.scheme.scripts = [];
                state.scheme.scripts.push(block.name);
                syncAvailable(); renderAll(); resetDrag(); return;
            }

            if (block.type === 'script') { alert('Script-блоки нельзя перетаскивать в визуал!'); resetDrag(); return; }
            
            if (targetNode === state.scheme || targetNode) {
                // Защита от дублей
                const usedSlots = [];
                const collectSlots = (n) => {
                    if (n.slot) usedSlots.push(n.slot);
                    if (n.children) n.children.forEach(collectSlots);
                };
                state.scheme.children.forEach(collectSlots);
                if (usedSlots.includes(block.name)) {
                    alert('Этот блок уже добавлен в схему!');
                    resetDrag(); return;
                }

                const newNode = { tag: 'div', slot: block.name, type: block.type };
                insertNode(newNode, targetNode, position);
            }
        }
        
        else if (window.draggedSource === 'tree') {
            const node = window.draggedNode;
            if (targetNode === 'available') {
                returnToAvailable(node); renderAll(); resetDrag(); return;
            }
            if (node === targetNode || isDescendant(node, targetNode)) {
                alert('Нельзя переместить элемент внутрь самого себя!'); resetDrag(); return;
            }
            removeNode(state.scheme, node);
            insertNode(node, targetNode, position);
        }

        resetDrag();
    }

    function insertNode(newNode, targetNode, position) {
        if (position === 'inside') {
            if (!targetNode.children) targetNode.children = [];
            targetNode.children.push(newNode);
        } else if (position === 'before' || position === 'after') {
            const parent = findParent(state.scheme, targetNode);
            if (parent && parent.children) {
                const idx = parent.children.indexOf(targetNode);
                parent.children.splice(position === 'before' ? idx : idx + 1, 0, newNode);
            }
        }
        state.selectedNode = newNode;
        syncAvailable(); // <-- Обновляем доступные блоки ДО рендера
        renderAll();
    }

    function resetDrag() {
        window.draggedSource = null; window.draggedNode = null; window.draggedBlock = null;
    }

    function renderProps() {
        elProps.innerHTML = '';
        if (!state.selectedNode) { elProps.innerHTML = '<p style="color:#888;">Выберите элемент</p>'; return; }
        const node = state.selectedNode;

        const clsGroup = document.createElement('div'); clsGroup.className = 'sb-prop-group'; clsGroup.innerHTML = '<label>CSS Класс</label>';
        const inp = document.createElement('input'); inp.type = 'text'; inp.value = node.class || '';
        inp.addEventListener('input', e => { node.class = e.target.value; renderAll(); });
        clsGroup.appendChild(inp); elProps.appendChild(clsGroup);

        if (node.type !== 'visual') {
            const tagGroup = document.createElement('div'); tagGroup.className = 'sb-prop-group'; tagGroup.innerHTML = '<label>Тег</label>';
            const select = document.createElement('select');
            ['div', 'header', 'main', 'footer', 'section', 'article', 'aside'].forEach(t => {
                const o = document.createElement('option'); o.value = t; o.textContent = `<${t}>`;
                if (node.tag === t) o.selected = true;
                select.appendChild(o);
            });
            select.addEventListener('change', e => { node.tag = e.target.value; renderAll(); });
            tagGroup.appendChild(select); elProps.appendChild(tagGroup);
        }
    }

    function renderFile() {
        elFileView.innerHTML = state.selectedNode 
            ? `<strong>Блок:</strong> ${state.selectedNode.slot || 'container'}<br><br><em>(Код файла)</em>`
            : '<p style="color:#888;">Файл не выбран</p>';
    }

    elCanvas.addEventListener('dragover', e => { e.preventDefault(); });
    elCanvas.addEventListener('drop', e => { e.preventDefault(); if (window.draggedSource === 'tree' || window.draggedSource === 'available') handleDrop(state.scheme, 'inside'); });
    
    elZoneAvailable.addEventListener('dragover', e => { e.preventDefault(); });
    elZoneAvailable.addEventListener('drop', e => { 
        e.preventDefault(); 
        if (window.draggedSource === 'tree' || window.draggedSource === 'tree-data') handleDrop('available', 'inside'); 
    });

    elZoneData.addEventListener('dragover', e => { e.preventDefault(); });
    elZoneData.addEventListener('drop', e => { e.preventDefault(); if (window.draggedSource === 'available' && window.draggedBlock.type === 'script') handleDrop('data-zone', 'inside'); });

    const form = document.querySelector('.scheme-builder-app form');
    if (form) form.addEventListener('submit', () => { 
        elJsonInput.value = JSON.stringify(state.scheme); 
    });

    renderAll();
});