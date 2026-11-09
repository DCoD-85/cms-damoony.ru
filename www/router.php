<?php

// Этот файл используется ТОЛЬКО для встроенного PHP-сервера в тестах CI
// (php -S 127.0.0.1:8080 -t www/ www/router.php)

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// 1. Админка
if (str_starts_with($uri, '/admin')) {
    require __DIR__ . '/admin/index.php';
    return true;
}

// 2. Контент
if (str_starts_with($uri, '/content')) {
    require __DIR__ . '/content.php';
    return true;
}

// 3. Статические файлы
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

// 4. Публичный сайт
require __DIR__ . '/index.php';
return true;
