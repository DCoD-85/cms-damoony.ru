<?php

declare(strict_types=1);

namespace Damoony\Ai;

final class ModelRouter
{
    private const DEFAULT_MODEL = 'qwen2.5-coder:7b';

    /** @var array<string, mixed>|null */
    private ?array $cache = null;

    public function __construct(private readonly string $root)
    {
    }

    /**
     * Возвращает имя модели для конкретной роли.
     * Приоритет: roles[role] → default → DEFAULT_MODEL.
     */
    public function resolve(string $role): string
    {
        $cfg = $this->loadConfig();

        if (isset($cfg['roles']) && is_array($cfg['roles'])
            && isset($cfg['roles'][$role]) && is_string($cfg['roles'][$role])
        ) {
            return $cfg['roles'][$role];
        }

        if (isset($cfg['default']) && is_string($cfg['default']) && $cfg['default'] !== '') {
            return $cfg['default'];
        }

        return self::DEFAULT_MODEL;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadConfig(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $file = $this->root . '/config/models.config';
        if (!is_file($file)) {
            return $this->cache = [];
        }

        $raw = file_get_contents($file);
        if ($raw === false) {
            return $this->cache = [];
        }

        $decoded = json_decode($raw, true);

        return $this->cache = is_array($decoded) ? $decoded : [];
    }
}
