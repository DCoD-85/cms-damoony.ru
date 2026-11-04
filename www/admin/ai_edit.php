<?php

declare(strict_types=1);

use Damoony\Cli\Commands\AiEditPageCommand;
use Damoony\Cli\Context;
use Damoony\Files\Paths;

require_once __DIR__ . '/../../vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');

// TODO: Добавить проверку авторизации админа (ADMINSESSID)
// Для MVP считаем, что запрос идет из админки

$input = json_decode((string) file_get_contents('php://input'), true);
$prompt = $input['prompt'] ?? '';
$domain = $input['domain'] ?? '';
$alias = $input['alias'] ?? 'www';
$page = $input['page'] ?? '__root__';
$apply = (bool)($input['apply'] ?? false);

if (!$domain || !$prompt) {
    echo json_encode(['ok' => false, 'error' => 'Missing domain or prompt']);
    exit;
}

$paths = new Paths(dirname(__DIR__, 2));
$cmd = new AiEditPageCommand($paths);
$ctx = new Context(static fn () => null);

$args = [
    'domain' => $domain,
    'alias' => $alias,
    'page' => $page,
    'prompt' => $prompt,
];
if ($apply) {
    $args['force'] = '1';
}

$result = $cmd->run($args, $ctx);

if ($result->ok) {
    echo json_encode([
        'ok' => true,
        'data' => $result->data,
    ]);
} else {
    echo json_encode([
        'ok' => false,
        'error' => implode('; ', $result->errors),
    ]);
}
