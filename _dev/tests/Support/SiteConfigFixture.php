<?php

declare(strict_types=1);

namespace Damoony\Tests\Support;

use Damoony\Core\Settings\SiteConfig;

/**
 * Фикстуры site.config для тестов движка (схема domains_app).
 */
final class SiteConfigFixture
{
    /**
     * Один проект + алиасы с одинаковыми языками.
     *
     * @param list<string> $aliases
     * @param list<string> $langs
     */
    public static function single(
        string $domain = 'example.ru',
        array $aliases = ['www'],
        bool $available = true,
        string $stub = 'maintenance',
        string $scheme = 'www',
        array $langs = ['ru'],
        ?string $namespace = null,
    ): SiteConfig {
        $aliasMap = [];
        foreach ($aliases as $alias) {
            $aliasMap[$alias] = ['available' => true, 'langs' => $langs];
        }

        return SiteConfig::fromArray([
            'domains_app' => [
                $domain => [
                    'folder' => $domain,
                    'namespace' => $namespace ?? str_replace(['.', '-'], '_', $domain),
                    'scheme' => $scheme,
                    'stub' => $stub,
                    'langs' => $langs,
                    'mirrors' => [$domain => ['available' => $available]],
                ],
            ],
            'alliases' => [$domain => $aliasMap],
        ]);
    }

    /**
     * Legacy-представление для контейнера ('site'): страницы читают только alliases.
     *
     * @param list<string> $aliases
     * @param list<string> $langs
     *
     * @return array<string, mixed>
     */
    public static function legacyView(string $domain = 'example.ru', array $aliases = ['www'], array $langs = ['ru']): array
    {
        $aliasMap = [];
        foreach ($aliases as $alias) {
            $aliasMap[$alias] = ['available' => true, 'langs' => $langs];
        }

        return ['alliases' => [$domain => $aliasMap]];
    }
}
