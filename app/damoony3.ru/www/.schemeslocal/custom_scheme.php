<?php
return [
    'tag' => 'div', 'class' => 'dt',
    'children' => [
        ['tag' => 'main', 'class' => 'dt_center', 'slot' => 'center'],
    ],
    'meta' => [
        'css' => ['/media/{real}/{alias}/skin/{skin}/css/main.css'],
        'js' => ['/media/shared/scripts/cliajax.js'],
    ],
];