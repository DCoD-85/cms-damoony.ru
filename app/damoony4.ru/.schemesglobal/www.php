<?php

return [
    'tag' => 'div',
    'class' => 'layout',
    'children' => [
        ['tag' => 'header', 'slot' => 'top'],
        ['tag' => 'aside', 'slot' => 'left'],
        ['tag' => 'main', 'slot' => 'center'],
        ['tag' => 'aside', 'slot' => 'right'],
        ['tag' => 'footer', 'slot' => 'footer'],
        ['tag' => 'section', 'slot' => 'bottom'],
        ['tag' => 'div', 'slot' => 'policy'],
        ['tag' => 'div', 'slot' => 'toast'],
    ],
    'meta' => [
        'css' => ['/media/damoony4.ru/www/skin/{skin}/css/main.css'],
        'js' => ['/media/damoony4.ru/www/skin/{skin}/js/main.js', '/media/shared/scripts/cliajax.js'],
    ],
];
