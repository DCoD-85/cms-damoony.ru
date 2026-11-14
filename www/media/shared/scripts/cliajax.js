(function() {
    'use strict';
    if (!window.fetch || !window.history || !window.history.pushState) return;

    // --- Benchmark Logic ---
    let ajaxCount = 0;
    let lastRequestTime = 0;
    let totalRequestTime = 0;

    function updateBenchmark() {
        var bm = document.getElementById('dt_benchmark');
        if (!bm) return;
        bm.textContent = '⚡ ' + Math.round(lastRequestTime) + ' ms / ' + Math.round(totalRequestTime) + ' ms / ' + ajaxCount;
    }

    document.addEventListener('DOMContentLoaded', updateBenchmark);

    var originalFetch = window.fetch;
    window.fetch = function() {
        var start = performance.now();
        return originalFetch.apply(this, arguments).then(function(response) {
            var end = performance.now();
            lastRequestTime = end - start;
            totalRequestTime += lastRequestTime;
            ajaxCount++;
            updateBenchmark();
            return response;
        }).catch(function(err) {
            var end = performance.now();
            lastRequestTime = end - start;
            totalRequestTime += lastRequestTime;
            ajaxCount++;
            updateBenchmark();
            throw err;
        });
    };
    // -----------------------

    function showToast(type, text, timeout) {
        var toast = document.querySelector('.dt_toast') || document.querySelector('.site-toast');
        if (!toast) return;
        var div = document.createElement('div');
        div.className = type || 'info';
        div.textContent = text;
        toast.appendChild(div);
        toast.style.display = 'block';
        var ttl = (typeof timeout === 'number') ? timeout : (type === 'error' ? 0 : 3000);
        if (ttl > 0) {
            setTimeout(function() {
                div.remove();
                if (!toast.children.length) toast.style.display = 'none';
            }, ttl);
        }
    }

    function handleNonJson(res) {
        var text = (res && res.status === 404) ? 'Страница не найдена'
                 : (res && res.status >= 500) ? 'Ошибка сервера'
                 : 'Не удалось загрузить данные';
        showToast('error', text, 0);
    }

    function applyMinStruct(data, form) {
        if (data.layout) {
            var currentLayout = document.querySelector('.dt') || document.querySelector('.layout');
            if (currentLayout) {
                currentLayout.outerHTML = data.layout;
                // Обновляем title из нового layout
                var tempDiv = document.createElement('div');
                tempDiv.innerHTML = data.layout;
                var newTitle = tempDiv.querySelector('title');
                if (newTitle) document.title = newTitle.textContent;
            }
        } else if (data.blocks) {
            for (var key in data.blocks) {
                if (data.blocks.hasOwnProperty(key)) {
                    var el = document.querySelector('[data-block="' + key + '"]');
                    if (el) el.innerHTML = data.blocks[key];
                }
            }
        }
        
        if (data.release) {
            var main = document.querySelector('[data-block="center"]') || document.querySelector('main') || document.body;
            if (main) main.innerHTML = data.release;
        }
        
        // Смена скина без перезагрузки
        if (data.skin_updated) {
            document.querySelectorAll('link[rel="stylesheet"]').forEach(function(link) {
                var href = link.getAttribute('href');
                if (href && /\/skin\/[a-z0-9_]+\//.test(href)) {
                    link.setAttribute('href', href.replace(/\/skin\/[a-z0-9_]+\//, '/skin/' + data.skin_updated + '/'));
                }
            });
            if (data.redirect_url) {
                navigateTo(window.location.origin + data.redirect_url, false);
            }
            return true;
        }

        if (data.messages && data.messages.length > 0) {
            var toast = document.querySelector('.dt_toast') || document.querySelector('.site-toast');
            if (toast) { toast.innerHTML = ''; toast.style.display = 'block'; }
            data.messages.forEach(function(msg) {
                showToast(msg.type || 'info', msg.text || msg.message || msg, msg.timeout);
            });
        }
        if (data.form && data.form.redirect) {
            var url = data.form.redirect;
            var delay = (typeof data.form.redirect_time === 'number') ? data.form.redirect_time : 0;
            if (delay > 0) {
                setTimeout(function() { navigateTo(url, true); }, delay);
            } else {
                navigateTo(url, true);
            }
            return true;
        }
        if (data.reload) {
            window.location.reload();
            return true;
        }
        if (form && data.form && data.form.reset) {
            form.reset();
        }
        return false;
    }

    function navigateTo(url, pushState) {
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function(res) {
            var ct = res.headers.get('Content-Type');
            if (!ct || ct.indexOf('application/json') === -1) { handleNonJson(res); return null; }
            return res.json();
        })
        .then(function(data) {
            if (!data) return;
            var reloaded = applyMinStruct(data);
            if (pushState && !reloaded) {
                window.history.pushState({ url: url }, '', url);
            }
        })
        .catch(function() { showToast('error', 'Нет связи с сервером', 0); });
    }

    // Перехват ссылок
    document.addEventListener('click', function(e) {
        var link = e.target.closest('a');
        if (!link || link.target === '_blank' || link.hasAttribute('data-no-ajax')) return;
        if (link.host !== window.location.host) return;

        e.preventDefault();
        navigateTo(link.href, true);
    });

    // Перехват форм
    document.addEventListener('submit', function(e) {
        var form = e.target;
        if (!form || form.tagName !== 'FORM' || form.hasAttribute('data-no-ajax')) return;
        if (form.method.toLowerCase() !== 'post') return;

        e.preventDefault(); 
        var formData = new FormData(form);
        
        fetch(form.action || window.location.href, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(res) {
            var ct = res.headers.get('Content-Type');
            if (!ct || ct.indexOf('application/json') === -1) { handleNonJson(res); return null; }
            return res.json();
        })
        .then(function(data) {
            if (!data) return;
            applyMinStruct(data, form);
        })
        .catch(function() { showToast('error', 'Нет связи с сервером', 0); });
    });

    // Кнопки назад/вперед (теперь без перезагрузки)
    window.addEventListener('popstate', function() {
        navigateTo(window.location.href, false);
    });
})();
