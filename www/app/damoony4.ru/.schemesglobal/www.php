<?php
$children = [
    ['tag' => 'header', 'class' => 'dt_top', 'slot' => 'top'],
    ['tag' => 'aside', 'class' => 'dt_left', 'slot' => 'left'],
    ['tag' => 'main', 'class' => 'dt_center', 'slot' => 'center'],
    ['tag' => 'aside', 'class' => 'dt_right', 'slot' => 'right'],
    ['tag' => 'section', 'class' => 'dt_bottom', 'slot' => 'bottom'],
    ['tag' => 'footer', 'class' => 'dt_footer', 'slot' => 'footer'],
    ['tag' => 'div', 'class' => 'dt_policy', 'slot' => 'policy'],
    ['tag' => 'div', 'class' => 'dt_toast', 'slot' => 'toast'],
];

// Автоматически подключаем CSS для каждого блока в схеме
$blockCss = [];
foreach ($children as $child) {
    if (isset($child['slot'])) {
        $blockCss[] = '/media/damoony4.ru/www/css/' . $child['slot'] . '.css';
    }
}

$baseCss = [
    '/media/damoony4.ru/www/css/main.css',
    '/media/damoony4.ru/www/css/blocks.css',
    '/media/damoony4.ru/www/css/components.css',
];

// Скин подключается после базы, но перед компактным
$skinCss = [
    '/media/damoony4.ru/www/skin/{skin}/css/main.css',
];

// Compact всегда в самом конце
$compactCss = [
    '/media/damoony4.ru/www/skin/mobile/css/main.css',
];

return [
    'tag' => 'div', 'class' => 'dt',
    'children' => $children,
    'meta' => [
        'css' => array_merge($baseCss, $blockCss, $skinCss, $compactCss),
        'js' => ['/media/shared/scripts/cliajax.js'],
    ],
];
