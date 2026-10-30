document.addEventListener('DOMContentLoaded', () => {
    const tools = document.querySelectorAll('.pb-tool');
    const container = document.getElementById('blocks-container');

    if (!container) return;

    function addBlock(type) {
        const rows = container.querySelectorAll('.editor-block-row');
        const newIndex = rows.length;

        const template = document.getElementById('block-template');
        if (!template) return;

        let html = template.innerHTML.replace(/9999/g, newIndex);
        
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = html.trim();
        let newNode = tempDiv.firstChild;

        if (type && newNode) {
            newNode.setAttribute('data-type', type);
            
            const title = newNode.querySelector('.pb-block-title');
            if (title) title.textContent = type.toUpperCase();
            
            const hiddenType = newNode.querySelector('input[type="hidden"]');
            if (hiddenType) hiddenType.value = type;

            const existingTextarea = newNode.querySelector('.pb-textarea');
            const existingInput = newNode.querySelector('.pb-input');
            if (existingTextarea) existingTextarea.remove();
            if (existingInput) existingInput.remove();

            if (type === 'if') {
                const input = document.createElement('input');
                input.type = 'text';
                input.className = 'pb-input code-font';
                input.name = `blocks[${newIndex}][condition]`;
                input.placeholder = '$isAuth === true';
                newNode.appendChild(input);
            } 
            else if (type === 'custom') {
                const textarea = document.createElement('textarea');
                textarea.className = 'pb-textarea code-font';
                textarea.name = `blocks[${newIndex}][code]`;
                textarea.placeholder = '// PHP code here...';
                textarea.rows = 5;
                newNode.appendChild(textarea);
            }
            else if (type === 'h1' || type === 'h2' || type === 'text') {
                const textarea = document.createElement('textarea');
                textarea.className = 'pb-textarea';
                textarea.name = `blocks[${newIndex}][content]`;
                textarea.placeholder = 'Введите текст...';
                textarea.rows = 3;
                newNode.appendChild(textarea);
            }
            else if (type === 'img') {
                const input = document.createElement('input');
                input.type = 'text';
                input.className = 'pb-input';
                input.name = `blocks[${newIndex}][content]`;
                input.placeholder = 'URL картинки';
                newNode.appendChild(input);
            }
        }

        if (newNode) {
            container.appendChild(newNode);
        }
    }

    tools.forEach(btn => {
        btn.addEventListener('click', () => {
            addBlock(btn.getAttribute('data-type'));
        });
    });

    container.addEventListener('click', (e) => {
        if (e.target.closest('.remove-block-btn')) {
            const row = e.target.closest('.editor-block-row');
            if (row) {
                row.remove();
            }
        }
    });

    // --- ДОБАВЛЯЕМ КНОПКУ "ПОКАЗАТЬ PHP" ---
    const form = container.closest('form');
    const saveBtn = form ? form.querySelector('button[type="submit"]') : null;
    
    if (saveBtn && !document.getElementById('preview-php-btn')) {
        const previewBtn = document.createElement('button');
        previewBtn.type = 'button';
        previewBtn.id = 'preview-php-btn';
        previewBtn.className = 'btn btn-outline';
        previewBtn.textContent = 'Показать PHP';
        previewBtn.style.marginRight = '10px';

        previewBtn.addEventListener('click', () => {
            const formData = new FormData(form);
            const urlParams = new URLSearchParams(window.location.search);
            const domain = urlParams.get('domain') || '';
            const name = urlParams.get('name') || urlParams.get('block') || '';
            const allias = urlParams.get('allias') || '';

            // Определяем, откуда пришел запрос (Block или Filler)
            const isFiller = window.location.href.includes('filler');
            const action = isFiller ? 'filler:preview' : 'block:preview';

            fetch(`/admin/?p=${action}&domain=${domain}&name=${name}&allias=${allias}`, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                const w = window.open('', '_blank', 'width=900,height=700');
                w.document.write('<html><head><title>PHP Preview</title></head><body style="margin:0; background:#1e1e1e; color:#d4d4d4; font-family:monospace;">' + html + '</body></html>');
                w.document.close();
            });
        });

        saveBtn.parentNode.insertBefore(previewBtn, saveBtn);
    }
});