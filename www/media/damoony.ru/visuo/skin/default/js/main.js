document.addEventListener('DOMContentLoaded', () => {
    if (!document.getElementById('toast-container')) {
        const tc = document.createElement('div');
        tc.id = 'toast-container';
        document.body.appendChild(tc);
    }

    function showToast(html) {
        if (!html) return;
        const tc = document.getElementById('toast-container');
        const toast = document.createElement('div');
        toast.className = 'dt_toast';
        toast.innerHTML = html;
        tc.appendChild(toast);
        setTimeout(() => { toast.classList.add('show'); }, 10);
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    const flashScript = document.getElementById('flash-data');
    if (flashScript) {
        try {
            const msgs = JSON.parse(flashScript.textContent);
            msgs.forEach(m => showToast(m));
        } catch (e) {}
    }

    document.body.addEventListener('click', async (e) => {
        const link = e.target.closest('a');
        if (!link || link.host !== window.location.host || link.getAttribute('href').startsWith('#') || link.target === '_blank' || link.hasAttribute('data-no-ajax')) return;
        
        e.preventDefault();
        const url = link.href;
        
        try {
            const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json();
            
            if (data.ok && data.blocks) {
                for (const [name, html] of Object.entries(data.blocks)) {
                    // Ищем элемент по data-block, а не по классу
                    const container = document.querySelector('[data-block="' + name + '"]');
                    if (container) {
                        container.innerHTML = html;
                    }
                }
                window.history.pushState({}, '', url);
            } else {
                window.location.href = url;
            }
        } catch (err) {
            window.location.href = url;
        }
    });

    document.body.addEventListener('submit', async (e) => {
        const form = e.target.closest('form');
        if (!form || form.hasAttribute('data-no-ajax')) return;
        e.preventDefault();

        const formData = new FormData(form);
        try {
            const res = await fetch(form.action || window.location.href, {
                method: form.method || 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();

            if (data.error) {
                showToast(data.messages || 'Ошибка');
            } else {
                showToast(data.messages || 'Успешно');
                if (data.form && data.form.reset) form.reset();
                if (data.form && data.form.redirect) {
                    setTimeout(() => { window.location.href = data.form.redirect; }, data.form.redirect_time || 1000);
                }
                if (data.reload) {
                    setTimeout(() => window.location.reload(), 500);
                }
            }
        } catch (err) {
            form.submit();
        }
    });
});
