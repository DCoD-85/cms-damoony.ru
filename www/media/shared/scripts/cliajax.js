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

    function ensureToastContainer() {
        var toast = document.querySelector('.dt_toast');
        if (toast && toast.parentNode !== document.body) {
            document.body.appendChild(toast);
        }
    }

    function ensureToastContainer() {
        var toast = document.querySelector('.dt_toast');
        if (toast && toast.parentNode !== document.body) {
            document.body.appendChild(toast);
        }
    }

    function showToast(type, text, timeout) {
        ensureToastContainer();
        var toast = document.querySelector('.dt_toast');
        if (!toast) return;
        
        var item = document.createElement('div');
        item.className = 'toast-item ' + (type || 'info');
        item.textContent = text;
        toast.appendChild(item);
        
        requestAnimationFrame(function() {
            requestAnimationFrame(function() {
                item.classList.add('show');
            });
        });

        // Ошибки живут 5 секунд, остальные 3 секунды
        var ttl = (typeof timeout === 'number') ? timeout : (type === 'error' ? 5000 : 3000);
        
        function removeToast() {
            item.classList.remove('show');
            setTimeout(function() {
                item.remove();
            }, 300);
        }

        if (ttl > 0) {
            setTimeout(removeToast, ttl);
        }
        
        item.addEventListener('click', removeToast);
    }

    function applyMinStruct(data) {
        // 1. Сохраняем текущие тосты перед заменой layout
        var oldToastContainer = document.querySelector('.dt_toast');
        
        if (data.layout) {
            var currentLayout = document.querySelector('.dt') || document.querySelector('.layout');
            if (currentLayout) {
                currentLayout.outerHTML = data.layout;
                
                // Находим новый контейнер тостов (он пришел в новом layout)
                var newToastContainer = document.querySelector('.dt_toast');
                
                // Переносим старые тосты в новый контейнер (если они были)
                if (oldToastContainer && newToastContainer) {
                    while (oldToastContainer.firstChild) {
                        newToastContainer.appendChild(oldToastContainer.firstChild);
                    }
                    oldToastContainer.remove();
                }
                
                // Переносим новый контейнер на body, чтобы он выживал при следующих переходах
                if (newToastContainer && newToastContainer.parentNode !== document.body) {
                    document.body.appendChild(newToastContainer);
                }

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
        
        // 2. Смена скина
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

        // 3. Показ сообщений через showToast (с накоплением)
        if (data.messages && data.messages.length > 0) {
            data.messages.forEach(function(msg) {
                showToast(msg.type || 'info', msg.text || msg.message || msg);
            });
        }

        // 4. Редирект (без перезагрузки)
        if (data.form && data.form.redirect) {
            var delay = data.form.redirect_time || 0;
            if (delay > 0) {
                setTimeout(function() { navigateTo(data.form.redirect, true); }, delay);
            } else {
                navigateTo(data.form.redirect, true);
            }
            return true;
        }
        if (data.reload) {
            window.location.reload();
            return true;
        }

        // M7: re-init cookie-banner after AJAX content replacement (layout/blocks/release)
        if (window.__damoonyPolicy && typeof window.__damoonyPolicy.init === 'function') {
            window.__damoonyPolicy.init();
        }

        return false;
    }

    function navigateTo(url, pushState) {
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function(res) {
            if (res.status === 404) {
                showToast('error', 'Страница не найдена', 0);
                return null;
            }
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
        
        fetch(form.getAttribute('action') || window.location.href, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(res) {
            if (res.status === 404) { showToast('error', 'Страница не найдена', 0); return null; }
            var ct = res.headers.get('Content-Type');
            if (!ct || ct.indexOf('application/json') === -1) { 
                if (res.status >= 500) { showToast('error', 'Ошибка сервера', 0); return null; }
                form.submit(); 
                return null; 
            }
            return res.json();
        })
        .then(function(data) {
            if (!data) return;
            var reloaded = applyMinStruct(data);
            if (!reloaded && (!data.form || !data.form.reset)) {
                // Не сбрасываем форму, если не было явной команды
            } else if (data.form && data.form.reset) {
                form.reset();
            }
        })
        .catch(function() { form.submit(); });
    });

    // Кнопки назад/вперед (теперь без перезагрузки)
    window.addEventListener('popstate', function() {
        navigateTo(window.location.href, false);
    });
})();
