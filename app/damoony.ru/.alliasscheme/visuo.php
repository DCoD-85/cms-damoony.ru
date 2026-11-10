<?php
return [
    'tag' => 'div',
    'class' => 'dt',
    'children' => [
        ['tag' => 'header', 'slot' => 'top', 'attrs' => ['data-block' => 'top']],
        [
            'tag' => 'div',
            'class' => 'dt_middle',
            'children' => [
                ['tag' => 'aside', 'class' => 'dt_sidebar', 'slot' => 'left', 'attrs' => ['data-block' => 'left']],
                ['tag' => 'main', 'slot' => 'center', 'attrs' => ['data-block' => 'center']],
                ['tag' => 'aside', 'class' => 'dt_sidebar', 'slot' => 'right', 'attrs' => ['data-block' => 'right']],
            ]
        ],
        ['tag' => 'div', 'slot' => 'bottom', 'attrs' => ['data-block' => 'bottom']],
        ['tag' => 'footer', 'slot' => 'footer', 'attrs' => ['data-block' => 'footer']],
    ],
    'meta' => [
        'css' => [
            '/media/damoony.ru/visuo/skin/default/css/main.css',
            '/media/damoony.ru/visuo/skin/default/css/dt.css',
            '/media/damoony.ru/visuo/skin/default/css/components.css',
            '/media/damoony.ru/visuo/skin/default/css/toast.css',
            '/media/damoony.ru/visuo/skin/default/css/mobile.css'
        ],
        'js' => [
            '/media/damoony.ru/visuo/skin/default/js/main.js'
        ]
    ]
];
