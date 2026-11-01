<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Damoony\Ai\Navigator;
use Damoony\Ai\Roles;
use Damoony\Ainet\AinetFacade;

/**
 * @param array<string, mixed> $response
 * @param array<string, mixed> $args
 */
function respond(array $response, string $role, ?string $cmd = null, array $args = []): never
{
    $response['role'] = $role;
    $response['next'] = [];

    if ($cmd === 'ai:start') {
        $state = $response['data']['state'] ?? ['domains' => []];
        $next = Navigator::start($state);
    } elseif ($cmd !== null && $cmd !== 'done') {
        $next = Navigator::after($cmd, $args);
    } else {
        $next = [];
    }

    if ($role !== '*') {
        $whitelist = Roles::whitelist($role);
        $next = array_values(array_filter(
            $next,
            static fn (array $n): bool => $n['cmd'] === 'done' || in_array($n['cmd'], $whitelist, true),
        ));
    }

    $response['next'] = $next;
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit(($response['ok'] ?? false) === true ? 0 : 1);
}

/**
 * @return array{ok: bool, output: string, error: string, hint: string}
 */
function parseCliResult(string $output): array
{
    $trimmed = trim($output);
    $isError = str_contains($trimmed, 'FAILED')
        || str_contains($trimmed, 'Ошибки:')
        || str_contains($trimmed, '✗ ')
        || $trimmed === '';

    return $isError
        ? ['ok' => false, 'output' => $trimmed, 'error' => $trimmed, 'hint' => 'Read output; if "already exists" — skip and continue']
        : ['ok' => true, 'output' => $trimmed, 'error' => '', 'hint' => ''];
}

/**
 * @return array{ok: bool, output: string, error: string, hint: string}
 */
function runCli(string $args): array
{
    $output = shell_exec('php ' . __DIR__ . '/cli.php ' . $args . ' 2>&1');
    return parseCliResult((string) $output);
}

/**
 * Вызов CLI-команды с --json (для *:list).
 * Возвращает декодированный массив (ok/data/created/warnings/errors/steps).
 */
/**
 * @return array<string, mixed>
 */
function runCliJson(string $args): array
{
    $output = shell_exec('php ' . __DIR__ . '/cli.php ' . $args . ' --json 2>&1');
    $trimmed = trim((string) $output);
    if ($trimmed === '') {
        return ['ok' => false, 'data' => [], 'error' => 'empty output', 'hint' => 'CLI returned nothing'];
    }
    $decoded = json_decode($trimmed, true);
    if (!is_array($decoded)) {
        return ['ok' => false, 'data' => [], 'error' => 'invalid JSON', 'hint' => substr($trimmed, 0, 200)];
    }
    if (!isset($decoded['data']) || !is_array($decoded['data'])) {
        $decoded['data'] = [];
    }
    return $decoded;
}

// === Вход ===
$input = stream_get_contents(STDIN);
$input = $input === false ? '' : $input;
$request = json_decode($input, true);

if (!is_array($request) || !isset($request['command']) || !is_string($request['command'])) {
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON request', 'hint' => '{"command": "ai:start"}']);
    exit(1);
}

$command = $request['command'];
$args = is_array($request['args'] ?? null) ? $request['args'] : [];
$role = isset($request['role']) && is_string($request['role']) ? $request['role'] : '*';

if (!Roles::isValid($role)) {
    echo json_encode(['ok' => false, 'error' => "Unknown role '{$role}'", 'hint' => 'Allowed roles: * , ' . implode(', ', Roles::all())]);
    exit(1);
}

if ($role === '*') {
    $allowedCommands = ['ai:start', 'done', 'read_test_file', 'engine_status', 'filler_toggle'];
    foreach (Roles::all() as $roleName) {
        $allowedCommands = array_merge($allowedCommands, Roles::whitelist($roleName));
    }
    $allowedCommands = array_merge(
        $allowedCommands,
        [
            'net:status', 'image:search', 'image:download', 'fetch',
            'skin:remove',
            'domain:list', 'allias:list', 'page:list', 'page:read',
            'scheme:list', 'block:list', 'filler:list', 'skin:list',
        ]
    );
    $allowedCommands = array_values(array_unique($allowedCommands));
} else {
    $allowedCommands = array_merge(['ai:start', 'done', 'engine_status'], Roles::whitelist($role));
}

if (!in_array($command, $allowedCommands, true)) {
    respond([
        'ok' => false,
        'error' => "Command '{$command}' is not allowed for role '{$role}'.",
        'hint' => 'Allowed for this role: ' . implode(', ', $allowedCommands),
    ], $role, $command, $args);
}

$agentConfigFile = __DIR__ . '/../config/agent.config';
$allowedDomains = [];
if (is_file($agentConfigFile)) {
    $agentRaw = file_get_contents($agentConfigFile);
    $agentConfig = $agentRaw === false ? null : json_decode($agentRaw, true);
    if (is_array($agentConfig) && isset($agentConfig['allowed_domains']) && is_array($agentConfig['allowed_domains'])) {
        $allowedDomains = $agentConfig['allowed_domains'];
    }
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

try {
    if ($command === 'ai:start') {
        $domains = [];
        $siteFile = __DIR__ . '/../config/site.config';
        if (is_file($siteFile)) {
            $siteRaw = file_get_contents($siteFile);
            $site = $siteRaw === false ? null : json_decode($siteRaw, true);
            if (is_array($site) && isset($site['domains_real']) && is_array($site['domains_real'])) {
                $set = [];
                foreach ($site['domains_real'] as $host => $real) {
                    if (is_string($real) && $real !== '') {
                        $set[$real] = true;
                    }
                }
                $domains = array_keys($set);
                sort($domains);
            }
        }
        respond([
            'ok' => true,
            'data' => [
                'state' => ['domains' => $domains],
                'allowed_domains' => $allowedDomains,
                'whitelist' => $allowedCommands,
            ],
        ], $role, $command, $args);
    }

    if ($command === 'done') {
        respond(['ok' => true, 'data' => ['done' => true]], $role, $command, $args);
    }

    if ($command === 'read_test_file') {
        $filename = (string) ($args['filename'] ?? '');
        if (str_contains($filename, '..') || str_contains($filename, '/')) {
            throw new \RuntimeException('Invalid filename.');
        }
        $filePath = __DIR__ . '/../var/' . basename($filename);
        if (!is_file($filePath)) {
            respond(['ok' => false, 'error' => "File '{$filename}' not found."], $role, $command, $args);
        }
        respond(['ok' => true, 'data' => ['content' => file_get_contents($filePath)]], $role, $command, $args);
    }

    if ($command === 'engine_status') {
        $output = [];
        $returnCode = 0;
        exec('php ' . __DIR__ . '/cli.php structure:check 2>&1', $output, $returnCode);
        $text = implode("\n", $output);
        if ($returnCode === 0) {
            respond(['ok' => true, 'data' => ['status' => 'OK', 'message' => 'Structure is perfect.']], $role, $command, $args);
        }
        preg_match('/Найдено проблем: (\d+)/', $text, $matches);
        respond([
            'ok' => false,
            'error' => 'Structure check failed',
            'data' => ['errors_count' => $matches[1] ?? 'unknown', 'raw_output' => $text],
        ], $role, $command, $args);
    }

    if ($command === 'filler_toggle') {
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
        respond($r, $role, $command, $args);
    }

    if ($command === 'domain:new') {
        $domain = (string) ($args['domain'] ?? '');
        if (!in_array($domain, $allowedDomains, true)) {
            throw new \RuntimeException("Security violation: Domain '{$domain}' is not in the allowed list.");
        }
        $r = runCli('domain:new --domain=' . escapeshellarg($domain));
        respond($r, $role, $command, $args);
    }

    if ($command === 'domain:list') {
        respond(runCliJson('domain:list'), $role, $command, $args);
    }

    if ($command === 'allias:new') {
        $domain = (string) ($args['domain'] ?? '');
        $name = (string) ($args['name'] ?? '');
        if (!in_array($domain, $allowedDomains, true)) {
            throw new \RuntimeException("Security violation: Domain '{$domain}' is not in the allowed list.");
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            throw new \RuntimeException('Invalid alias name.');
        }
        $r = runCli('allias:new --domain=' . escapeshellarg($domain) . ' --name=' . escapeshellarg($name));
        respond($r, $role, $command, $args);
    }

    if ($command === 'allias:list') {
        $domain = (string) ($args['domain'] ?? '');
        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain) || str_contains($domain, '..')) {
            throw new \RuntimeException('Invalid domain.');
        }
        respond(runCliJson('allias:list --domain=' . escapeshellarg($domain)), $role, $command, $args);
    }

    if ($command === 'page:new') {
        $domain = (string) ($args['domain'] ?? '');
        $allias = (string) ($args['allias'] ?? '');
        $name = (string) ($args['name'] ?? '');
        if (!in_array($domain, $allowedDomains, true)) {
            throw new \RuntimeException("Security violation: Domain '{$domain}' is not in the allowed list.");
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $allias) || !preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            throw new \RuntimeException('Invalid alias or page name.');
        }
        $r = runCli(
            'page:new --domain=' . escapeshellarg($domain)
            . ' --allias=' . escapeshellarg($allias)
            . ' --name=' . escapeshellarg($name),
        );
        respond($r, $role, $command, $args);
    }

    if ($command === 'page:write') {
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
        foreach ($blocks as &$b) {
            if (!is_array($b)) {
                continue;
            }
            if (($b['type'] ?? '') === 'img' && (empty($b['content']) || !is_string($b['content']))) {
                foreach (['local_url', 'src', 'url'] as $k) {
                    if (!empty($b[$k]) && is_string($b[$k])) {
                        $b['content'] = $b[$k];
                        break;
                    }
                }
                unset($b['local_url'], $b['src']);
            }
        }
        unset($b);

        $cliName = ($name === '') ? '__root__' : $name;
        $encoded = base64_encode((string) json_encode($blocks, JSON_UNESCAPED_UNICODE));

        $r = runCli(
            'page:write --domain=' . escapeshellarg($domain)
            . ' --allias=' . escapeshellarg($allias)
            . ' --name=' . escapeshellarg($cliName)
            . ' --json_b64=' . escapeshellarg($encoded)
            . ' --with_schema=' . escapeshellarg($withSchema),
        );
        respond($r, $role, $command, $args);
    }

    if ($command === 'page:read') {
        $domain = (string) ($args['domain'] ?? '');
        $allias = (string) ($args['allias'] ?? '');
        $name = (string) ($args['name'] ?? '');
        if (!in_array($domain, $allowedDomains, true)) {
            throw new \RuntimeException("Security violation: Domain '{$domain}' is not in the allowed list.");
        }
        $r = runCli(
            'page:read --domain=' . escapeshellarg($domain)
            . ' --allias=' . escapeshellarg($allias)
            . ' --name=' . escapeshellarg($name),
        );
        respond($r, $role, $command, $args);
    }

    if ($command === 'page:list') {
        $domain = (string) ($args['domain'] ?? '');
        $allias = (string) ($args['allias'] ?? '');
        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain) || str_contains($domain, '..')) {
            throw new \RuntimeException('Invalid domain.');
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $allias)) {
            throw new \RuntimeException('Invalid alias.');
        }
        respond(
            runCliJson('page:list --domain=' . escapeshellarg($domain) . ' --allias=' . escapeshellarg($allias)),
            $role,
            $command,
            $args,
        );
    }

    if ($command === 'scheme:list') {
        $domain = (string) ($args['domain'] ?? '');
        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain) || str_contains($domain, '..')) {
            throw new \RuntimeException('Invalid domain.');
        }
        respond(runCliJson('scheme:list --domain=' . escapeshellarg($domain)), $role, $command, $args);
    }

    if ($command === 'block:list') {
        $domain = (string) ($args['domain'] ?? '');
        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain) || str_contains($domain, '..')) {
            throw new \RuntimeException('Invalid domain.');
        }
        respond(runCliJson('block:list --domain=' . escapeshellarg($domain)), $role, $command, $args);
    }

    if ($command === 'skin:new') {
        $domain = (string) ($args['domain'] ?? '');
        $allias = (string) ($args['allias'] ?? '');
        $name = (string) ($args['name'] ?? 'default');
        if (!in_array($domain, $allowedDomains, true)) {
            throw new \RuntimeException("Security violation: Domain '{$domain}' is not in the allowed list.");
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $allias) || !preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            throw new \RuntimeException('Invalid allias or skin name.');
        }
        $r = runCli(
            'skin:new --domain=' . escapeshellarg($domain)
            . ' --allias=' . escapeshellarg($allias)
            . ' --name=' . escapeshellarg($name),
        );
        respond($r, $role, $command, $args);
    }

    if ($command === 'skin:list') {
        $domain = (string) ($args['domain'] ?? '');
        $allias = (string) ($args['allias'] ?? '');
        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain) || str_contains($domain, '..')) {
            throw new \RuntimeException('Invalid domain.');
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $allias)) {
            throw new \RuntimeException('Invalid allias.');
        }
        respond(
            runCliJson('skin:list --domain=' . escapeshellarg($domain) . ' --allias=' . escapeshellarg($allias)),
            $role,
            $command,
            $args,
        );
    }

    if ($command === 'skin:edit') {
        $domain = (string) ($args['domain'] ?? '');
        $allias = (string) ($args['allias'] ?? '');
        $name = (string) ($args['name'] ?? '');
        $file = (string) ($args['file'] ?? '');
        $content = isset($args['content']) ? (string) $args['content'] : '';
        $contentB64 = isset($args['content_b64']) ? (string) $args['content_b64'] : '';
        if (!in_array($domain, $allowedDomains, true)) {
            throw new \RuntimeException("Security violation: Domain '{$domain}' is not in the allowed list.");
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $allias) || !preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            throw new \RuntimeException('Invalid allias or skin name.');
        }
        if (!in_array($file, ['css/main.css', 'js/main.js'], true)) {
            throw new \RuntimeException('Invalid file (allowed: css/main.css, js/main.js).');
        }
        $safeFile = escapeshellarg($file);
        $contentArg = $contentB64 !== ''
            ? ' --content_b64=' . escapeshellarg($contentB64)
            : ' --content=' . escapeshellarg($content);
        $r = runCli(
            'skin:edit --domain=' . escapeshellarg($domain)
            . ' --allias=' . escapeshellarg($allias)
            . ' --name=' . escapeshellarg($name)
            . ' --file=' . $safeFile
            . $contentArg,
        );
        respond($r, $role, $command, $args);
    }

    if ($command === 'filler:list') {
        $domain = (string) ($args['domain'] ?? '');
        $allias = (string) ($args['allias'] ?? '');
        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain) || str_contains($domain, '..')) {
            throw new \RuntimeException('Invalid domain.');
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $allias)) {
            throw new \RuntimeException('Invalid alias.');
        }
        respond(
            runCliJson('filler:list --domain=' . escapeshellarg($domain) . ' --allias=' . escapeshellarg($allias)),
            $role,
            $command,
            $args,
        );
    }

    if ($command === 'net:status') {
        if ($ainet === null) {
            respond(['ok' => false, 'error' => $ainetError, 'hint' => 'Fix config/ainet.config'], $role, $command, $args);
        }
        respond($ainet->status(), $role, $command, $args);
    }

    if ($command === 'image:search') {
        if ($ainet === null) {
            respond(['ok' => false, 'error' => $ainetError, 'hint' => 'Fix config/ainet.config'], $role, $command, $args);
        }
        $query = (string) ($args['query'] ?? '');
        $count = (int) ($args['count'] ?? 5);
        $provider = isset($args['provider']) && is_string($args['provider']) ? $args['provider'] : null;
        respond($ainet->searchImages($query, $count, $provider), $role, $command, $args);
    }

    if ($command === 'fetch') {
        if ($ainet === null) {
            respond(['ok' => false, 'error' => $ainetError, 'hint' => 'Fix config/ainet.config'], $role, $command, $args);
        }
        $url = (string) ($args['url'] ?? '');
        $type = (string) ($args['type'] ?? 'json');
        $result = ($type === 'text') ? $ainet->fetchText($url) : $ainet->fetchJson($url);
        respond($result, $role, $command, $args);
    }

    if ($command === 'image:download') {
        if ($ainet === null) {
            respond(['ok' => false, 'error' => $ainetError, 'hint' => 'Fix config/ainet.config'], $role, $command, $args);
        }
        $url = (string) ($args['url'] ?? '');
        $domain = (string) ($args['domain'] ?? '');
        $allias = (string) ($args['allias'] ?? 'www');
        $category = (string) ($args['category'] ?? 'general');
        $alt = (string) ($args['alt'] ?? '');

        if (!in_array($domain, $allowedDomains, true)) {
            respond([
                'ok' => false,
                'error' => "Security violation: Domain '{$domain}' is not in the allowed list.",
                'hint' => 'Add domain to config/agent.config allowed_domains',
            ], $role, $command, $args);
        }

        $dl = $ainet->downloadImage($url, $domain, $allias, $category, $alt);
        if (($dl['ok'] ?? false) !== true) {
            respond($dl, $role, $command, $args);
        }

        $data = $dl['data'];
        $bytes = (string) $data['bytes'];
        $mime = (string) $data['mime'];
        $ext = (string) $data['ext'];
        $hash = (string) $data['hash'];

        $tmpDir = __DIR__ . '/../var/tmp';
        if (!is_dir($tmpDir) && !@mkdir($tmpDir, 0o775, true) && !is_dir($tmpDir)) {
            respond(['ok' => false, 'error' => 'Cannot create var/tmp', 'hint' => 'Check write permissions'], $role, $command, $args);
        }
        $tmpFile = $tmpDir . '/content_' . $hash . '_' . bin2hex(random_bytes(4)) . '.bin';
        if (@file_put_contents($tmpFile, $bytes) === false) {
            respond(['ok' => false, 'error' => 'Cannot write tmp file', 'hint' => 'Check var/tmp permissions'], $role, $command, $args);
        }

        try {
            $cmd = 'php ' . __DIR__ . '/cli.php content:store'
                . ' --domain=' . escapeshellarg($domain)
                . ' --allias=' . escapeshellarg($allias)
                . ' --category=' . escapeshellarg($category)
                . ' --data_file=' . escapeshellarg($tmpFile)
                . ' --mime=' . escapeshellarg($mime)
                . ' --alt=' . escapeshellarg($alt)
                . ' --source_url=' . escapeshellarg($url)
                . ' 2>&1';
            $output = shell_exec($cmd);
            $outputStr = trim((string) $output);
        } finally {
            @unlink($tmpFile);
        }

        $storeResult = parseCliResult($outputStr);
        if (!$storeResult['ok']) {
            respond([
                'ok' => false,
                'error' => 'content:store failed',
                'hint' => $storeResult['hint'],
                'context' => ['cli_output' => $outputStr],
            ], $role, $command, $args);
        }

        $localUrl = '/content/' . $domain . '/' . $allias . '/' . $category . '/' . $hash . '.' . $ext;
        respond([
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
            ],
        ], $role, $command, $args);
    }

    respond([
        'ok' => false,
        'error' => "Command '{$command}' not implemented.",
        'hint' => 'See ai:start for available commands',
    ], $role, $command, $args);
} catch (\Throwable $e) {
    respond([
        'ok' => false,
        'error' => $e->getMessage(),
        'hint' => 'Unexpected error',
    ], $role, $command, $args);
}
