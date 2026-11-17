<?php

declare(strict_types=1);

namespace Damoony\Core\Settings;

use JsonException;
use RuntimeException;

/**
 * Настройки наблюдаемости (config/observability.config).
 *
 * Необязательный файл: нет — работают дефолты. Чего здесь нет — метрик и каналов
 * алертов кроме webhook/файла (фаза P1/P2).
 */
final class ObservabilityConfig
{
    private const DEFAULTS = [
        'health' => ['backup_max_age_days' => 2, 'disk_min_free_mb' => 1024],
        'alerts' => ['file' => 'var/alerts.log', 'webhook' => null],
    ];

    /** @param array<string, mixed> $data */
    private function __construct(private readonly array $data)
    {
    }

    public static function defaults(): self
    {
        return new self(self::DEFAULTS);
    }

    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            return self::defaults();
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException("observability.config не читается: {$path}");
        }

        return self::fromJson($raw);
    }

    public static function fromJson(string $json): self
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('observability.config: некорректный JSON: ' . $e->getMessage(), 0, $e);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('observability.config: ожидался JSON-объект');
        }

        /** @var array<string, mixed> $decoded */
        return self::fromArray($decoded);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $merged = self::DEFAULTS;

        $health = $data['health'] ?? [];
        if (!is_array($health)) {
            throw new RuntimeException('observability.config: health должен быть объектом');
        }
        foreach (['backup_max_age_days', 'disk_min_free_mb'] as $key) {
            if (!array_key_exists($key, $health)) {
                continue;
            }
            if (!is_int($health[$key]) || $health[$key] < 0) {
                throw new RuntimeException("observability.config: health.{$key} должен быть целым ≥ 0");
            }
            $merged['health'][$key] = $health[$key];
        }

        $alerts = $data['alerts'] ?? [];
        if (!is_array($alerts)) {
            throw new RuntimeException('observability.config: alerts должен быть объектом');
        }
        if (array_key_exists('file', $alerts)) {
            if (!is_string($alerts['file']) || trim($alerts['file']) === '') {
                throw new RuntimeException('observability.config: alerts.file должен быть непустой строкой');
            }
            $merged['alerts']['file'] = $alerts['file'];
        }
        if (array_key_exists('webhook', $alerts)) {
            $webhook = $alerts['webhook'];
            if ($webhook !== null && (!is_string($webhook) || preg_match('#^https?://#i', $webhook) !== 1)) {
                throw new RuntimeException('observability.config: alerts.webhook должен быть http(s)-URL или null');
            }
            $merged['alerts']['webhook'] = $webhook;
        }

        return new self($merged);
    }

    public function backupMaxAgeDays(): int
    {
        return (int) $this->data['health']['backup_max_age_days'];
    }

    public function diskMinFreeMb(): int
    {
        return (int) $this->data['health']['disk_min_free_mb'];
    }

    public function alertsFile(): string
    {
        return (string) $this->data['alerts']['file'];
    }

    public function alertsWebhook(): ?string
    {
        $webhook = $this->data['alerts']['webhook'];

        return is_string($webhook) && $webhook !== '' ? $webhook : null;
    }
}
