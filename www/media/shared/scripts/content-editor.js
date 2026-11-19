(function () {
    'use strict';

    function init() {
        var textarea = document.getElementById('content-editor');
        if (!textarea || !window.CodeMirror) {
            return;
        }

        var editor = CodeMirror.fromTextArea(textarea, {
            mode: { name: 'javascript', json: true },
            lineNumbers: true,
            lineWrapping: true,
            matchBrackets: true,
            autoCloseBrackets: true,
            indentUnit: 4,
            tabSize: 4,
            viewportMargin: Infinity,
            extraKeys: {
                'Ctrl-Space': 'autocomplete',
                'Cmd-Space': 'autocomplete'
            }
        });

        editor.setSize('100%', '520px');

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
