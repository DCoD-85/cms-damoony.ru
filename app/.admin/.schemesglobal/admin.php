<?php

declare(strict_types=1);

$children = [
    ['tag' => 'header', 'class' => 'admin_top', 'slot' => 'top'],
    ['tag' => 'main', 'class' => 'admin_center', 'slot' => 'center'],
    ['tag' => 'footer', 'class' => 'admin_footer', 'slot' => 'footer'],
];

return [
    'tag' => 'div',
    'class' => 'admin-shell',
    'children' => $children,
    'meta' => [
        'css' => [
            '/media/shared/css/admin.css',
        ],
        'js' => [],
    ],
];
