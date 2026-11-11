<?php

declare(strict_types=1);

return [
    'tag' => 'div',
    'class' => 'admin-wrapper',
    'children' => [
        ['tag' => 'div', 'class' => 'admin-top', 'slot' => 'top'],
        [
            'tag' => 'div',
            'class' => 'admin-middle',
            'children' => [
                ['tag' => 'div', 'class' => 'admin-left', 'slot' => 'left'],
                ['tag' => 'div', 'class' => 'admin-center', 'slot' => 'center'],
                ['tag' => 'div', 'class' => 'admin-right', 'slot' => 'right'],
            ],
        ],
        ['tag' => 'div', 'class' => 'admin-bottom', 'slot' => 'bottom'],
        ['tag' => 'div', 'class' => 'admin-policy', 'slot' => 'policy'],
        ['tag' => 'div', 'class' => 'admin-toast', 'slot' => 'toast'],
    ],
    'scripts' => [],
    'meta' => [
        'css' => ['/media/.admin/superadmin/skin/{skin}/css/main.css', '/media/.admin/superadmin/skin/{skin}/css/components.css'],
        'js' => ['/media/.admin/superadmin/skin/{skin}/js/main.js', '/media/shared/scripts/cliajax.js'],
    ],
];
