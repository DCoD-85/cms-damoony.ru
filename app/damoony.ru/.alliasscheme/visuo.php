<?php
return [
    'tag' => 'div',
    'class' => 'dt',
    'children' => [
        ['tag' => 'header', 'slot' => 'top'],
        [
            'tag' => 'div',
            'class' => 'dt_middle',
            'children' => [
                ['tag' => 'aside', 'class' => 'dt_sidebar', 'slot' => 'left'],
                ['tag' => 'main', 'slot' => 'center'],
                ['tag' => 'aside', 'class' => 'dt_sidebar', 'slot' => 'right'],
            ]
        ],
        ['tag' => 'div', 'slot' => 'bottom'],
        ['tag' => 'footer', 'slot' => 'footer'],
    ],
    'meta' => [
        'css' => [
            '/media/damoony.ru/visuo/skin/default/css/main.css',
            '/media/damoony.ru/visuo/skin/default/css/dt.css',
            '/media/damoony.ru/visuo/skin/default/css/components.css'
        ]
    ]
];
