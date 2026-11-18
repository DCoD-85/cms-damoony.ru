<?php

declare(strict_types=1);

/**
 * Отдельный вход в серверную админку.
 * Nginx admin-vhost направляет сюда; публичный index.php не участвует в выборе.
 */

$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
$host = preg_replace('/:\d+$/', '', $host) ?? $host;

if (filter_var($host, FILTER_VALIDATE_IP) === false) {
    http_response_code(404);
    exit('Not Found');
}

header('Cache-Control: no-store, private');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

$uri = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
if ($uri === '/admin.php' || $uri === '/admin.php/') {
    $uri = '/app/admin/auth/';
} elseif (str_starts_with($uri, '/admin.php/')) {
    $uri = '/app/admin/' . ltrim(substr($uri, strlen('/admin.php/')), '/');
} elseif ($uri === '/' || $uri === '') {
    $uri = '/app/admin/auth/';
} elseif (!str_starts_with($uri, '/app/admin/')) {
    $uri = '/app/admin/' . ltrim($uri, '/');
}

$_SERVER['REQUEST_URI'] = $uri;
$_SERVER['SCRIPT_NAME'] = '/index.php';

require __DIR__ . '/index.php';
