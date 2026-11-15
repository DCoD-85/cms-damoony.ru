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
        $blockCss[] = '/media/{real}/{alias}/skin/{skin}/css/' . $child['slot'] . '.css';
    }
}

$baseCss = [
    '/media/{real}/{alias}/skin/{skin}/css/main.css',
    '/media/{real}/{alias}/skin/{skin}/css/blocks.css',
    '/media/{real}/{alias}/skin/{skin}/css/components.css',
];

// Скин подключается после базы, но перед компактным
$skinCss = [];

// Compact всегда в самом конце
$compactCss = [
    '/media/{real}/{alias}/skin/{skin}/css/mobile.css',
];

return [
    'tag' => 'div', 'class' => 'dt',
    'children' => $children,
    'meta' => [
        'css' => array_merge($baseCss, $blockCss, $skinCss, $compactCss),
        'js' => ['/media/shared/scripts/cliajax.js'],
        'favicon' => '/media/damoony.ru/shared/ico.ico',
    ],
];
