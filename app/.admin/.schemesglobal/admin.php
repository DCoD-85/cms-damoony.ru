<?php

declare(strict_types=1);

$children = [
    ['tag' => 'header', 'class' => 'admin_top', 'slot' => 'top'],
    ['tag' => 'aside', 'class' => 'admin_left', 'slot' => 'left'],
    ['tag' => 'main', 'class' => 'admin_center', 'slot' => 'center'],
    ['tag' => 'footer', 'class' => 'admin_footer', 'slot' => 'footer'],
];

return [
    'tag' => 'div',
    'class' => 'dt admin-shell',
    'children' => $children,
    'meta' => [
        'css' => [
            '/media/shared/css/admin.css',
        ],
        'js' => [
            '/media/shared/scripts/cliajax.js?v=2',
        ],
    ],
];
