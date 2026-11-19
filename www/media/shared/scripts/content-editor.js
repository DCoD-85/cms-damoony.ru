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

        var hints = parseHints(textarea);
        var isPhp = textarea.getAttribute('data-mode') === 'php';
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
