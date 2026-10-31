<?php

declare(strict_types=1);

$input = stream_get_contents(STDIN);
$request = json_decode($input, true);

if (!is_array($request) || !isset($request['command'])) {
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON request']);
    exit(1);
}

$command = $request['command'];
$args = $request['args'] ?? [];

// Загружаем конфиг агента
$agentConfigFile = __DIR__ . '/../config/agent.config';
$allowedDomains = [];
if (is_file($agentConfigFile)) {
    $agentConfig = json_decode(file_get_contents($agentConfigFile), true);
    $allowedDomains = $agentConfig['allowed_domains'] ?? [];
}

$allowedCommands = ['read_test_file', 'engine_status', 'filler_toggle', 'domain:new', 'allias:new', 'page:new'];

if (!in_array($command, $allowedCommands, true)) {
    echo json_encode(['ok' => false, 'error' => "Command '{$command}' is not allowed."]);
    exit(1);
}

try {
    if ($command === 'read_test_file') {
        $filename = $args['filename'] ?? '';
        if (str_contains($filename, '..') || str_contains($filename, '/')) {
            throw new \RuntimeException('Invalid filename.');
        }
        $filePath = __DIR__ . '/../var/' . basename($filename);
        if (!is_file($filePath)) {
            throw new \RuntimeException("File '{$filename}' not found.");
        }
        echo json_encode(['ok' => true, 'data' => ['content' => file_get_contents($filePath)]]);
    } 
    
    elseif ($command === 'engine_status') {
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
                'data' => [
                    'errors_count' => $errorCount,
                    'raw_output' => $text
                ]
            ]);
        }
    }

    elseif ($command === 'filler_toggle') {
        $domain = $args['domain'] ?? '';
        $allias = $args['allias'] ?? '';
        $block = $args['block'] ?? '';
        $value = $args['value'] ?? '0';
        
        if (!in_array($domain, $allowedDomains, true)) {
            throw new \RuntimeException("Domain '{$domain}' is not allowed.");
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $allias) || !preg_match('/^[a-z][a-z0-9_]*$/', $block)) {
            throw new \RuntimeException('Invalid arguments for filler_toggle.');
        }

        $output = shell_exec("php " . __DIR__ . "/cli.php filler:toggle --domain={$domain} --allias={$allias} --block={$block} --value={$value} 2>&1");
        echo json_encode(['ok' => true, 'data' => ['output' => trim((string)$output)]]);
    }

    elseif ($command === 'domain:new') {
        $domain = $args['domain'] ?? '';
        
        if (!in_array($domain, $allowedDomains, true)) {
            throw new \RuntimeException("Security violation: Domain '{$domain}' is not in the allowed list.");
        }

        $safeDomain = escapeshellarg($domain);
        $output = shell_exec("php " . __DIR__ . "/cli.php domain:new --domain={$safeDomain} 2>&1");
        echo json_encode(['ok' => true, 'data' => ['output' => trim((string)$output)]]);
    }

    elseif ($command === 'allias:new') {
        $domain = $args['domain'] ?? '';
        $name = $args['name'] ?? '';
        
        if (!in_array($domain, $allowedDomains, true)) {
            throw new \RuntimeException("Security violation: Domain '{$domain}' is not in the allowed list.");
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            throw new \RuntimeException("Invalid alias name.");
        }

        $safeName = escapeshellarg($name);
        $safeDomain = escapeshellarg($domain);
        $output = shell_exec("php " . __DIR__ . "/cli.php allias:new --domain={$safeDomain} --name={$safeName} 2>&1");
        echo json_encode(['ok' => true, 'data' => ['output' => trim((string)$output)]]);
    }

    elseif ($command === 'page:new') {
        $domain = $args['domain'] ?? '';
        $allias = $args['allias'] ?? '';
        $name = $args['name'] ?? '';
        
        if (!in_array($domain, $allowedDomains, true)) {
            throw new \RuntimeException("Security violation: Domain '{$domain}' is not in the allowed list.");
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $allias) || !preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            throw new \RuntimeException("Invalid alias or page name.");
        }

        $safeDomain = escapeshellarg($domain);
        $safeAllias = escapeshellarg($allias);
        $safeName = escapeshellarg($name);
        
        $output = shell_exec("php " . __DIR__ . "/cli.php page:new --domain={$safeDomain} --allias={$safeAllias} --name={$safeName} 2>&1");
        echo json_encode(['ok' => true, 'data' => ['output' => trim((string)$output)]]);
    }
    
} catch (\Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit(1);
}