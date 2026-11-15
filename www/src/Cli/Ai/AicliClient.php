<?php

declare(strict_types=1);

namespace Damoony\Cli\Ai;

/**
 * Клиент для вызова bin/cli.php из AI-handlers.
 *
 * Копия runCli/runCliJson из bin/aicli.php (временно).
 * В Phase 0.5 — заменит соответствующие функции в bin/aicli.php.
 */
final class AicliClient
{
    private const TIMEOUT_SECONDS = 120;

    public function __construct(private readonly string $root)
    {
    }

    /**
     * @return array{ok: bool, output: string, error: string, hint: string}
     */
    public function run(string $args): array
    {
        // Linux (prod): proc_open работает стабильно через mpm_event/mpm_prefork
        // Windows (dev): proc_open/shell_exec ломают mpm_winnt (AH02965),
        // поэтому запускаем CLI через "start /B" — процесс стартует вне Apache

        // Под Nginx PHP_BINARY указывает на php-cgi.exe, но для CLI нужен php.exe
        $phpBinary = PHP_BINARY;
        if (str_contains($phpBinary, 'php-cgi')) {
            $phpBinary = str_replace('php-cgi', 'php', $phpBinary);
        }
        $cmd = escapeshellarg($phpBinary)
            . ' ' . escapeshellarg($this->root . '/bin/cli.php')
            . ' ' . $args;

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = proc_open($cmd, $descriptors, $pipes, $this->root);

        if (!is_resource($proc)) {
            return ['ok' => false, 'error' => 'Failed to start CLI process', 'hint' => '', 'output' => ''];
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $output = '';
        $start = time();

        while (true) {
            $status = proc_get_status($proc);
            $output .= stream_get_contents($pipes[1]);
            $output .= stream_get_contents($pipes[2]);

            if (!$status['running']) {
                break;
            }

            if (time() - $start > self::TIMEOUT_SECONDS) {
                proc_terminate($proc, 9);
                return ['ok' => false, 'error' => 'CLI timeout', 'hint' => '', 'output' => $output];
            }

            usleep(100000);
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);

        return $this->parseOutput(trim($output));
    }


    /**
     * @return array<string, mixed>
     */
    public function runJson(string $args): array
    {
        // Используем run() (proc_open) вместо shell_exec (который часто недоступен на Windows)
        $cliOutput = $this->run($args . ' --json');
        $trimmed = trim($cliOutput['output']);

        if ($trimmed === '') {
            return ['ok' => false, 'data' => [], 'error' => 'empty output', 'hint' => 'CLI returned nothing'];
        }

        // Ищем валидный JSON от CLI (он всегда начинается с {"ok":)
        if (preg_match('/(\{"ok":.*\})/s', $trimmed, $matches)) {
            $trimmed = $matches[1];
        }

        // Извлекаем JSON из возможного мусора (warnings, deprecations)
        $start = strpos($trimmed, '{');
        $end = strrpos($trimmed, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $trimmed = substr($trimmed, $start, $end - $start + 1);
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

    /**
     * @return array{ok: bool, output: string, error: string, hint: string}
     */
    private function parseOutput(string $output): array
    {
        $isError = str_contains($output, 'FAILED')
            || str_contains($output, 'Ошибки:')
            || str_contains($output, '✗ ')
            || $output === '';

        return $isError
            ? [
                'ok' => false,
                'output' => $output,
                'error' => $output,
                'hint' => 'Read output; if "already exists" — skip and continue',
            ]
            : ['ok' => true, 'output' => $output, 'error' => '', 'hint' => ''];
    }
}
