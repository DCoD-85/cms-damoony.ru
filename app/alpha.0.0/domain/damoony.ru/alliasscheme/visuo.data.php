<?php
return [
    'tag' => 'div',
    'class' => 'dt',
    'layout' => 'vertical',
    'children' => [
        ['tag' => 'div', 'class' => 'dt_top', 'slot' => 'top'],
        ['tag' => 'div', 'class' => 'dt_middle', 'layout' => 'horizontal', 'children' => [
            ['tag' => 'div', 'class' => 'dt_left', 'slot' => 'left'],
            ['tag' => 'div', 'class' => 'dt_center', 'slot' => 'center'],
            ['tag' => 'div', 'class' => 'dt_right', 'slot' => 'right'],
        ]],
        ['tag' => 'div', 'class' => 'dt_bottom', 'slot' => 'bottom'],
        ['tag' => 'div', 'class' => 'dt_policy', 'slot' => 'policy'],
        ['tag' => 'div', 'class' => 'dt_debug', 'slot' => 'debug'],
    ],
];