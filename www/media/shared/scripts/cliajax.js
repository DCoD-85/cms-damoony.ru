/**
 * CLIEngine v1.0
 * Self-contained, secure JS engine for Damoony Admin.
 * No external dependencies.
 */
(function (window, document) {
    'use strict';

    const CLIEngine = {
        version: '1.0.0',
        plugins: {},

        /**
         * Registry for external libraries or custom widgets.
         */
        register: function (name, initCallback) {
            this.plugins[name] = initCallback;
            try {
                initCallback(this); // init immediately
            } catch (e) {
                console.error('Plugin init failed: ' + name, e);
            }
        },

        Core: {
            /**
             * Secure fetch wrapper.
             * @param {string} url
             * @param {object} options
             */
            request: async function (url, options = {}) {
                const headers = options.headers || {};
                headers['X-Requested-With'] = 'XMLHttpRequest';

                const config = {
                    method: options.method || 'GET',
                    headers: headers,
                    body: options.body || null
                };

                try {
                    const res = await fetch(url, config);
                    const contentType = res.headers.get('content-type');
                    
                    if (contentType && contentType.includes('application/json')) {
                        const data = await res.json();
                        if (!res.ok) throw new Error(data.message || 'РћС€РёР±РєР° СЃРµСЂРІРµСЂР°');
                        return data;
                    } else {
                        throw new Error('РЎРµСЂРІРµСЂ РІРµСЂРЅСѓР» HTML РІРјРµСЃС‚Рѕ JSON. РџСЂРѕРІРµСЂСЊС‚Рµ AJAX РІ PHP.');
                    }
                } catch (err) {
                    CLIEngine.UI.toast('РћС€РёР±РєР° СЃРµС‚Рё: ' + err.message, 'error');
                    throw err;
                }
            },
            
            post: function(url, formData) {
                return this.request(url, { method: 'POST', body: formData });
            }
        },

        UI: {
            _injectStyles: function() {
                if (document.getElementById('cliengine-styles')) return;
                const style = document.createElement('style');
                style.id = 'cliengine-styles';
                style.innerHTML = `
                    #toast-container { position: fixed; top: 20px; right: 20px; z-index: 999999; display: flex; flex-direction: column; gap: 10px; }
                    .toast { padding: 12px 24px; border-radius: 6px; color: #fff; font-family: sans-serif; font-size: 14px; box-shadow: 0 4px 12px rgba(0,0,0,0.2); opacity: 1; transition: opacity 0.5s; }
                    .toast-success { background-color: #10b981; }
                    .toast-error { background-color: #ef4444; }
                    .toast-warning { background-color: #f59e0b; }
                    .toast-info { background-color: #3b82f6; }
                `;
                document.head.appendChild(style);
            },
            
            toast: function (message, type = 'info') {
                this._injectStyles();
                let container = document.getElementById('toast-container');
                if (!container) {
                    container = document.createElement('div');
                    container.id = 'toast-container';
                    document.body.appendChild(container);
                }

                const toast = document.createElement('div');
                toast.className = 'toast toast-' + type;
                toast.textContent = message;
                container.appendChild(toast);

                setTimeout(() => {
                    toast.style.opacity = '0';
                    setTimeout(() => toast.remove(), 500);
                }, 3000);
            }
        },

        init: function () {
            // Global form interceptor for forms with class="ajax-form"
            document.addEventListener('submit', async function (e) {
                const form = e.target;
                if (!form.classList.contains('ajax-form')) return;
                
                e.preventDefault();
                const formData = new FormData(form);
                
                try {
                    const data = await CLIEngine.Core.post(form.action, formData);
                    if (data.ok) {
                        CLIEngine.UI.toast(data.message, 'success');
                        if (data.redirect) {
                            setTimeout(() => {
                                // Simple SPA navigation fallback
                                window.location.href = data.redirect;
                            }, 1000);
                        }
                    } else {
                        CLIEngine.UI.toast(data.message || 'РќРµРёР·РІРµСЃС‚РЅР°СЏ РѕС€РёР±РєР°', 'error');
                    }
                } catch (err) {
                    // Error is already toasted by Core.request
                }
            });
        }
    };

    window.CLIEngine = CLIEngine;
    document.addEventListener('DOMContentLoaded', () => CLIEngine.init());

})(window, document);
