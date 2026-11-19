(function () {
    'use strict';

    function parseHints(textarea) {
        try {
            var raw = textarea.getAttribute('data-hints') || '{}';
            return JSON.parse(raw);
        } catch (e) {
            return {};
        }
    }

    function init() {
        var textarea = document.getElementById('content-editor');
        if (!textarea || !window.CodeMirror) {
            return;
        }

        var hints = parseHints(textarea);        var isPhp = textarea.getAttribute('data-mode') === 'php';
        var mode = isPhp
            ? 'application/x-httpd-php'
            : { name: 'javascript', json: true };

        var editor = CodeMirror.fromTextArea(textarea, {
            mode: mode,
            lineNumbers: true,
            lineWrapping: true,
            matchBrackets: true,
            autoCloseBrackets: true,
            indentUnit: 4,
            tabSize: 4,
            viewportMargin: Infinity
        });

        function suggest(cm) {
            var cur = cm.getCursor();
            var token = cm.getTokenAt(cur);
            var word = token.string.replace(/^[^a-zA-Z0-9_]+|[^a-zA-Z0-9_]+$/g, '');
            var list = [];
            var seen = {};

            function add(text, display) {
                if (!text || seen[text]) {
                    return;
                }
                seen[text] = true;
                list.push({ text: text, displayText: display || text });
            }

            if (isPhp) {
                (hints.methods || []).forEach(function (method) {
                    add(method.name, method.provider + '::' + method.name + '(' + (method.params || []).join(', ') + ')');
                });
            } else {
                (hints.types || []).forEach(function (type) {
                    add('"' + type + '"', 'type: ' + type);
                });
                (hints.fields || []).forEach(function (field) {
                    add('"' + field + '"', 'field: ' + field);
                });
                (hints.methods || []).forEach(function (method) {
                    add('"' + method.name + '"', 'method: ' + method.name);
                });
            }

            if (!list.length) {
                return null;
            }

            return {
                list: list,
                from: CodeMirror.Pos(cur.line, token.start),
                to: CodeMirror.Pos(cur.line, cur.ch)
            };
        }

        function showSuggestions(cm) {
            cm.showHint({ hint: suggest, completeSingle: false });
        }

        editor.setSize('100%', '520px');
        editor.on('inputRead', function (cm, change) {
            var text = change.text && change.text[0] ? change.text[0] : '';
            if (text === '"' || text === ':' || text === '.') {
                setTimeout(function () { showSuggestions(cm); }, 80);
            }
        });

        if (editor.setOption) {
            editor.setOption('extraKeys', {
                'Ctrl-Space': showSuggestions,
                'Cmd-Space': showSuggestions
            });
        }

        var templates = {
            h1: { type: 'h1', content: 'Заголовок' },
            h2: { type: 'h2', content: 'Заголовок' },
            h3: { type: 'h3', content: 'Заголовок' },
            text: { type: 'text', content: 'Текст' },
            p: { type: 'text', content: 'Текст' },
            img: { type: 'img', content: '/media/example.jpg', alt: 'Image' },
            link: { type: 'link', url: '/ru/about/', content: 'Ссылка' },
            button: { type: 'button', content: 'Кнопка', url: '/ru/about/', class: 'btn' },
            ul: { type: 'ul', items: ['Пункт 1', 'Пункт 2'] },
            nav: { type: 'nav', items: [{ url: '/ru/', title: 'Главная' }] },
            section: { type: 'section', children: [] },
            div: { type: 'div', children: [] },
            table: { type: 'table', headers: ['Колонка'], rows: [['Значение']] }
        };

        function insertBlock() {
            var select = document.getElementById('content-insert-block');
            if (!select) {
                return;
            }
            var template = templates[select.value];
            if (!template) {
                return;
            }
            editor.replaceSelection(JSON.stringify(template, null, 2));
            editor.focus();
        }

        function insertMethod() {
            var select = document.getElementById('content-insert-method');
            if (!select) {
                return;
            }
            editor.replaceSelection(select.value + '(');
            editor.focus();
        }

        var blockButton = document.querySelector('[data-insert-block]');
        if (blockButton) {
            blockButton.addEventListener('click', insertBlock);
        }
        var methodButton = document.querySelector('[data-insert-method]');
        if (methodButton) {
            methodButton.addEventListener('click', insertMethod);
        }

        document.querySelectorAll('[data-media-url]').forEach(function (button) {
            button.addEventListener('click', function () {
                var url = button.getAttribute('data-media-url') || '';
                editor.replaceSelection(isPhp ? "'" + url + "'" : JSON.stringify(url));
                editor.focus();
            });
        });

        var form = textarea.form;
        if (form) {
            form.addEventListener('submit', function () {
                textarea.value = editor.getValue();
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
