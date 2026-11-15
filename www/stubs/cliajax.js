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

    function applyMinStruct(data) {
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
            // Меняем CSS-ссылки
            document.querySelectorAll('link[rel="stylesheet"]').forEach(function(link) {
                var href = link.getAttribute('href');
                if (href && /\/skin\/[a-z0-9_]+\//.test(href)) {
                    link.setAttribute('href', href.replace(/\/skin\/[a-z0-9_]+\//, '/skin/' + data.skin_updated + '/'));
                }
            });
            // Подгружаем контент текущей страницы (без перезагрузки)
            if (data.redirect_url) {
                var currentPath = window.location.pathname.replace(/\/skin\/[a-z0-9_]+\//, '/');
                navigateTo(window.location.origin + (data.redirect_url || currentPath), false);
            }
            return true;
        }

        if (data.messages && data.messages.length > 0) {
            var toast = document.querySelector('[data-block="toast"]') || document.querySelector('.dt_toast');
            if (toast) {
                toast.innerHTML = '';
                // Поддержка и массива, и строки
                var messages = Array.isArray(data.messages) ? data.messages : [{ type: 'info', message: data.messages }];
                messages.forEach(function(msg) {
                    var div = document.createElement('div');
                    div.className = msg.type || 'info';
                    div.textContent = msg.message || msg;
                    toast.appendChild(div);
                });
                // Показываем ВСЕХ родителей (dt_toast / site-toast)
                var parent = toast.closest('.dt_toast');
                if (parent) parent.style.display = 'block';
                toast.style.display = 'block';
                setTimeout(function() {
                    if (parent) parent.style.display = 'none';
                    toast.style.display = 'none';
                }, 3000);
            }
        }
        if (data.form && data.form.redirect) {
            window.location.href = data.form.redirect;
            return true;
        }
        if (data.reload) {
            window.location.reload();
            return true;
        }
        return false;
    }

    function navigateTo(url, pushState) {
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function(res) {
            var ct = res.headers.get('Content-Type');
            if (!ct || ct.indexOf('application/json') === -1) { window.location.href = url; return null; }
            return res.json();
        })
        .then(function(data) {
            if (!data) return;
            var reloaded = applyMinStruct(data);
            if (pushState && !reloaded) {
                window.history.pushState({ url: url }, '', url);
            }
        })
        .catch(function() { window.location.href = url; });
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
            if (!ct || ct.indexOf('application/json') === -1) { form.submit(); return null; }
            return res.json();
        })
        .then(function(data) {
            if (!data) return;
            applyMinStruct(data);
            form.reset(); 
        })
        .catch(function() { form.submit(); });
    });

    // Кнопки назад/вперед (теперь без перезагрузки)
    window.addEventListener('popstate', function() {
        navigateTo(window.location.href, false);
    });
})();
