document.addEventListener('DOMContentLoaded', () => {
    let totalTime = 0;
    let totalQueries = 1; // 1 начальная загрузка страницы
    const benchmarkEl = document.getElementById('dt_benchmark');
    if (benchmarkEl) {
        totalTime = parseFloat(benchmarkEl.textContent.replace(/[^0-9.]/g, '')) || 0;
    }

    function updateBenchmark(ajaxTime) {
        if (!benchmarkEl) return;
        totalTime += ajaxTime;
        totalQueries++;
        benchmarkEl.innerHTML = '⚡ ' + ajaxTime + ' ms (' + totalQueries + ' req) <span style="opacity:0.6; margin-left:8px;">[Total: ' + totalTime.toFixed(1) + ' ms]</span>';
    }

    if (!document.getElementById('toast-container')) {
        const tc = document.createElement('div');
        tc.id = 'toast-container';
        document.body.appendChild(tc);
    }

    function showToast(html, type = 'info') {
        if (!html) return;
        const tc = document.getElementById('toast-container');
        const toast = document.createElement('div');
        toast.className = 'dt_toast ' + type;
        
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = html;
        toast.appendChild(tempDiv.firstElementChild || tempDiv);
        
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
            if (link.pathname === path) link.classList.add('active');
            else link.classList.remove('active');
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
                if (pushState) window.history.pushState({}, '', url);
                highlightMenu();
                if (data.debug_time) updateBenchmark(data.debug_time);
            } else {
                window.location.href = url;
            }
        } catch (err) {
            window.location.href = url;
        }
    }

    const flashScript = document.getElementById('flash-data');
    if (flashScript) {
        try { JSON.parse(flashScript.textContent).forEach(m => showToast(m, 'info')); } catch (e) {}
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

        const btn = form.querySelector('button[type="submit"]');
        if (btn && btn.disabled) return; // Защита от двойного клика
        if (btn) btn.disabled = true;

        const formData = new FormData(form);
        try {
            const res = await fetch(form.action || window.location.href, {
                method: form.method || 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();

            if (data.error) {
                showToast(data.messages || 'Ошибка', 'error');
            } else {
                showToast(data.messages || 'Успешно', 'success');
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
        } finally {
            if (btn) btn.disabled = false;
        }
    });
});
