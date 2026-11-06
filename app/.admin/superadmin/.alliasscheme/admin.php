<?php

return [
    'tag' => 'div',
    'class' => 'admin-wrapper',
    'children' => [
        [
            'tag' => 'div',
            'class' => 'admin-sidebar',
            'children' => [
                ['tag' => 'div', 'class' => 'admin-logo', 'content' => 'Damoony Admin'],
                ['tag' => 'div', 'slot' => 'menu'],
            ]
        ],
        [
            'tag' => 'div',
            'class' => 'admin-main',
            'children' => [
                ['tag' => 'div', 'class' => 'admin-header', 'slot' => 'title'],
                ['tag' => 'div', 'class' => 'admin-content', 'slot' => 'center'],
            ]
        ]
    ],
    'scripts' => [],
    'meta' => [
        'css' => ['/media/.admin/superadmin/skin/default/css/main.css'],
        'js' => ['/media/.admin/superadmin/skin/default/js/main.js'],
    ]
];
