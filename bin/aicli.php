<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Damoony\Ainet\AinetFacade;

$input = stream_get_contents(STDIN);
$input = $input === false ? '' : $input;
$request = json_decode($input, true);

if (!is_array($request) || !isset($request['command']) || !is_string($request['command'])) {
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON request', 'hint' => '{"command": "engine_status"}']);
    exit(1);
}

$command = $request['command'];
$args = is_array($request['args'] ?? null) ? $request['args'] : [];

$agentConfigFile = __DIR__ . '/../config/agent.config';
$allowedDomains = [];
if (is_file($agentConfigFile)) {
    $agentRaw = file_get_contents($agentConfigFile);
    $agentConfig = $agentRaw === false ? null : json_decode($agentRaw, true);
    if (is_array($agentConfig) && isset($agentConfig['allowed_domains']) && is_array($agentConfig['allowed_domains'])) {
        $allowedDomains = $agentConfig['allowed_domains'];
    }
}

$allowedCommands = [
    'read_test_file',
    'engine_status',
    'filler_toggle',
    'domain:new',
    'allias:new',
    'page:new',
    'page:write',
    'page:read',
    'net:status',
    'image:search',
    'image:download',
    'fetch',
];

if (!in_array($command, $allowedCommands, true)) {
    echo json_encode([
        'ok' => false,
        'error' => "Command '{$command}' is not allowed.",
        'hint' => 'Allowed: ' . implode(', ', $allowedCommands),
    ]);
    exit(1);
}

$ainet = null;
$ainetError = null;
if (in_array($command, ['net:status', 'image:search', 'image:download', 'fetch'], true)) {
    try {
        $ainet = AinetFacade::createFromConfig(
            __DIR__ . '/../config/ainet.config',
            __DIR__ . '/../var/ainet-cache',
        );
    } catch (\Throwable $e) {
        $ainetError = 'AINET init failed: ' . $e->getMessage();
    }
}

/**
 * Парсит вывод CLI (Result) в {ok, output, error, hint}.
 */
function parseCliResult(string $output): array
{
    $trimmed = trim($output);
    $isError = str_contains($trimmed, 'FAILED')
        || str_contains($trimmed, 'Ошибки:')
        || str_contains($trimmed, '✗ ')
        || $trimmed === '';

    if ($isError) {
        return [
            'ok' => false,
            'output' => $trimmed,
            'error' => $trimmed,
            'hint' => 'Read output; if "already exists" — skip and continue',
        ];
    }

    return [
        'ok' => true,
        'output' => $trimmed,
        'error' => '',
        'hint' => '',
    ];
}

/** Безопасный вызов CLI-команды через shell_exec. */
function runCli(string $args): array
{
    $output = shell_exec('php ' . __DIR__ . '/cli.php ' . $args . ' 2>&1');
    return parseCliResult((string) $output);
}

try {
    if ($command === 'read_test_file') {
        $filename = (string) ($args['filename'] ?? '');
        if (str_contains($filename, '..') || str_contains($filename, '/')) {
            throw new \RuntimeException('Invalid filename.');
        }
        $filePath = __DIR__ . '/../var/' . basename($filename);
        if (!is_file($filePath)) {
            echo json_encode(['ok' => false, 'error' => "File '{$filename}' not found."]);
            exit(1);
        }
        echo json_encode(['ok' => true, 'data' => ['content' => file_get_contents($filePath)]]);
    } elseif ($command === 'engine_status') {
        $output = [];
        $returnCode = 0;
        exec('php ' . __DIR__ . '/cli.php structure:check 2>&1', $output, $returnCode);
        $text = implode("\n", $output);

        if ($returnCode === 0) {
            echo json_encode(['ok' => true, 'data' => ['status' => 'OK', 'message' => 'Structure is perfect.']]);
        } else {
            preg_match('/Найдено проблем: (\d+)/', $text, $matches);
            $errorCount = $matches[1] ?? 'unknown';
            echo json_encode([
                'ok' => false,
                'error' => 'Structure check failed',
                'data' => ['errors_count' => $errorCount, 'raw_output' => $text],
            ]);
        }
    } elseif ($command === 'filler_toggle') {
        $domain = (string) ($args['domain'] ?? '');
        $allias = (string) ($args['allias'] ?? '');
        $block = (string) ($args['block'] ?? '');
        $value = (string) ($args['value'] ?? '0');

        if (!in_array($domain, $allowedDomains, true)) {
            throw new \RuntimeException("Domain '{$domain}' is not allowed.");
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $allias) || !preg_match('/^[a-z][a-z0-9_]*$/', $block)) {
            throw new \RuntimeException('Invalid arguments for filler_toggle.');
        }

        $r = runCli("filler:toggle --domain={$domain} --allias={$allias} --block={$block} --value={$value}");
        echo json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } elseif ($command === 'domain:new') {
        $domain = (string) ($args['domain'] ?? '');
        if (!in_array($domain, $allowedDomains, true)) {
            throw new \RuntimeException("Security violation: Domain '{$domain}' is not in the allowed list.");
        }
        $safeDomain = escapeshellarg($domain);
        $r = runCli("domain:new --domain={$safeDomain}");
        echo json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } elseif ($command === 'allias:new') {
        $domain = (string) ($args['domain'] ?? '');
        $name = (string) ($args['name'] ?? '');

        if (!in_array($domain, $allowedDomains, true)) {
            throw new \RuntimeException("Security violation: Domain '{$domain}' is not in the allowed list.");
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            throw new \RuntimeException('Invalid alias name.');
        }

        $safeName = escapeshellarg($name);
        $safeDomain = escapeshellarg($domain);
        $r = runCli("allias:new --domain={$safeDomain} --name={$safeName}");
        echo json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } elseif ($command === 'page:new') {
        $domain = (string) ($args['domain'] ?? '');
        $allias = (string) ($args['allias'] ?? '');
        $name = (string) ($args['name'] ?? '');

        if (!in_array($domain, $allowedDomains, true)) {
            throw new \RuntimeException("Security violation: Domain '{$domain}' is not in the allowed list.");
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $allias) || !preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            throw new \RuntimeException('Invalid alias or page name.');
        }

        $safeDomain = escapeshellarg($domain);
        $safeAllias = escapeshellarg($allias);
        $safeName = escapeshellarg($name);
        $r = runCli("page:new --domain={$safeDomain} --allias={$safeAllias} --name={$safeName}");
        echo json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } elseif ($command === 'page:write') {
        $domain = (string) ($args['domain'] ?? '');
        $allias = (string) ($args['allias'] ?? '');
        $name = (string) ($args['name'] ?? '');
        $blocks = $args['blocks'] ?? [];
        $withSchema = (string) ($args['with_schema'] ?? '0');

        if (!in_array($domain, $allowedDomains, true)) {
            throw new \RuntimeException("Security violation: Domain '{$domain}' is not in the allowed list.");
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $allias)) {
            throw new \RuntimeException('Invalid alias name.');
        }
        if (!is_array($blocks)) {
            throw new \RuntimeException('Blocks must be a JSON array.');
        }
        if ($name !== '' && !preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            throw new \RuntimeException('Invalid page name.');
        }

        // Remap: LLM иногда использует local_url или src вместо content для img
        foreach ($blocks as &$b) {
            if (!is_array($b)) {
                continue;
            }
            $type = $b['type'] ?? '';
            if ($type === 'img' && (empty($b['content']) || !is_string($b['content'] ?? null))) {
                if (!empty($b['local_url']) && is_string($b['local_url'])) {
                    $b['content'] = $b['local_url'];
                } elseif (!empty($b['src']) && is_string($b['src'])) {
                    $b['content'] = $b['src'];
                } elseif (!empty($b['url']) && is_string($b['url'])) {
                    $b['content'] = $b['url'];
                }
                unset($b['local_url'], $b['src']);
            }
        }
        unset($b);

        $cliName = ($name === '') ? '__root__' : $name;
        $json = json_encode($blocks, JSON_UNESCAPED_UNICODE);
        $encoded = base64_encode((string) $json);

        $safeDomain = escapeshellarg($domain);
        $safeAllias = escapeshellarg($allias);
        $safeName = escapeshellarg($cliName);
        $safeJson = escapeshellarg($encoded);
        $safeSchema = escapeshellarg($withSchema);

        $r = runCli("page:write --domain={$safeDomain} --allias={$safeAllias} --name={$safeName} --json_b64={$safeJson} --with_schema={$safeSchema}");
        echo json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } elseif ($command === 'page:read') {
        $domain = (string) ($args['domain'] ?? '');
        $allias = (string) ($args['allias'] ?? '');
        $name = (string) ($args['name'] ?? '');

        if (!in_array($domain, $allowedDomains, true)) {
            throw new \RuntimeException("Security violation: Domain '{$domain}' is not in the allowed list.");
        }

        $safeDomain = escapeshellarg($domain);
        $safeAllias = escapeshellarg($allias);
        $safeName = escapeshellarg($name);

        $r = runCli("page:read --domain={$safeDomain} --allias={$safeAllias} --name={$safeName}");
        echo json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } elseif ($command === 'net:status') {
        if ($ainet === null) {
            echo json_encode(['ok' => false, 'error' => $ainetError, 'hint' => 'Fix config/ainet.config or create it from ainet.config.example']);
            exit(1);
        }
        echo json_encode($ainet->status(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } elseif ($command === 'image:search') {
        if ($ainet === null) {
            echo json_encode(['ok' => false, 'error' => $ainetError, 'hint' => 'Fix config/ainet.config']);
            exit(1);
        }
        $query = (string) ($args['query'] ?? '');
        $count = (int) ($args['count'] ?? 5);
        $provider = isset($args['provider']) && is_string($args['provider']) ? $args['provider'] : null;

        echo json_encode(
            $ainet->searchImages($query, $count, $provider),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    } elseif ($command === 'fetch') {
        if ($ainet === null) {
            echo json_encode(['ok' => false, 'error' => $ainetError, 'hint' => 'Fix config/ainet.config']);
            exit(1);
        }
        $url = (string) ($args['url'] ?? '');
        $type = (string) ($args['type'] ?? 'json');

        $result = ($type === 'text') ? $ainet->fetchText($url) : $ainet->fetchJson($url);
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } elseif ($command === 'image:download') {
        if ($ainet === null) {
            echo json_encode(['ok' => false, 'error' => $ainetError, 'hint' => 'Fix config/ainet.config']);
            exit(1);
        }
        $url = (string) ($args['url'] ?? '');
        $domain = (string) ($args['domain'] ?? '');
        $allias = (string) ($args['allias'] ?? 'www');
        $category = (string) ($args['category'] ?? 'general');
        $alt = (string) ($args['alt'] ?? '');

        if (!in_array($domain, $allowedDomains, true)) {
            echo json_encode([
                'ok' => false,
                'error' => "Security violation: Domain '{$domain}' is not in the allowed list.",
                'hint' => 'Add domain to config/agent.config allowed_domains',
                'context' => ['domain' => $domain],
            ]);
            exit(1);
        }

        $dl = $ainet->downloadImage($url, $domain, $allias, $category, $alt);
        if (($dl['ok'] ?? false) !== true) {
            echo json_encode($dl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit(1);
        }

        $data = $dl['data'];
        $bytes = (string) $data['bytes'];
        $mime = (string) $data['mime'];
        $ext = (string) $data['ext'];
        $hash = (string) $data['hash'];

        $tmpDir = __DIR__ . '/../var/tmp';
        if (!is_dir($tmpDir) && !@mkdir($tmpDir, 0o775, true) && !is_dir($tmpDir)) {
            echo json_encode(['ok' => false, 'error' => 'Cannot create var/tmp', 'hint' => 'Check write permissions']);
            exit(1);
        }
        $tmpFile = $tmpDir . '/media_' . $hash . '_' . bin2hex(random_bytes(4)) . '.bin';
        if (@file_put_contents($tmpFile, $bytes) === false) {
            echo json_encode(['ok' => false, 'error' => 'Cannot write tmp file', 'hint' => 'Check var/tmp permissions']);
            exit(1);
        }

        try {
            $safeDomain = escapeshellarg($domain);
            $safeAllias = escapeshellarg($allias);
            $safeCategory = escapeshellarg($category);
            $safeDataFile = escapeshellarg($tmpFile);
            $safeMime = escapeshellarg($mime);
            $safeAlt = escapeshellarg($alt);
            $safeSourceUrl = escapeshellarg($url);

			$cmd = 'php ' . __DIR__ . "/cli.php content:store"
				. " --domain={$safeDomain}"
				. " --allias={$safeAllias}"
				. " --category={$safeCategory}"
				. " --data_file={$safeDataFile}"
				. " --mime={$safeMime}"
				. " --alt={$safeAlt}"
				. " --source_url={$safeSourceUrl}"
				. ' 2>&1';

            $output = shell_exec($cmd);
            $outputStr = trim((string) $output);
        } finally {
            @unlink($tmpFile);
        }

        $storeResult = parseCliResult($outputStr);

        if (!$storeResult['ok']) {
            echo json_encode([
                'ok' => false,
                'error' => 'media:store failed',
                'hint' => $storeResult['hint'],
                'context' => ['cli_output' => $outputStr],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit(1);
        }

        $localUrl = '/content/' . $domain . '/' . $allias . '/' . $category . '/' . $hash . '.' . $ext;

        echo json_encode([
            'ok' => true,
            'data' => [
                'local_url' => $localUrl,
                'path' => 'content/' . $domain . '/' . $allias . '/' . $category . '/' . $hash . '.' . $ext,
                'hash' => $hash,
                'ext' => $ext,
                'mime' => $mime,
                'width' => (int) $data['width'],
                'height' => (int) $data['height'],
                'size' => (int) $data['size'],
                'cached' => (bool) $data['cached'],
                'cli_output' => $outputStr,
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
} catch (\Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage(), 'hint' => 'Unexpected error']);
    exit(1);
}