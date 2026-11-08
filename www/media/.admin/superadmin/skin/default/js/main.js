// === Global Vars ===
const navEntries = performance.getEntriesByType('navigation');
let totalLoadTime = navEntries.length > 0 ? Math.round(navEntries[0].duration) : 0;
let lastAjaxTime = 0;

// === Debug Badge ===
function updateDebugBadgeText() {
    const badge = document.getElementById('debug-badge');
    if (badge) {
        const ajaxStr = lastAjaxTime > 0 ? ` (+${lastAjaxTime} ms)` : '';
        badge.innerText = `Total: ${totalLoadTime} ms${ajaxStr}`;
    }
}

// === Toast Notifications ===
window.showToast = function(type, text) {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        document.body.appendChild(container);
    }
    
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerText = text;
    
    container.appendChild(toast);
    
    setTimeout(() => toast.classList.add('show'), 10);
    
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 4000);
};

function checkFlashMessages(doc) {
    const flashData = doc.getElementById('flash-data');
    if (flashData) {
        try {
            const messages = JSON.parse(flashData.innerText);
            messages.forEach(msg => window.showToast(msg.type, msg.text));
            flashData.remove();
        } catch(e) { console.error('Flash parse error', e); }
    }
}

// === AJAX Navigation ===
async function loadPage(url, pushState = true) {
    const startLoad = performance.now();
    try {
        const wrapperEl = document.querySelector('.admin-wrapper');
        if (wrapperEl) wrapperEl.style.opacity = '0.5';
        
        const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        if (!response.ok) throw new Error('Network error');
        
        const html = await response.text();
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        
        const newWrapper = doc.querySelector('.admin-wrapper');
        if (newWrapper && wrapperEl) {
            wrapperEl.innerHTML = newWrapper.innerHTML;
            wrapperEl.style.opacity = '1';
        }
        
        const newHtmlLang = doc.documentElement.lang;
        if (newHtmlLang) document.documentElement.lang = newHtmlLang;
        
        const newTitle = doc.querySelector('title');
        if (newTitle) document.title = newTitle.innerText;
        
        if (pushState) window.history.pushState({}, '', url);
        
        checkFlashMessages(doc);
        updateDebugBadgeText();
        bindAiAssistant();
        
    } catch (error) {
        console.error('AJAX nav failed:', error);
        window.location.href = url;
    } finally {
        lastAjaxTime = Math.round(performance.now() - startLoad);
        totalLoadTime += lastAjaxTime;
        updateDebugBadgeText();
    }
}

async function loadPartial(url, targets, pushState = true) {
    const startLoad = performance.now();
    try {
        const elsToUpdate = targets.map(t => document.querySelector(`[data-slot="${t}"]`)).filter(Boolean);
        elsToUpdate.forEach(el => el.style.opacity = '0.3');
        
        const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
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
        
        if (pushState) window.history.pushState({}, '', url);
        
        document.querySelectorAll('.admin-menu a').forEach(a => {
            a.classList.remove('active');
            const aPath = new URL(a.href).pathname;
            const currentPath = new URL(url, window.location.origin).pathname;
            if (aPath === currentPath) a.classList.add('active');
        });
        
        checkFlashMessages(doc);
        
    } catch (error) {
        console.error('AJAX partial failed:', error);
        window.location.href = url;
    } finally {
        lastAjaxTime = Math.round(performance.now() - startLoad);
        totalLoadTime += lastAjaxTime;
        updateDebugBadgeText();
    }
}

// === AI Assistant ===
function bindAiAssistant() {
    const toggle = document.getElementById('ai-toggle');
    const close = document.getElementById('ai-close');
    const panel = document.querySelector('.ai-assistant');
    
    if (toggle && panel) {
        // Удаляем старые обработчики, чтобы не дублировались
        const newToggle = toggle.cloneNode(true);
        toggle.parentNode.replaceChild(newToggle, toggle);
        newToggle.addEventListener('click', () => panel.classList.add('open'));
    }
    
    if (close && panel) {
        const newClose = close.cloneNode(true);
        close.parentNode.replaceChild(newClose, close);
        newClose.addEventListener('click', () => panel.classList.remove('open'));
    }
}

// === Document Ready ===
document.addEventListener('DOMContentLoaded', () => {
    updateDebugBadgeText();
    checkFlashMessages(document);
    bindAiAssistant();
    
    // Event Delegation for AJAX links
    document.body.addEventListener('click', (e) => {
        const link = e.target.closest('a');
        if (!link || !link.href.includes('/admin/') || link.href.includes('logout') || link.getAttribute('target') === '_blank') return;
        
        e.preventDefault();
        const targetAttr = link.getAttribute('data-update');
        
        if (targetAttr === 'all') {
            loadPage(link.href, true);
        } else if (targetAttr) {
            loadPartial(link.href, targetAttr.split(','));
        } else {
            loadPartial(link.href, ['top', 'title', 'center', 'lang']);
        }
    });

    // Event Delegation for Toggles
    document.body.addEventListener('change', async (e) => {
        if (e.target.matches('.ui-toggle input[type="checkbox"]')) {
            const checkbox = e.target;
            const domain = checkbox.getAttribute('data-domain');
            const alias = checkbox.getAttribute('data-alias');
            const lang = checkbox.getAttribute('data-lang');
            const isChecked = checkbox.checked;
            
            checkbox.disabled = true;
            
            try {
                const formData = new URLSearchParams();
                formData.append('domain', domain);
                formData.append('active', isChecked ? 'true' : 'false');
                
                let url = `/admin/${lang}/domains/toggle`;
                if (alias) {
                    formData.append('alias', alias);
                    url = `/admin/${lang}/domain/toggle_alias`;
                }

                const response = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData.toString()
                });

                const text = await response.text();
                let result;
                try { result = JSON.parse(text); } 
                catch (err) { throw new Error('Server error: ' + text.substring(0, 100)); }
                
                if (!result.ok) throw new Error(result.error || 'Unknown error');
                
                const row = checkbox.closest('tr');
                if (row) {
                    row.style.backgroundColor = '#e6f9e6';
                    setTimeout(() => { row.style.backgroundColor = ''; }, 1000);
                }
            } catch (error) {
                console.error('Toggle failed:', error);
                checkbox.checked = !isChecked;
                alert('Ошибка: ' + error.message);
            } finally {
                checkbox.disabled = false;
            }
        }
    });

    window.addEventListener('popstate', () => loadPage(window.location.href, false));
});
