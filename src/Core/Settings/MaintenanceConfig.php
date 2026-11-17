<?php

declare(strict_types=1);

namespace Damoony\Core\Settings;

use Damoony\Cli\Support\BackupArchive;
use JsonException;
use RuntimeException;

/**
 * Настройки обслуживания (config/maintenance.config).
 *
 * Используются как дефолты CLI-команд: флаги команды перекрывают конфиг.
 * Файла нет — работают встроенные значения, ничего не ломается.
 */
final class MaintenanceConfig
{
    private const DEFAULT_TASKS = [
        'cleanup' => true,
        'content_gc' => false,
        'backup' => true,
        'structure' => true,
        'health' => true,
    ];

    private const DEFAULTS = [
        'backups' => ['keep' => 7, 'parts' => ['app', 'content', 'media', 'db'], 'compress_media' => false],
        'trash' => ['ttl_days' => 0],
        'sessions' => ['ttl_hours' => 24],
        'content' => ['gc_ttl_days' => 30, 'max_mb' => 25, 'min_side_px' => 200],
        'logs' => ['cli_keep_days' => 14],
        'ratelimit' => ['storage_keep_days' => 7],
        'cron' => [
            'lock_dir' => 'var/locks',
            'tasks' => self::DEFAULT_TASKS,
            'backup_domains' => [],
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
            throw new RuntimeException("maintenance.config не читается: {$path}");
        }

        return self::fromJson($raw);
    }

    public static function fromJson(string $json): self
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('maintenance.config: некорректный JSON: ' . $e->getMessage(), 0, $e);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('maintenance.config: ожидался JSON-объект');
        }

        /** @var array<string, mixed> $decoded */
        return self::fromArray($decoded);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $merged = [];
        foreach (self::DEFAULTS as $section => $defaults) {
            $given = $data[$section] ?? [];
            if (!is_array($given)) {
                throw new RuntimeException("maintenance.config: {$section} должен быть объектом");
            }
            $merged[$section] = $defaults;

            foreach ($defaults as $key => $default) {
                if (!array_key_exists($key, $given)) {
                    continue;
                }
                $merged[$section][$key] = self::validate($section, $key, $given[$key], $default);
            }
        }

        return new self($merged);
    }

    public function backupsKeep(): int
    {
        return (int) $this->data['backups']['keep'];
    }

    /**
     * @return list<string>
     */
    public function backupParts(): array
    {
        /** @var list<string> $parts */
        $parts = $this->data['backups']['parts'];

        return $parts;
    }

    public function compressMedia(): bool
    {
        return (bool) $this->data['backups']['compress_media'];
    }

    public function trashTtlDays(): int
    {
        return (int) $this->data['trash']['ttl_days'];
    }

    public function sessionsTtlHours(): int
    {
        return (int) $this->data['sessions']['ttl_hours'];
    }

    public function contentGcTtlDays(): int
    {
        return (int) $this->data['content']['gc_ttl_days'];
    }

    public function contentMaxBytes(): int
    {
        return (int) $this->data['content']['max_mb'] * 1024 * 1024;
    }

    public function contentMinSidePx(): int
    {
        return (int) $this->data['content']['min_side_px'];
    }

    public function cliLogsKeepDays(): int
    {
        return (int) $this->data['logs']['cli_keep_days'];
    }

    public function ratelimitKeepDays(): int
    {
        return (int) $this->data['ratelimit']['storage_keep_days'];
    }

    public function cronLockDir(): string
    {
        return (string) $this->data['cron']['lock_dir'];
    }

    /**
     * @return array<string, bool>
     */
    public function cronTasks(): array
    {
        /** @var array<string, bool> $tasks */
        $tasks = $this->data['cron']['tasks'];

        return $tasks;
    }

    /**
     * @return list<string>
     */
    public function cronBackupDomains(): array
    {
        /** @var list<string> $domains */
        $domains = $this->data['cron']['backup_domains'];

        return $domains;
    }

    private static function validate(string $section, string $key, mixed $value, mixed $default): mixed
    {
        if ($section === 'cron' && $key === 'lock_dir') {
            if (!is_string($value) || trim($value) === '') {
                throw new RuntimeException('maintenance.config: cron.lock_dir должен быть непустой строкой');
            }

            return $value;
        }

        if ($section === 'cron' && $key === 'tasks') {
            if (!is_array($value)) {
                throw new RuntimeException('maintenance.config: cron.tasks должен быть объектом');
            }

            $tasks = self::DEFAULT_TASKS;
            foreach ($value as $name => $enabled) {
                if (!is_string($name) || !array_key_exists($name, $tasks)) {
                    throw new RuntimeException('maintenance.config: неизвестная cron-задача ' . (string) $name);
                }
                if (!is_bool($enabled)) {
                    throw new RuntimeException('maintenance.config: cron.tasks.' . $name . ' должен быть boolean');
                }
                $tasks[$name] = $enabled;
            }

            return $tasks;
        }

        if ($section === 'cron' && $key === 'backup_domains') {
            if (!is_array($value)) {
                throw new RuntimeException('maintenance.config: cron.backup_domains должен быть массивом');
            }

            $domains = [];
            foreach ($value as $domain) {
                if (!is_string($domain) || trim($domain) === '') {
                    throw new RuntimeException('maintenance.config: cron.backup_domains — только непустые строки');
                }
                $domains[] = $domain;
            }

            return $domains;
        }

        if (is_bool($default)) {
            if (!is_bool($value)) {
                throw new RuntimeException("maintenance.config: {$section}.{$key} должен быть boolean");
            }

            return $value;
        }

        if (is_int($default)) {
            if (!is_int($value) || $value < 0) {
                throw new RuntimeException("maintenance.config: {$section}.{$key} должен быть целым ≥ 0");
            }

            return $value;
        }

        if (is_array($default)) {
            if (!is_array($value) || $value === []) {
                throw new RuntimeException("maintenance.config: {$section}.{$key} должен быть непустым массивом");
            }

            $parts = [];
            foreach ($value as $part) {
                if (!is_string($part) || !in_array($part, BackupArchive::PARTS, true)) {
                    throw new RuntimeException(
                        "maintenance.config: {$section}.{$key} — допустимо: " . implode(', ', BackupArchive::PARTS),
                    );
                }
                $parts[$part] = true;
            }

            $out = [];
            foreach (BackupArchive::PARTS as $part) {
                if (isset($parts[$part])) {
                    $out[] = $part;
                }
            }

            return $out;
        }

        throw new RuntimeException("maintenance.config: неподдерживаемый тип {$section}.{$key}");
    }
}
