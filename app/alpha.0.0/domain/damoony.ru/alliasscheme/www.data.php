<?php

return [
    'tag' => 'div',
    'class' => 'layout',
    'children' => [
        ['tag' => 'header', 'slot' => 'hello'],
        ['tag' => 'main', 'slot' => 'center'],
        ['tag' => 'footer', 'slot' => 'footer'],
    ],
];