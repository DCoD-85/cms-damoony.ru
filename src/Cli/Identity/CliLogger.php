<?php

declare(strict_types=1);

namespace Damoony\Cli\Identity;

use Damoony\Cli\Result;
use RuntimeException;

/**
 * Журнал запусков CLI: var/log/cli/YYYY-MM-DD.log (JSONL, одна строка = запуск).
 *
 * Секреты редактируются перед записью: CLI — это про учёт, а не про утечку паролей.
 */
final class CliLogger
{
    private const REDACT_KEYS = [
        'password',
        'pass',
        'secret',
        'token',
        'admin-sess',
        'admin_sess',
        'data_b64',
        'content_b64',
        'json_b64',
        'params_b64',
        'args_b64',
    ];

    public function __construct(private readonly string $root)
    {
    }

    public function dir(): string
    {
        return $this->root . '/var/log/cli';
    }

    /**
     * @param array<string, string> $args
     */
    public function log(string $user, string $command, array $args, Result $result, float $durationMs): void
    {
        $entry = [
            'ts' => gmdate('Y-m-d\TH:i:s\Z'),
            'user' => $user === '' ? 'owner' : $user,
            'command' => $command,
            'args' => $this->redact($args),
            'ok' => $result->ok,
            'errors' => $result->errors,
            'duration_ms' => round($durationMs, 1),
        ];

        $dir = $this->dir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0o775, true);
        }

        $file = $dir . '/' . gmdate('Y-m-d') . '.log';
        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

        if ($line === false || @file_put_contents($file, $line . "\n", FILE_APPEND) === false) {
            throw new RuntimeException('Не удалось записать журнал CLI: ' . $file);
        }
    }

    /**
     * @param array<string, string> $args
     *
     * @return array<string, string>
     */
    private function redact(array $args): array
    {
        $out = [];
        foreach ($args as $key => $value) {
            $normalized = strtolower($key);
            if (in_array($normalized, self::REDACT_KEYS, true) || str_contains($normalized, 'pass')) {
                $out[$key] = '***';

                continue;
            }
            $out[$key] = $value;
        }

        return $out;
    }
}
