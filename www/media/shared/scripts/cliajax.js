(function() {
    'use strict';

    function isSupported() {
        return window.fetch && window.history && window.history.pushState;
    }

    if (!isSupported()) return;

    function applyMinStruct(data) {
        if (data.release) {
            var main = document.querySelector('[data-block="center"]') || document.querySelector('main') || document.body;
            if (main) main.innerHTML = data.release;
        }
        if (data.messages) {
            var toast = document.getElementById('toast-container') || document.querySelector('.site-toast');
            if (toast) {
                toast.innerHTML = data.messages;
                toast.style.display = 'block';
                setTimeout(function() { toast.style.display = 'none'; }, 3000);
            }
        }
        if (data.reload) {
            window.location.reload();
            return true;
        }
        return false;
    }

    document.addEventListener('click', function(e) {
        var link = e.target.closest('a');
        if (!link || link.target === '_blank' || link.hasAttribute('data-no-ajax')) return;
        if (link.host !== window.location.host) return;

        e.preventDefault();
        fetch(link.href, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(res) {
            var ct = res.headers.get('Content-Type');
            if (!ct || ct.indexOf('application/json') === -1) {
                window.location.href = link.href;
                return null;
            }
            return res.json();
        })
        .then(function(data) {
            if (!data) return;
            var reloaded = applyMinStruct(data);
            if (!reloaded) {
                window.history.pushState({}, '', link.href);
            }
        })
        .catch(function(err) {
            console.error('cliajax error', err);
            window.location.href = link.href;
        });
    });

    window.addEventListener('popstate', function() {
        window.location.reload();
    });
})();
