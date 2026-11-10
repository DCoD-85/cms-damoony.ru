<?php
return [
    'tag' => 'div',
    'class' => 'layout',
    'children' => [
        ['tag' => 'header', 'class' => 'header', 'slot' => 'top'],
        ['tag' => 'main', 'class' => 'main-content', 'slot' => 'center'],
        ['tag' => 'footer', 'class' => 'footer', 'slot' => 'footer'],
    ],
    'meta' => [
        'css' => [
            '/media/damoony.ru/skin/default/css/main.css',
            '/media/damoony.ru/skin/default/css/components.css'
        ]
    ]
];
