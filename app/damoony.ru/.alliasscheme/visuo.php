<?php
return [
    'tag' => 'div',
    'class' => 'dt',
    'children' => [
        ['tag' => 'header', 'class' => 'dt_header', 'slot' => 'top'],
        [
            'tag' => 'div',
            'class' => 'dt_middle',
            'children' => [
                ['tag' => 'aside', 'class' => 'dt_sidebar_left', 'slot' => 'left'],
                ['tag' => 'main', 'class' => 'dt_main', 'slot' => 'center'],
                ['tag' => 'aside', 'class' => 'dt_sidebar_right', 'slot' => 'right'],
            ]
        ],
        ['tag' => 'div', 'class' => 'dt_bottom_section', 'slot' => 'bottom'],
        ['tag' => 'footer', 'class' => 'dt_footer', 'slot' => 'footer'],
    ],
    'meta' => [
        'css' => [
            '/media/damoony.ru/visuo/skin/default/css/main.css',
            '/media/damoony.ru/visuo/skin/default/css/dt.css',
            '/media/damoony.ru/visuo/skin/default/css/components.css'
        ]
    ]
];
