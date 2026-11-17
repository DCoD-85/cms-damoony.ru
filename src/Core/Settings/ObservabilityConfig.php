<?php

declare(strict_types=1);

namespace Damoony\Core\Settings;

use JsonException;
use RuntimeException;

/**
 * Настройки наблюдаемости (config/observability.config).
 *
 * Необязательный файл: нет — работают дефолты. Битый JSON/типы — явная ошибка.
 */
final class ObservabilityConfig
{
    private const DEFAULTS = [
        'health' => ['backup_max_age_days' => 2, 'disk_min_free_mb' => 1024],
        'metrics' => ['enabled' => true, 'sample' => 1, 'keep_days' => 14, 'token' => ''],
        'alerts' => [
            'file' => 'var/alerts.log',
            'webhook' => null,
            'dedup_minutes' => 60,
            'errors_5xx_per_5m' => 10,
        ],
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
            $merged['health'][$key] = self::intOrFail($health[$key], 0, 'health.' . $key);
        }

        $metrics = $data['metrics'] ?? [];
        if (!is_array($metrics)) {
            throw new RuntimeException('observability.config: metrics должен быть объектом');
        }
        if (array_key_exists('enabled', $metrics)) {
            if (!is_bool($metrics['enabled'])) {
                throw new RuntimeException('observability.config: metrics.enabled должен быть boolean');
            }
            $merged['metrics']['enabled'] = $metrics['enabled'];
        }
        if (array_key_exists('sample', $metrics)) {
            $merged['metrics']['sample'] = self::intOrFail($metrics['sample'], 1, 'metrics.sample');
        }
        if (array_key_exists('keep_days', $metrics)) {
            $merged['metrics']['keep_days'] = self::intOrFail($metrics['keep_days'], 0, 'metrics.keep_days');
        }
        if (array_key_exists('token', $metrics)) {
            if (!is_string($metrics['token'])) {
                throw new RuntimeException('observability.config: metrics.token должен быть строкой');
            }
            $merged['metrics']['token'] = $metrics['token'];
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
        if (array_key_exists('dedup_minutes', $alerts)) {
            $merged['alerts']['dedup_minutes'] = self::intOrFail($alerts['dedup_minutes'], 0, 'alerts.dedup_minutes');
        }
        if (array_key_exists('errors_5xx_per_5m', $alerts)) {
            $merged['alerts']['errors_5xx_per_5m'] = self::intOrFail(
                $alerts['errors_5xx_per_5m'],
                0,
                'alerts.errors_5xx_per_5m',
            );
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

    public function metricsEnabled(): bool
    {
        return (bool) $this->data['metrics']['enabled'];
    }

    public function metricsSample(): int
    {
        return (int) $this->data['metrics']['sample'];
    }

    public function metricsKeepDays(): int
    {
        return (int) $this->data['metrics']['keep_days'];
    }

    public function metricsToken(): string
    {
        return (string) $this->data['metrics']['token'];
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

    public function alertsDedupMinutes(): int
    {
        return (int) $this->data['alerts']['dedup_minutes'];
    }

    public function errorsThreshold5xx(): int
    {
        return (int) $this->data['alerts']['errors_5xx_per_5m'];
    }

    private static function intOrFail(mixed $value, int $min, string $field): int
    {
        if (!is_int($value) || $value < $min) {
            throw new RuntimeException("observability.config: {$field} должен быть целым ≥ {$min}");
        }

        return $value;
    }
}
