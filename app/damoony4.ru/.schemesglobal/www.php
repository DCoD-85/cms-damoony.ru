<?php
return [
    'tag' => 'div', 'class' => 'dt',
    'children' => [
        ['tag' => 'header', 'class' => 'dt_top', 'slot' => 'top'],
        ['tag' => 'aside', 'class' => 'dt_left', 'slot' => 'left'],
        ['tag' => 'main', 'class' => 'dt_center', 'slot' => 'center'],
        ['tag' => 'aside', 'class' => 'dt_right', 'slot' => 'right'],
        ['tag' => 'section', 'class' => 'dt_bottom', 'slot' => 'bottom'],
        ['tag' => 'footer', 'class' => 'dt_footer', 'slot' => 'footer'],
        ['tag' => 'div', 'class' => 'dt_policy', 'slot' => 'policy'],
        ['tag' => 'div', 'class' => 'dt_toast', 'slot' => 'toast'],
    ],
    'meta' => [
        'css' => [
            '/media/damoony4.ru/www/css/main.css',
            '/media/damoony4.ru/www/css/blocks.css',
            '/media/damoony4.ru/www/css/toast.css',
            '/media/damoony4.ru/www/css/components.css',
        ],
        'js' => ['/media/shared/scripts/cliajax.js'],
    ],
];