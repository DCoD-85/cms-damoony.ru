<?php

declare(strict_types=1);

namespace Damoony\Core\Settings;

/**
 * Описание проекта из site.config: domains_app.{key}.
 *
 * Один проект = одна папка в app/ (folder) + один PHP-namespace домена (namespace)
 * + набор входных хостов (mirrors). Алиасы проекта живут в alliases.{key}.
 */
final class ProjectSettings
{
    /**
     * @param list<string> $langs
     * @param array<string, array{db: string, prefix: string}> $aliasesStorage
     * @param array<string, bool> $mirrors host => available
     * @param array<string, AliasSettings> $aliases
     */
    public function __construct(
        public readonly string $key,
        public readonly string $folder,
        public readonly string $namespace,
        public readonly string $scheme,
        public readonly string $stub,
        public readonly string $defaultAlias,
        public readonly array $langs,
        private readonly array $aliasesStorage,
        private readonly array $mirrors,
        private readonly array $aliases,
    ) {
    }

    /** @return array<string, bool> */
    public function mirrors(): array
    {
        return $this->mirrors;
    }

    /** @return list<string> */
    public function hostNames(): array
    {
        return array_keys($this->mirrors);
    }

    public function isHostAvailable(string $host): bool
    {
        return $this->mirrors[$host] ?? false;
    }

    /** @return array<string, AliasSettings> */
    public function aliases(): array
    {
        return $this->aliases;
    }

    /** @return list<string> */
    public function aliasNames(): array
    {
        return array_keys($this->aliases);
    }

    public function hasAlias(string $alias): bool
    {
        return isset($this->aliases[$alias]);
    }

    public function alias(string $alias): ?AliasSettings
    {
        return $this->aliases[$alias] ?? null;
    }

    /** @return list<string> */
    public function aliasLangs(string $alias): array
    {
        $settings = $this->alias($alias);

        return $settings === null ? [] : $settings->langs;
    }

    public function isAliasAvailable(string $alias): bool
    {
        $settings = $this->alias($alias);

        return $settings !== null && $settings->available;
    }

    /**
     * Префикс таблиц алиаса из aliases_storage (пустая строка, если не задан).
     */
    public function storagePrefix(string $alias): string
    {
        return $this->aliasesStorage[$alias]['prefix'] ?? '';
    }

    /**
     * БД алиаса из aliases_storage (пустая строка, если не задана).
     */
    public function storageDb(string $alias): string
    {
        return $this->aliasesStorage[$alias]['db'] ?? '';
    }
}
