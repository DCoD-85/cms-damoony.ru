<?php

declare(strict_types=1);

// API-алиас: без скинов и CSS, только JSON-страницы.
return [
    'tag' => 'div',
    'class' => 'api',
    'children' => [
        ['tag' => 'main', 'slot' => 'center'],
    ],
];
