<?php

declare(strict_types=1);

ob_start();
ini_set('display_errors', '1'); // Временно включили, чтобы видеть ошибки
ini_set('max_execution_time', '300'); // 5 минут на генерацию
ini_set('memory_limit', '512M');
error_reporting(E_ALL);

use Damoony\Ai\Orchestrator\ContextManager;
use Damoony\Ai\Orchestrator\Orchestrator;
use Damoony\Ai\Orchestrator\ToolsCollector;
use Damoony\Cli\Ai\AicliClient;
use Damoony\Cli\Commands\SessionApplyCommand;
use Damoony\Cli\Commands\SessionDiscardCommand;
use Damoony\Cli\Commands\SessionStartCommand;
use Damoony\Cli\Context;
use Damoony\Core\Config;
use Damoony\Core\Domain\ConfigDomainResolver;
use Damoony\Core\Files\Paths;
use Damoony\Core\Session\NativeSession;

require_once __DIR__ . '/../../vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');


/**
 * Рекурсивно считает блоки (включая детей section/div).
 *
 * @param array<int, mixed> $blocks
 */
function self_countBlocks(array $blocks): int
{
    $count = 0;
    foreach ($blocks as $block) {
        if (!is_array($block)) {
            continue;
        }
        $count++;
        if (isset($block['children']) && is_array($block['children'])) {
            $count += self_countBlocks($block['children']);
        }
    }
    return $count;
}

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
    if (!preg_match('/^[a-z][a-z0-9_]*$/', $alias)) {
        throw new \RuntimeException('Invalid alias');
    }
    if ($page !== '__root__' && !preg_match('/^[a-z][a-z0-9_]*$/', $page)) {
        throw new \RuntimeException('Invalid page name');
    }

    $sess = new NativeSession('ADMINSESSID', ($_SERVER['HTTPS'] ?? '') === 'on');
    $sess->start();
    $adminSessId = (string) $sess->get('admin_login');
    if ($adminSessId === '') {
        throw new \RuntimeException('Forbidden: Admin authentication required.');
    }

    $root = dirname(__DIR__, 2);
    $paths = new Paths($root);
    $siteFile = $root . '/config/site.config';
    $site = is_file($siteFile) ? Config::fromFile($siteFile) : Config::fromArray([]);
    $resolver = new ConfigDomainResolver($site);

    try {
        $realDomain = $resolver->resolve($domain)->real;
    } catch (\Throwable) {
        $realDomain = $domain;
    }

    $sessionDir = $paths->sessionDir($tabId, $realDomain);
    $ctxCli = new Context(static fn () => null);

    // === 1. START ===
    if ($action === 'start') {
        if (is_dir($sessionDir)) {
            $discardCmd = new SessionDiscardCommand($paths);
            $discardCmd->run(['domain' => $realDomain, 'tab' => $tabId], $ctxCli);
        }
        if (!is_dir($sessionDir)) {
            $startCmd = new SessionStartCommand($paths);
            $r = $startCmd->run([
                'domain' => $realDomain,
                'tab' => $tabId,
                'admin-sess' => $adminSessId,
            ], $ctxCli);
            if (!$r->ok) {
                throw new \RuntimeException('Session start failed: ' . implode('; ', $r->errors));
            }
        }

        ob_clean();
        echo json_encode([
            'ok' => true,
            'tab' => $tabId,
            'preview_url' => '/admin/preview/' . $tabId . '/',
        ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // === 2. PREVIEW (Мультиагентный) ===
    if ($action === 'preview') {
        $prompt = (string) ($input['prompt'] ?? '');
        if ($prompt === '') {
            throw new \RuntimeException('Missing prompt');
        }
        if (!is_dir($sessionDir)) {
            throw new \RuntimeException('Session not found. Call action=start first.');
        }

        $targetBlock = (string) ($input['target_block'] ?? 'all');

        // Читаем текущие блоки
        $jsonFile = $sessionDir . '/' . $alias . '/algoritm/'
            . ($page === '__root__' ? 'algoritm' : $page)
            . '.editor.json';
        $current = is_file($jsonFile) ? json_decode((string) file_get_contents($jsonFile), true) : [];
        if (!is_array($current)) {
            $current = [];
        }

        // Запускаем мультиагентный Оркестратор
        $orchClient = new AicliClient($root);
        $orchCtx = new ContextManager($paths, $tabId, $realDomain);
        $orchTools = new ToolsCollector($orchClient);
        $orchInterviewer = new \Damoony\Ai\Orchestrator\Agent\InterviewerAgent($root);
        $orchArchitect = new \Damoony\Ai\Orchestrator\Agent\ArchitectAgent($root);
        $orchCoder = new \Damoony\Ai\Orchestrator\Agent\CoderAgent($root);

        $orchestrator = new Orchestrator($orchCtx, $orchTools, $orchInterviewer, $orchArchitect, $orchCoder, $orchClient);
        /** @var list<array<string, mixed>> $current */
        $orchResult = $orchestrator->run($tabId, $realDomain, $alias, $prompt, $targetBlock, $current);

        // Проверяем статус
        $status = $orchResult['meta']['status'] ?? '';

        if ($status === 'chat_done') {
            $reply = $orchResult['agent_outputs']['interviewer']['message'] ?? 'Понял вас.';
            $brief = $orchResult['input']['brief'] ?? '';

            ob_clean();
            echo json_encode([
                'ok' => true,
                'chat_only' => true,
                'reply' => $reply,
                'brief' => $brief,
            ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($status !== 'coder_done') {
            $errs = implode('; ', $orchResult['meta']['errors'] ?? []);
            throw new \RuntimeException('Multi-agent failed: ' . $errs);
        }

        // Берем блоки от Кодера
        $newBlocks = $orchResult['agent_outputs']['coder']['blocks'] ?? [];
        // Пишем в файл через CLI
        $tmpJsonFile = $root . '/var/aiui_write_' . $tabId . '.json';
        if (!is_dir($root . '/var')) {
            mkdir($root . '/var', 0o775, true);
        }
        $jsonStr = json_encode($newBlocks, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($jsonStr === false) {
            throw new \RuntimeException('json_encode failed: ' . json_last_error_msg());
        }
        file_put_contents($tmpJsonFile, $jsonStr);

        $writeClient = new AicliClient($root);
        $writeArgs = 'page:write'
            . ' --session=' . $tabId
            . ' --domain=' . $realDomain
            . ' --allias=' . $alias
            . ' --name=' . $page
            . ' --json_file=' . escapeshellarg($tmpJsonFile)
            . ' --with_schema=1';

        $writeRes = $writeClient->runJson($writeArgs);
        @unlink($tmpJsonFile);

        if (!($writeRes['ok'] ?? false)) {
            $hint = (string) ($writeRes['hint'] ?? '');
            $errMsg = 'page:write failed: ' . ($writeRes['error'] ?? 'unknown');
            if ($hint !== '') {
                $errMsg .= ' | hint: ' . substr($hint, 0, 300);
            }
            throw new \RuntimeException($errMsg);
        }

        ob_clean();
        echo json_encode([
            'ok' => true,
            'preview_url' => '/admin/preview/' . $tabId . '/',
            'blocks_count' => count($newBlocks),
        ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // === 2b. BUILD: явная генерация по кнопке (без Интервьюера) ===
    if ($action === 'build') {
        if (!is_dir($sessionDir)) {
            throw new \RuntimeException('Session not found. Call action=start first.');
        }

        $jsonFile = $sessionDir . '/' . $alias . '/algoritm/'
            . ($page === '__root__' ? 'algoritm' : $page)
            . '.editor.json';
        $current = is_file($jsonFile) ? json_decode((string) file_get_contents($jsonFile), true) : [];
        if (!is_array($current)) {
            $current = [];
        }

        $orchClient = new AicliClient($root);
        $orchCtx = new ContextManager($paths, $tabId, $realDomain);
        $orchTools = new ToolsCollector($orchClient);
        $orchInterviewer = new \Damoony\Ai\Orchestrator\Agent\InterviewerAgent($root);
        $orchArchitect = new \Damoony\Ai\Orchestrator\Agent\ArchitectAgent($root);
        $orchCoder = new \Damoony\Ai\Orchestrator\Agent\CoderAgent($root);
        $orchestrator = new Orchestrator($orchCtx, $orchTools, $orchInterviewer, $orchArchitect, $orchCoder, $orchClient);

        $orchResult = $orchestrator->build($tabId, $realDomain, $alias);
        $status = $orchResult['meta']['status'] ?? '';

        if (!in_array($status, ['build_done', 'build_partial'], true)) {
            $errors = $orchResult['meta']['errors'] ?? ['Unknown build error'];
            $errs = implode('; ', $errors);
            throw new \RuntimeException('Build failed: ' . $errs);
        }

        $pagesTotal = (int) ($orchResult['meta']['pages_total'] ?? 0);
        $pagesOk = (int) ($orchResult['meta']['pages_ok'] ?? 0);
        $progress = $orchResult['meta']['build_progress'] ?? [];

        ob_clean();
        echo json_encode([
            'ok' => true,
            'preview_url' => '/admin/preview/' . $tabId . '/',
            'pages_total' => $pagesTotal,
            'pages_ok' => $pagesOk,
            'progress' => $progress,
            'status' => $status,
        ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // === 3. APPLY ===
    if ($action === 'apply') {
        // ЗАЩИТА: нельзя применять почти пустую страницу
        $sessJson = $sessionDir . '/' . $alias . '/algoritm/'
            . ($page === '__root__' ? 'algoritm' : $page)
            . '.editor.json';
        $sessBlocks = is_file($sessJson) ? json_decode((string) file_get_contents($sessJson), true) : [];
        $blockCount = is_array($sessBlocks) ? self_countBlocks($sessBlocks) : 0;
        if ($blockCount < 5) {
            throw new \RuntimeException(
                'Применение заблокировано: страница содержит только ' . $blockCount
                . ' блок(ов). Сгенерируйте более полный контент или добавьте блоки вручную.',
            );
        }

        $applyCmd = new SessionApplyCommand($paths);
        $r = $applyCmd->run([
            'domain' => $realDomain,
            'tab' => $tabId,
            'admin-sess' => $adminSessId,
        ], $ctxCli);
        if (!$r->ok) {
            throw new \RuntimeException(implode('; ', $r->errors));
        }

        ob_clean();
        echo json_encode(['ok' => true, 'applied' => true], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
        exit;
    }

    throw new \RuntimeException('Unknown action: ' . $action);

} catch (\Throwable $e) {
    ob_clean();
    http_response_code(400);
    $err = $e->getMessage() . ' | File: ' . $e->getFile() . ':' . $e->getLine();
    $json = json_encode(
        ['ok' => false, 'error' => $err],
        JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE,
    );
    echo $json !== false ? $json : '{"ok":false,"error":"json_encode failed: ' . addslashes(json_last_error_msg()) . '"}';
}
