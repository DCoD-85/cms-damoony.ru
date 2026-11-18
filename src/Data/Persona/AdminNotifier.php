<?php

declare(strict_types=1);

namespace Damoony\Data\Persona;

use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\ObservabilityConfig;
use Throwable;

/**
 * Уведомление админа о заявке: пишет в тот же канал алертов, что alert:notify.
 */
final class AdminNotifier
{
    public function __construct(
        private readonly Paths $paths,
        private readonly ?ObservabilityConfig $config = null,
    ) {
    }

    public function notify(string $message, string $level = 'info', string $source = 'persona'): void
    {
        try {
            $config = $this->config ?? ObservabilityConfig::fromFile(
                $this->paths->root() . '/config/observability.config',
            );
            $file = $config->alertsFile();
            $isAbsolute = str_starts_with($file, '/') || preg_match('/^[a-zA-Z]:[\\\\\/]/', $file) === 1;
            $path = $isAbsolute ? $file : $this->paths->root() . '/' . ltrim($file, '/\\');

            Fs::mkdir(dirname($path));
            $line = json_encode([
                'ts' => gmdate('Y-m-d\TH:i:s\Z'),
                'level' => $level,
                'source' => $source,
                'message' => $message,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if ($line !== false) {
                @file_put_contents($path, $line . "\n", FILE_APPEND);
            }
        } catch (Throwable) {
            // уведомление не должно ломать заявку
        }
    }
}
