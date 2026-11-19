<?php

declare(strict_types=1);

$children = [
    ['tag' => 'header', 'class' => 'admin_top', 'slot' => 'top'],
    ['tag' => 'aside', 'class' => 'admin_left', 'slot' => 'left'],
    ['tag' => 'main', 'class' => 'admin_center', 'slot' => 'center'],
    ['tag' => 'footer', 'class' => 'admin_footer', 'slot' => 'footer'],
];

return [
    'tag' => 'div',
    'class' => 'dt admin-shell',
    'children' => $children,
    'meta' => [
        'css' => [
            '/media/shared/css/admin.css',
            '/media/shared/vendor/codemirror/codemirror.min.css',
            '/media/shared/vendor/codemirror/addon/hint/show-hint.min.css',
        ],
        'js' => [
            '/media/shared/scripts/cliajax.js?v=2',
            '/media/shared/vendor/codemirror/codemirror.min.js',
            '/media/shared/vendor/codemirror/mode/javascript/javascript.min.js',
            '/media/shared/vendor/codemirror/mode/xml/xml.min.js',
            '/media/shared/vendor/codemirror/mode/clike/clike.min.js',
            '/media/shared/vendor/codemirror/mode/php/php.min.js',
            '/media/shared/vendor/codemirror/addon/edit/matchbrackets.min.js',
            '/media/shared/vendor/codemirror/addon/hint/show-hint.min.js',
            '/media/shared/scripts/content-editor.js',
        ],
    ],
];
