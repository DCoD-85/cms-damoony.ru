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
        'css' => array_merge(['/media/damoony4.ru/www/css/main.css', '/media/damoony4.ru/www/skin/{skin}/css/main.css'], array (
  0 => '/media/damoony4.ru/www/css/top.css',
  1 => '/media/damoony4.ru/www/css/left.css',
  2 => '/media/damoony4.ru/www/css/center.css',
  3 => '/media/damoony4.ru/www/css/right.css',
  4 => '/media/damoony4.ru/www/css/bottom.css',
  5 => '/media/damoony4.ru/www/css/footer.css',
  6 => '/media/damoony4.ru/www/css/policy.css',
  7 => '/media/damoony4.ru/www/css/toast.css',
)),
        'js' => ['/media/damoony4.ru/www/skin/{skin}/js/main.js', '/media/shared/scripts/cliajax.js'],
    ],
];
