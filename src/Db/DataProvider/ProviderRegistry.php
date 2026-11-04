<?php

declare(strict_types=1);

namespace Damoony\Db\DataProvider;

/**
 * Реестр провайдеров для (domain, alias, name).
 *
 * Ключ: "{domain}|{alias}|{name}" — быстрый поиск.
 * В Phase 1 — только core-провайдеры. Custom (app/[domain]/[alias]/provider/) — Phase 5.
 */
final class ProviderRegistry
{
    /** @var array<string, ProviderInterface> */
    private array $providers = [];

    public function add(ProviderInterface $provider): void
    {
        $key = $this->key($provider->domain(), $provider->alias(), $provider->name());
        $this->providers[$key] = $provider;
    }

    public function find(string $domain, string $alias, string $name): ?ProviderInterface
    {
        return $this->providers[$this->key($domain, $alias, $name)] ?? null;
    }

    public function has(string $domain, string $alias, string $name): bool
    {
        return $this->find($domain, $alias, $name) !== null;
    }

    /**
     * Все провайдеры для (domain, alias).
     *
     * @return list<ProviderInterface>
     */
    public function forAlias(string $domain, string $alias): array
    {
        $prefix = $domain . '|' . $alias . '|';
        $out = [];
        foreach ($this->providers as $key => $provider) {
            if (str_starts_with($key, $prefix)) {
                $out[] = $provider;
            }
        }

        return $out;
    }

    private function key(string $domain, string $alias, string $name): string
    {
        return $domain . '|' . $alias . '|' . $name;
    }
}
