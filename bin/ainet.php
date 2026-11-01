<?php

declare(strict_types=1);

use Damoony\Ainet\AinetFacade;

require_once __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
$configFile = $root . '/config/ainet.config';
$cacheDir = $root . '/var/ainet-cache';

$input = stream_get_contents(STDIN);
$input = $input === false ? '' : $input;

$request = json_decode($input, true);
if (!is_array($request) || !isset($request['command']) || !is_string($request['command'])) {
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON request', 'hint' => '{"command": "net:status"}']);
    exit(1);
}

$command = $request['command'];
$args = is_array($request['args'] ?? null) ? $request['args'] : [];

try {
    $facade = AinetFacade::createFromConfig($configFile, $cacheDir);
} catch (\Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'Cannot init AINET: ' . $e->getMessage(), 'hint' => 'Check config/ainet.config']);
    exit(1);
}

try {
    $result = match ($command) {
        'net:status' => $facade->status(),
        'image:search' => $facade->searchImages(
            (string) ($args['query'] ?? ''),
            (int) ($args['count'] ?? 5),
            isset($args['provider']) ? (string) $args['provider'] : null,
        ),
        'image:download' => $facade->downloadImage(
            (string) ($args['url'] ?? ''),
            (string) ($args['domain'] ?? ''),
            (string) ($args['allias'] ?? 'www'),
            (string) ($args['category'] ?? 'general'),
            (string) ($args['alt'] ?? ''),
        ),
        'fetch' => (($args['type'] ?? 'json') === 'text')
            ? $facade->fetchText((string) ($args['url'] ?? ''))
            : $facade->fetchJson((string) ($args['url'] ?? '')),
        default => ['ok' => false, 'error' => "Unknown command '{$command}'", 'hint' => 'Allowed: net:status, image:search, image:download, fetch'],
    };
} catch (\Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage(), 'hint' => 'Unexpected error']);
    exit(1);
}

// image:download возвращает bytes — в CLI-выводе это мусор, убираем
if (isset($result['data']['bytes'])) {
    unset($result['data']['bytes']);
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
echo "\n";