<?php

return [
    'tag' => 'div',
    'class' => 'layout-dev',
    'children' => [
        ['tag' => 'header', 'slot' => 'top', 'attrs' => ['style' => 'background:#1f2937; color:white; padding:10px;']],
        [
            'tag' => 'div', 'class' => 'middle-row', 'attrs' => ['style' => 'display:flex; gap:20px; padding:20px;'],
            'children' => [
                ['tag' => 'aside', 'slot' => 'left', 'attrs' => ['style' => 'width:200px; background:#e5e7eb; padding:10px;']],
                ['tag' => 'main', 'slot' => 'center', 'attrs' => ['style' => 'flex-grow:1;']],
                ['tag' => 'aside', 'slot' => 'right', 'attrs' => ['style' => 'width:200px; background:#e5e7eb; padding:10px;']],
            ]
        ],
        ['tag' => 'footer', 'slot' => 'bottom', 'attrs' => ['style' => 'background:#374151; color:white; padding:10px; text-align:center;']],
    ],
    'meta' => [
        'css' => [
            '/media/damoony.ru/development/skin/default/css/engine.css'
        ]
    ]
];
