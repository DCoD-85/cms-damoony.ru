<?php

declare(strict_types=1);

return [
    'tag' => 'div',
    'class' => 'dt_wrapper',
    'children' => [
        ['tag' => 'div', 'class' => 'dt_top', 'slot' => 'top'],
        [
            'tag' => 'div',
            'class' => 'dt_middle',
            'children' => [
                ['tag' => 'div', 'class' => 'dt_left', 'slot' => 'left'],
                ['tag' => 'div', 'class' => 'dt_center', 'slot' => 'center'],
                ['tag' => 'div', 'class' => 'dt_right', 'slot' => 'right'],
            ],
        ],
        ['tag' => 'div', 'class' => 'dt_bottom', 'slot' => 'bottom'],
        ['tag' => 'div', 'class' => 'dt_policy', 'slot' => 'policy'],
        ['tag' => 'div', 'class' => 'dt_toast', 'slot' => 'toast'],
    ],
    'scripts' => [],
    'meta' => [
        'css' => ['/media/.admin/superadmin/skin/{skin}/css/main.css', '/media/.admin/superadmin/skin/{skin}/css/components.css'],
        'js' => ['/media/.admin/superadmin/skin/{skin}/js/main.js', '/media/shared/scripts/cliajax.js'],
    ],
];
