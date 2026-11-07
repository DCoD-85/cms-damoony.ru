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
                ['tag' => 'div', 'slot' => 'menu', 'attrs' => ['data-slot' => 'menu']],
            ]
        ],
        [
            'tag' => 'div',
            'class' => 'admin-main',
            'children' => [
                [
                    'tag' => 'div', 
                    'class' => 'admin-header d-flex justify-content-between align-items-center', 
                    'children' => [
                        ['tag' => 'div', 'slot' => 'title', 'attrs' => ['data-slot' => 'title']],
                        ['tag' => 'div', 'slot' => 'lang', 'attrs' => ['data-slot' => 'lang']]
                    ]
                ],
                ['tag' => 'div', 'class' => 'admin-content', 'slot' => 'center', 'attrs' => ['data-slot' => 'center']],
            ]
        ]
    ],
    'scripts' => [],
    'meta' => [
        'css' => ['/media/.admin/superadmin/skin/default/css/main.css'],
        'js' => ['/media/.admin/superadmin/skin/default/js/main.js'],
    ]
];
