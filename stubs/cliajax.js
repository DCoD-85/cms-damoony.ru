(function() {
    'use strict';
    if (!window.fetch || !window.history || !window.history.pushState) return;

    function applyMinStruct(data) {
        if (data.blocks) {
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
        if (data.messages) {
            var toast = document.querySelector('[data-block="toast"]') || document.querySelector('.site-toast');
            if (toast) {
                toast.innerHTML = data.messages;
                toast.style.display = 'block';
                setTimeout(function() { toast.style.display = 'none'; }, 3000);
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

    // Перехват ссылок
    document.addEventListener('click', function(e) {
        var link = e.target.closest('a');
        if (!link || link.target === '_blank' || link.hasAttribute('data-no-ajax')) return;
        if (link.host !== window.location.host) return;

        e.preventDefault();
        fetch(link.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function(res) {
            var ct = res.headers.get('Content-Type');
            if (!ct || ct.indexOf('application/json') === -1) { window.location.href = link.href; return null; }
            return res.json();
        })
        .then(function(data) {
            if (!data) return;
            var reloaded = applyMinStruct(data);
            if (!reloaded) window.history.pushState({}, '', link.href);
        })
        .catch(function() { window.location.href = link.href; });
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
        })
        .catch(function() { form.submit(); });
    });

    window.addEventListener('popstate', function() { window.location.reload(); });
})();
