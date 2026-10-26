<?php

return [
    'meta' => [
        'css' => [
            '/media/damoony.ru/skin/default/css/doc.css'
        ]
    ],
    'tag' => 'div',
    'class' => 'layout',
    'children' => [
        ['tag' => 'header', 'slot' => 'top'],
        ['tag' => 'main', 'slot' => 'center'],
        ['tag' => 'footer', 'slot' => 'bottom'],
    ],
];