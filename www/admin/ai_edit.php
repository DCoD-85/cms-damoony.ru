<?php
declare(strict_types=1);

// 1. Включаем буферизацию и скрываем ошибки от вывода в браузер
ob_start();
ini_set('display_errors', '0');

use Damoony\Cli\Commands\AiEditPageCommand;
use Damoony\Cli\Context;
use Damoony\Core\Config;
use Damoony\Domain\ConfigDomainResolver;
use Damoony\Files\Paths;

require_once __DIR__ . '/../../vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Получаем сырые данные из потока
    $rawInput = file_get_contents('php://input');
    $input = json_decode((string)$rawInput, true);
    
    $prompt = $input['prompt'] ?? '';
    $domain = $input['domain'] ?? '';
    $alias = $input['alias'] ?? 'www';
    $page = $input['page'] ?? '__root__';
    $apply = (bool)($input['apply'] ?? false);

    if (!$domain || !$prompt) {
        throw new \RuntimeException('Missing domain or prompt. Raw input: ' . $rawInput);
    }

    $root = dirname(__DIR__, 2);
    $paths = new Paths($root);
    
    $siteFile = $root . '/config/site.config';
    $site = is_file($siteFile) ? Config::fromFile($siteFile) : Config::fromArray([]);
    $resolver = new ConfigDomainResolver($site);

    $cmd = new AiEditPageCommand($paths, $resolver);
    $ctx = new Context(static fn() => null);

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

    // Очищаем буфер от любых случайных Warning'ов
    ob_clean();
    
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
} catch (\Throwable $e) {
    // Очищаем буфер даже при фатальной ошибке
    ob_clean();
    echo json_encode([
        'ok' => false,
        'error' => 'Server error: ' . $e->getMessage(),
    ]);
}
