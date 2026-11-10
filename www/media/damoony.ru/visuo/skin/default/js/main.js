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

    function highlightMenu() {
        const path = window.location.pathname;
        document.querySelectorAll('.dt_top .menu a').forEach(link => {
            if (link.pathname === path) {
                link.classList.add('active');
            } else {
                link.classList.remove('active');
            }
        });
    }

    async function loadPage(url, pushState = true) {
        try {
            const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json();
            
            if (data.ok && data.blocks) {
                for (const [name, html] of Object.entries(data.blocks)) {
                    const container = document.querySelector('[data-block="' + name + '"]');
                    if (container) container.innerHTML = html;
                }
                if (pushState) {
                    window.history.pushState({}, '', url);
                }
                highlightMenu();
            } else {
                window.location.href = url;
            }
        } catch (err) {
            window.location.href = url;
        }
    }

    // Инициализация при загрузке
    const flashScript = document.getElementById('flash-data');
    if (flashScript) {
        try { JSON.parse(flashScript.textContent).forEach(m => showToast(m)); } catch (e) {}
    }
    highlightMenu();

    document.body.addEventListener('click', (e) => {
        const link = e.target.closest('a');
        if (!link || link.host !== window.location.host || link.getAttribute('href').startsWith('#') || link.target === '_blank' || link.hasAttribute('data-no-ajax')) return;
        
        e.preventDefault();
        loadPage(link.href, true);
    });

    window.addEventListener('popstate', () => {
        loadPage(window.location.href, false);
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
                    setTimeout(() => { loadPage(data.form.redirect, true); }, data.form.redirect_time || 1000);
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
