/**
 * CLIEngine v1.1
 * AJAX navigation and form interceptor for Damoony Engine.
 */
(function (window, document) {
    'use strict';

    const CLIEngine = {
        version: '1.1.0',

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

        handleResponse: function(data, form = null) {
            if (data.messages && data.messages.length > 0) {
                data.messages.forEach(msg => CLIEngine.UI.toast(msg.text, msg.type));
            }

            if (data.blocks) {
                for (const [name, html] of Object.entries(data.blocks)) {
                    const el = document.querySelector('.dt_' + name) || document.querySelector('[data-block="' + name + '"]');
                    if (el) {
                        el.innerHTML = html;
                    }
                }
            }

            if (data.form) {
                if (data.form.redirect) {
                    setTimeout(() => { window.location.href = data.form.redirect; }, data.form.redirect_time || 0);
                } else if (data.form.reset && form) {
                    form.reset();
                    if (data.form.keep && data.form.keep.length > 0) {
                        data.form.keep.forEach(name => {
                            const input = form.querySelector(`[name="${name}"]`);
                            if (input) {
                                if (input.type === 'checkbox' || input.type === 'radio') {
                                    input.checked = true;
                                } else {
                                    input.value = input.defaultValue;
                                }
                            }
                        });
                    }
                }
            }

            if (data.reload) {
                window.location.reload();
            }
        },

        init: function () {
            // AJAX Navigation
            document.addEventListener('click', async function (e) {
                const a = e.target.closest('a:not([data-no-ajax]):not([target="_blank"])');
                if (!a || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey || a.hasAttribute('download')) return;
                
                if (a.host !== window.location.host) return;
                const href = a.getAttribute('href');
                if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;

                e.preventDefault();

                try {
                    const res = await fetch(a.href, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const contentType = res.headers.get('content-type') || '';
                    if (!res.ok || !contentType.includes('application/json')) {
                        window.location.href = a.href;
                        return;
                    }
                    const data = await res.json();
                    CLIEngine.handleResponse(data);
                    
                    // If no redirect, update URL
                    if (!data.form || !data.form.redirect) {
                        history.pushState({}, '', a.href);
                    }
                } catch (err) {
                    window.location.href = a.href;
                }
            });

            // AJAX Forms
            document.addEventListener('submit', async function (e) {
                const form = e.target;
                if (form.hasAttribute('data-no-ajax') || form.method.toUpperCase() !== 'POST') return;
                
                e.preventDefault();
                const formData = new FormData(form);
                
                try {
                    const res = await fetch(form.action, {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        body: formData
                    });
                    const contentType = res.headers.get('content-type') || '';
                    if (!res.ok || !contentType.includes('application/json')) {
                        form.submit();
                        return;
                    }
                    const data = await res.json();
                    CLIEngine.handleResponse(data, form);
                } catch (err) {
                    form.submit();
                }
            });
        }
    };

    window.CLIEngine = CLIEngine;
    document.addEventListener('DOMContentLoaded', () => CLIEngine.init());

})(window, document);
