/**
 * policy.js — cookie consent banner progressive enhancement.
 *
 * - Без JS: работает форма POST /{lang}/policy/accept + is-hidden от сервера.
 * - С JS: добавляет .enhanced (модалка), перехватывает submit,
 *   отправляет fetch POST на /{lang}/policy/ajax.
 * - "Закрыть" (btn-decline) скрывает баннер без cookie (запоминается в памяти JS).
 */
(function () {
    'use strict';

    var BANNER_ID = 'cookie-banner';
    var isClosedInSession = false;

    function getBanner() {
        return document.getElementById(BANNER_ID);
    }

    function hideBanner(banner) {
        if (banner) {
            banner.classList.add('is-hidden');
            banner.classList.remove('enhanced');
        }
    }

    function enhance(banner) {
        if (!banner || banner.classList.contains('is-hidden')) {
            return;
        }
        if (isClosedInSession) {
            banner.classList.add('is-hidden');
            return;
        }

        // 1. Добавляем .enhanced (модалка по центру)
        banner.classList.add('enhanced');

        // 2. Переносим на body (чтобы вырвать из контекста .dt_policy)
        if (banner.parentNode !== document.body) {
            document.body.appendChild(banner);
        }

        // 3. Перехват формы приёма
        var form = banner.querySelector('.cookie-accept-form');
        if (form && !form.hasAttribute('data-bound')) {
            form.setAttribute('data-bound', '1');
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var action = form.getAttribute('action') || '';
                var ajaxUrl = action.replace(/\/accept$/, '/ajax');

                var formData = new FormData(form);
                fetch(ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(function (res) {
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    return res.json();
                })
                .then(function (data) {
                    if (data.ok) {
                        hideBanner(banner);
                    } else {
                        alert('Ошибка сохранения. Пожалуйста, обновите страницу.');
                    }
                })
                .catch(function () {
                    alert('Ошибка сети. Пожалуйста, обновите страницу.');
                });
            });
        }

        // 4. Перехват кнопки "Закрыть" (без cookie)
        var closeBtn = banner.querySelector('.btn-decline');
        if (closeBtn && !closeBtn.hasAttribute('data-bound')) {
            closeBtn.setAttribute('data-bound', '1');
            closeBtn.addEventListener('click', function () {
                isClosedInSession = true;
                hideBanner(banner);
            });
        }
    }

    function init() {
        var banner = getBanner();
        if (banner) {
            enhance(banner);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Экспорт для cliajax.js (реинициализация после AJAX)
    window.__damoonyPolicy = { init: init };
})();
