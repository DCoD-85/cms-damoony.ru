<?php

declare(strict_types=1);

namespace Damoony\Ai\Aicli;

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
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . $this->root . '/bin/cli.php ' . $args . ' 2>&1';
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

                return ['ok' => false, 'error' => 'CLI timeout', 'hint' => 'Command took too long', 'output' => $output];
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
        $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . $this->root . '/bin/cli.php ' . $args . ' --json 2>&1');
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
