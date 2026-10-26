<?php

return [
    'tag' => 'div',
    'class' => 'layout',
    'children' => [
        ['tag' => 'header', 'slot' => 'top'],
        ['tag' => 'main', 'slot' => 'center'],
    ],
];