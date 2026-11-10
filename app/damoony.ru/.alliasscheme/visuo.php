<?php
return [
    'tag' => 'div',
    'class' => 'layout',
    'children' => [
        ['tag' => 'header', 'class' => 'header', 'slot' => 'top'],
        [
            'tag' => 'div',
            'class' => 'middle-wrapper',
            'children' => [
                ['tag' => 'aside', 'class' => 'sidebar-left', 'slot' => 'left'],
                ['tag' => 'main', 'class' => 'main-content', 'slot' => 'center'],
                ['tag' => 'aside', 'class' => 'sidebar-right', 'slot' => 'right'],
            ]
        ],
        ['tag' => 'div', 'class' => 'bottom-section', 'slot' => 'bottom'],
        ['tag' => 'footer', 'class' => 'footer', 'slot' => 'footer'],
    ],
    'meta' => [
        'css' => [
            '/media/damoony.ru/skin/default/css/main.css',
            '/media/damoony.ru/skin/default/css/components.css'
        ]
    ]
];
