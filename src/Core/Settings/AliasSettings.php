<?php

declare(strict_types=1);

namespace Damoony\Core\Settings;

/**
 * Алиас проекта из site.config: alliases.{projectKey}.{alias}.
 */
final class AliasSettings
{
    /**
     * @param list<string> $langs
     */
    public function __construct(
        public readonly string $name,
        public readonly bool $available,
        public readonly array $langs,
        public readonly ?string $stub = null,
    ) {
    }

    public function defaultLang(string $fallback = 'ru'): string
    {
        return $this->langs[0] ?? $fallback;
    }

    /**
     * Заглушка алиаса или доменная (fallback).
     */
    public function stubOr(string $fallback): string
    {
        return $this->stub ?? $fallback;
    }
}
