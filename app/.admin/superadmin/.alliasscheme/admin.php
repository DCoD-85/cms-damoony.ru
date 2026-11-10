<?php

// Извлекаем flash-сообщения из сессии и очищаем их
$flash = $_SESSION['flash'] ?? [];
unset($_SESSION['flash']);

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
            ]
        ],
        ['tag' => 'div', 'class' => 'admin-bottom', 'slot' => 'bottom'],
        
        // Контейнер для всплывающих сообщений
        ['tag' => 'div', 'id' => 'toast-container'],
        
        // === ИИ-АССИСТЕНТ (СТАТИЧЕСКИЙ) ===
        [
            'tag' => 'div',
            'class' => 'ai-assistant',
            'children' => [
                [
                    'tag' => 'div', 
                    'class' => 'ai-panel',
                    'children' => [
                        [
                            'tag' => 'div', 
                            'class' => 'ai-header',
                            'children' => [
                                ['tag' => 'span', 'class' => 'ai-title', 'content' => '🤖 AI-Ассистент'],
                                ['tag' => 'span', 'class' => 'ai-debug', 'id' => 'debug-badge', 'content' => '0 ms'],
                                ['tag' => 'button', 'class' => 'ai-close', 'id' => 'ai-close', 'content' => '×'],
                            ]
                        ],
                        [
                            'tag' => 'div', 
                            'class' => 'ai-messages',
                            'children' => [
                                ['tag' => 'div', 'class' => 'ai-msg ai-bot', 'content' => 'Привет! Я ваш AI-ассистент. Чем могу помочь?']
                            ]
                        ],
                        [
                            'tag' => 'div', 
                            'class' => 'ai-input-area',
                            'children' => [
                                ['tag' => 'input', 'class' => 'ai-input', 'attrs' => ['type' => 'text', 'placeholder' => 'Введите сообщение...', 'disabled' => 'disabled']],
                                ['tag' => 'button', 'class' => 'ai-send', 'content' => '➤', 'attrs' => ['disabled' => 'disabled']]
                            ]
                        ]
                    ]
                ],
                ['tag' => 'button', 'class' => 'ai-toggle', 'id' => 'ai-toggle', 'content' => '🤖']
            ]
        ],
        [
            'tag' => 'script',
            'content' => 'window.flashMessages = ' . json_encode($flash) . ';'
        ]
    ],
    'meta' => [
        'css' => ['/media/.admin/superadmin/skin/default/css/main.css', '/media/.admin/superadmin/skin/default/css/components.css'],
        'js' => ['/media/.admin/superadmin/skin/default/js/main.js'],
    ]
];
