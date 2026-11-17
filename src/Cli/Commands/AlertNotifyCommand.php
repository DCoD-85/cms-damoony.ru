<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\ObservabilityConfig;
use Throwable;

/**
 * alert:notify — записать алерт в файл и (если настроено) отправить webhook.
 *
 * Не бросает исключений: канал недоступен → warning, но команда завершается ok,
 * если хотя бы один канал сработал.
 */
final class AlertNotifyCommand implements CommandInterface
{
    public function __construct(
        private readonly Paths $paths,
        private readonly ObservabilityConfig $observability,
    ) {
    }

    public function name(): string
    {
        return 'alert:notify';
    }

    public function description(): string
    {
        return 'Отправить алерт (--message, --level, --source) в файл и webhook';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $message = trim((string) ($args['message'] ?? ''));
        if ($message === '') {
            $r->error('Укажите --message=...');

            return $r;
        }

        $level = (string) ($args['level'] ?? 'warning');
        $source = (string) ($args['source'] ?? 'cli');

        $alert = [
            'ts' => gmdate('Y-m-d\TH:i:s\Z'),
            'level' => $level,
            'source' => $source,
            'message' => $message,
        ];

        $channels = [];

        $file = $this->alertFile();
        try {
            Fs::mkdir(dirname($file));
            $line = json_encode($alert, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($line === false || @file_put_contents($file, $line . "\n", FILE_APPEND) === false) {
                $r->warn('Не удалось записать алерт в файл: ' . Fs::rel($file, $this->paths->root()));
            } else {
                $channels[] = 'file';
            }
        } catch (Throwable $e) {
            $r->warn('Файл алертов недоступен: ' . $e->getMessage());
        }

        $webhook = $this->observability->alertsWebhook();
        if ($webhook !== null) {
            $sent = $this->postWebhook($webhook, $alert);
            if ($sent) {
                $channels[] = 'webhook';
            } else {
                $r->warn('Webhook не ответил успешно: ' . $webhook);
            }
        }

        if ($channels === []) {
            $r->error('Алерт не доставлен ни одним каналом');

            return $r;
        }

        $r->data = ['alert' => $alert, 'channels' => $channels];
        $context->step('Алерт доставлен: ' . implode(', ', $channels), true, $level);

        return $r;
    }

    private function alertFile(): string
    {
        $file = $this->observability->alertsFile();
        if (str_starts_with($file, '/') || preg_match('/^[a-zA-Z]:[\\\\\/]/', $file) === 1) {
            return $file;
        }

        return $this->paths->root() . '/' . ltrim($file, '/\\');
    }

    /** @param array<string, string> $alert */
    private function postWebhook(string $url, array $alert): bool
    {
        $payload = json_encode($alert, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            return false;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $payload,
                'timeout' => 5,
                'ignore_errors' => true,
            ],
        ]);

        $result = @file_get_contents($url, false, $context);

        return $result !== false;
    }
}
