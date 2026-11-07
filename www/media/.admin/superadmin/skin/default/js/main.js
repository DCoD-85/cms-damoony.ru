
// Инициализация накопленного времени
const navEntries = performance.getEntriesByType('navigation');
let totalLoadTime = navEntries.length > 0 ? Math.round(navEntries[0].duration) : 0;
let lastAjaxTime = 0;

function updateDebugBadge() {
    let badge = document.getElementById('debug-badge');
    if (!badge) {
        badge = document.createElement('div');
        badge.id = 'debug-badge';
        badge.style.cssText = 'position:fixed; bottom:12px; right:12px; background:rgba(29,29,31,0.85); color:#fff; padding:6px 12px; border-radius:6px; font-family:monospace; font-size:12px; z-index:99999; box-shadow:0 2px 10px rgba(0,0,0,0.2); pointer-events:none; backdrop-filter: blur(4px);';
        document.body.appendChild(badge);
    }
    const ajaxStr = lastAjaxTime > 0 ? ` (+${lastAjaxTime} ms)` : '';
    badge.innerHTML = `⚡ Всего: ${totalLoadTime} ms${ajaxStr}`;
}

document.addEventListener('DOMContentLoaded', () => {
    updateDebugBadge();

    async function loadPage(url, pushState = true) {
        const startLoad = performance.now();
        try {
            const wrapperEl = document.querySelector('.admin-wrapper');
            if (wrapperEl) wrapperEl.style.opacity = '0.5';
            
            const response = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            
            if (!response.ok) throw new Error('Network error');
            
            const html = await response.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            
            // 1. Меняем ВЕСЬ каркас (сайдбар + шапка + контент)
            const newWrapper = doc.querySelector('.admin-wrapper');
            if (newWrapper && wrapperEl) {
                wrapperEl.innerHTML = newWrapper.innerHTML;
                wrapperEl.style.opacity = '1';
            }
            
            // 2. Меняем атрибут lang у тега <html>
            const newHtmlLang = doc.documentElement.lang;
            if (newHtmlLang) {
                document.documentElement.lang = newHtmlLang;
            }
            
            // 3. Меняем title страницы
            const newTitle = doc.querySelector('title');
            if (newTitle) {
                document.title = newTitle.innerText;
            }
            
            if (pushState) {
                window.history.pushState({}, '', url);
            }
            
        } catch (error) {
            console.error('AJAX nav failed, fallback to full reload:', error);
            window.location.href = url;
            return;
        } finally {
            lastAjaxTime = Math.round(performance.now() - startLoad);
            totalLoadTime += lastAjaxTime;
            updateDebugBadge();
        }
    }

    async function loadPartial(url, targets, pushState = true) {
        const startLoad = performance.now();
        try {
            const elsToUpdate = targets.map(t => document.querySelector(`[data-slot="${t}"]`)).filter(Boolean);
            elsToUpdate.forEach(el => el.style.opacity = '0.3');
            
            const response = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            
            if (!response.ok) throw new Error('Network error');
            
            const html = await response.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            
            let updatedAny = false;
            targets.forEach(target => {
                const newEl = doc.querySelector(`[data-slot="${target}"]`);
                const oldEl = document.querySelector(`[data-slot="${target}"]`);
                if (newEl && oldEl) {
                    oldEl.innerHTML = newEl.innerHTML;
                    oldEl.style.opacity = '1';
                    updatedAny = true;
                }
            });
            
            if (!updatedAny) {
                loadPage(url, pushState);
                return;
            }
            
            if (pushState) {
                window.history.pushState({}, '', url);
            }
            
            // Обновляем активный класс в меню (так как мы не меняли сайдбар)
            document.querySelectorAll('.admin-menu a').forEach(a => {
                a.classList.remove('active');
                const aPath = new URL(a.href).pathname;
                const currentPath = new URL(url, window.location.origin).pathname;
                if (aPath === currentPath) a.classList.add('active');
            });
            
        } catch (error) {
            console.error('AJAX nav failed, fallback to full reload:', error);
            window.location.href = url;
            return;
        } finally {
            lastAjaxTime = Math.round(performance.now() - startLoad);
            totalLoadTime += lastAjaxTime;
            updateDebugBadge();
        }
    }

    document.body.addEventListener('click', (e) => {
        const link = e.target.closest('a');
        if (!link || !link.href.includes('/admin/') || link.href.includes('logout') || link.getAttribute('target') === '_blank') {
            return;
        }
        
        e.preventDefault();
        const targetAttr = link.getAttribute('data-update');
        
        if (targetAttr === 'all') {
            loadPage(link.href, true); // Теперь язык меняется через AJAX (полная замена каркаса)
        } else if (targetAttr) {
            loadPartial(link.href, targetAttr.split(','));
        } else {
            loadPartial(link.href, ['title', 'center']);
        }
    });

    window.addEventListener('popstate', () => {
        // При нажатии "Назад" в браузере делаем полную AJAX-загрузку
        loadPage(window.location.href, false);
    });
});
