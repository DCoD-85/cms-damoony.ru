<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors', '0');

use Damoony\Ai\Aicli\AicliClient;
use Damoony\Cli\Commands\SessionApplyCommand;
use Damoony\Cli\Commands\SessionStartCommand;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Domain\ConfigDomainResolver;
use Damoony\Files\Paths;
use Damoony\Session\NativeSession;

require_once __DIR__ . '/../../vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $raw = (string) file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!is_array($input)) {
        throw new \RuntimeException('Invalid JSON input');
    }

    $action = (string) ($input['action'] ?? 'preview');
    $domain = (string) ($input['domain'] ?? '');
    $alias  = (string) ($input['alias'] ?? 'www');
    $page   = (string) ($input['page'] ?? '__root__');
    $tabId  = (string) ($input['tab'] ?? '');

    if ($domain === '' || !preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain)) {
        throw new \RuntimeException('Invalid domain');
    }
    if (!preg_match('/^[a-f0-9]{8,32}$/', $tabId)) {
        throw new \RuntimeException('Invalid tab ID (8-32 hex chars)');
    }

    // Admin session id (для привязки сессии)
    $sess = new NativeSession('ADMINSESSID', ($_SERVER['HTTPS'] ?? '') === 'on');
    $sess->start();
    $adminSessId = (string) session_id();

    $root = dirname(__DIR__, 2);
    $paths = new Paths($root);
    $siteFile = $root . '/config/site.config';
    $site = is_file($siteFile) ? Config::fromFile($siteFile) : Config::fromArray([]);
    $resolver = new ConfigDomainResolver($site);

    // Резолвим в real (для файлов)
    try {
        $realDomain = $resolver->resolve($domain)->real;
    } catch (\Throwable) {
        $realDomain = $domain;
    }

    $sessionDir = $paths->sessionDir($tabId, $realDomain);
    $ctx = new Context(static fn() => null);

    // === 1. START: создаём сессию, если её нет ===
    if ($action === 'start') {
        if (!is_dir($sessionDir)) {
            $startCmd = new SessionStartCommand($paths);
            $r = $startCmd->run([
                'domain' => $realDomain,
                'tab' => $tabId,
                'admin-sess' => $adminSessId,
            ], $ctx);
            if (!$r->ok) {
                throw new \RuntimeException('Session start failed: ' . implode('; ', $r->errors));
            }
        }

        ob_clean();
        echo json_encode([
            'ok' => true,
            'tab' => $tabId,
            'preview_url' => '/admin/preview/' . $tabId . '/',
        ]);
        exit;
    }

    // === 2. PREVIEW: LLM пишет черновик в сессию ===
    if ($action === 'preview') {
        $prompt = (string) ($input['prompt'] ?? '');
        if ($prompt === '') {
            throw new \RuntimeException('Missing prompt');
        }
        if (!is_dir($sessionDir)) {
            throw new \RuntimeException('Session not found. Call action=start first.');
        }

        // Читаем текущий JSON из сессии
        $jsonFile = $sessionDir . '/' . $alias . '/algoritm/'
            . ($page === '__root__' ? 'algoritm' : $page)
            . '.editor.json';
        $current = is_file($jsonFile) ? json_decode((string) file_get_contents($jsonFile), true) : [];

        // Формируем промпт
        $llmPrompt = "You are an AI web editor. You must output a JSON array of block objects.\n"
            . "Current page blocks:\n" . json_encode($current, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n"
            . "User request: {$prompt}\n\n"
            . "CRITICAL RULES:\n"
            . "1. Output ONLY a valid JSON array.\n"
            . "2. Each element MUST be an object with a 'type' field.\n"
            . "3. Allowed types: h1, h2, h3, text, img, link, button, ul, nav, section, div, table, form, input, textarea, select, label.\n"
            . "4. Keep existing blocks unless the user explicitly asks to delete them.\n\n"
            . "EXAMPLE:\n[\n  {\"type\": \"h1\", \"content\": \"Hello\"},\n  {\"type\": \"text\", \"content\": \"World\"}\n]\n\n"
            . "Output JSON array only. No markdown, no explanations.";

        $client = new AicliClient($root);
        $handler = new \Damoony\Ai\Aicli\Handler\AgentChatHandler($client, $root);
        $chat = $handler->handle('agent:chat', ['history' => [['role' => 'user', 'content' => $llmPrompt]]], 'designer');
        if (!$chat->ok) {
            throw new \RuntimeException('AI failed: ' . $chat->error);
        }

        $reply = (string) preg_replace('/^```json\s*|\s*```$/m', '', trim((string) ($chat->data['reply'] ?? '')));
        $newBlocks = json_decode($reply, true);

        if (!is_array($newBlocks)) {
            throw new \RuntimeException('AI returned invalid JSON');
        }
        foreach ($newBlocks as $b) {
            if (!is_array($b) || !isset($b['type']) || !is_string($b['type'])) {
                throw new \RuntimeException('AI returned invalid block structure. Raw: ' . substr($reply, 0, 300));
            }
        }

        // Записываем в сессию через наш page:write (он работает в real домене)
        $encoded = base64_encode((string) json_encode($newBlocks, JSON_UNESCAPED_UNICODE));
        $cliPath = $root . '/bin/cli.php';
        // Используем сессионную папку: подсовываем domain=".sess_{tab}.{real}"
        // НО page:write ожидает release-домен. Проще напрямую записать editor.json + перекомпилировать.
        // Пишем editor.json:
        if (!is_dir(dirname($jsonFile))) {
            mkdir(dirname($jsonFile), 0o775, true);
        }
        file_put_contents($jsonFile, json_encode($newBlocks, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        // Компилируем PHP: используем PageCompiler напрямую
        $compiler = new \Damoony\Page\PageCompiler();
        $namespace = (str_replace(['.', '-'], '_', $realDomain)) . '\\' . $alias;
        $className = $page === '__root__' ? 'algoritm' : 'algoritm_' . $page;
        $php = $compiler->compile($namespace, $className, $newBlocks, true);

        $phpFile = dirname($jsonFile) . '/' . $className . '.php';
        file_put_contents($phpFile, $php);

        ob_clean();
        echo json_encode([
            'ok' => true,
            'preview_url' => '/admin/preview/' . $tabId . '/',
            'blocks_count' => count($newBlocks),
        ]);
        exit;
    }

    // === 3. APPLY: переносим сессию в production ===
    if ($action === 'apply') {
        $applyCmd = new SessionApplyCommand($paths);
        $r = $applyCmd->run([
            'domain' => $realDomain,
            'tab' => $tabId,
            'admin-sess' => $adminSessId,
        ], $ctx);
        if (!$r->ok) {
            throw new \RuntimeException(implode('; ', $r->errors));
        }

        ob_clean();
        echo json_encode(['ok' => true, 'applied' => true]);
        exit;
    }

    throw new \RuntimeException('Unknown action: ' . $action);

} catch (\Throwable $e) {
    ob_clean();
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ]);
}
