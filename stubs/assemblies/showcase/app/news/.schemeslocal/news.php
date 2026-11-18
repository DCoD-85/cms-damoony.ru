<?php
$children = [
    ['tag' => 'header', 'class' => 'dt_top', 'slot' => 'top'],
    ['tag' => 'aside', 'class' => 'dt_left', 'slot' => 'left'],
    ['tag' => 'main', 'class' => 'dt_center', 'slot' => 'center'],
    ['tag' => 'section', 'class' => 'dt_bottom', 'slot' => 'bottom'],
    ['tag' => 'footer', 'class' => 'dt_footer', 'slot' => 'footer'],
    ['tag' => 'div', 'class' => 'dt_policy', 'slot' => 'policy'],
    ['tag' => 'div', 'class' => 'dt_toast', 'slot' => 'toast'],
];

$blockCss = [];
foreach ($children as $child) {
    if (isset($child['slot'])) {
        $blockCss[] = '/media/{real}/{skin_path}/css/' . $child['slot'] . '.css';
    }
}

$baseCss = [
    '/media/{real}/{skin_path}/css/main.css',
    '/media/{real}/{skin_path}/css/blocks.css',
    '/media/{real}/{skin_path}/css/components.css',
];

$skinCss = [
    '/media/{real}/{skin_path}/css/news.css',
];

return [
    'tag' => 'div', 'class' => 'dt news',
    'children' => $children,
    'meta' => [
        'css' => array_merge($baseCss, $blockCss, $skinCss),
        'js' => [
            '/media/shared/scripts/cliajax.js',
            '/media/shared/scripts/policy.js',
        ],
    ],
];
