/**
 * policy.js — cookie consent banner (accept/decline).
 *
 * Логика:
 *   - Первый визит: баннер показан (fixed, снизу).
 *   - «Принять»: cookie damoony_policy=accepted (1 год), баннер скрыт навсегда.
 *   - «Отклонить»: баннер скрыт на текущей странице, без cookie;
 *     при перезагрузке / навигации — появится снова.
 *
 * .cookie-banner по умолчанию лежит внутри .dt_policy (display: none в main.css),
 * поэтому переносим на body — как .dt_toast в cliajax.js.
 *
 * Re-init после AJAX: cliajax.js вызывает window.__damoonyPolicy.init().
 */
(function () {
    'use strict';

    var COOKIE_NAME = 'damoony_policy';
    var COOKIE_VALUE = 'accepted';
    var COOKIE_MAX_AGE = 60 * 60 * 24 * 365; // 1 год
    var BANNER_SELECTOR = '.cookie-banner';

    function hasConsent() {
        return document.cookie.split(';').some(function (pair) {
            return pair.trim() === COOKIE_NAME + '=' + COOKIE_VALUE;
        });
    }

    function setConsent() {
        document.cookie = COOKIE_NAME + '=' + COOKIE_VALUE
            + '; max-age=' + COOKIE_MAX_AGE
            + '; path=/'
            + '; SameSite=Lax';
    }

    function init() {
        // Убираем body-attached banner от прошлой инициализации (AJAX-навигация).
        var orphans = document.querySelectorAll('body > ' + BANNER_SELECTOR);
        for (var i = 0; i < orphans.length; i++) {
            orphans[i].parentNode.removeChild(orphans[i]);
        }

        var banner = document.querySelector(BANNER_SELECTOR);
        if (!banner) return;

        // Согласие уже дано — держим скрытым (inline display:none в policy.php).
        if (hasConsent()) {
            banner.style.display = 'none';
            return;
        }

        // Переносим на body, чтобы .dt_policy { display: none } не скрывал баннер.
        if (banner.parentNode !== document.body) {
            document.body.appendChild(banner);
        }
        banner.style.display = 'flex';

        var accept = banner.querySelector('.btn-accept');
        var decline = banner.querySelector('.btn-decline');

        if (accept && !accept.hasAttribute('data-bound')) {
            accept.setAttribute('data-bound', '1');
            accept.addEventListener('click', function () {
                setConsent();
                banner.style.display = 'none';
            });
        }

        if (decline && !decline.hasAttribute('data-bound')) {
            decline.setAttribute('data-bound', '1');
            decline.addEventListener('click', function () {
                banner.style.display = 'none';
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Публичный API для re-init после AJAX (Step 5).
    window.__damoonyPolicy = { init: init };
})();
