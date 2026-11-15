<?php

declare(strict_types=1);

namespace Damoony\Ainet;

/**
 * Whitelist доменов для AINET. Точный матч (без wildcard).
 * Загружается из config/ainet.config при инициализации.
 */
final class DomainWhitelist
{
    /** @var array<string, true> */
    private array $domains = [];

    /** @param list<string> $domains */
    public function __construct(array $domains)
    {
        foreach ($domains as $d) {
            $d = strtolower(trim($d));
            if ($d === '' || !$this->isValidDomainName($d)) {
                continue;
            }
            $this->domains[$d] = true;
        }
    }

    /** @param array<string, mixed> $config */
    public static function fromConfig(array $config): self
    {
        $list = $config['allowed_domains'] ?? [];
        if (!is_array($list)) {
            return new self([]);
        }

        /** @var list<string> $list */
        return new self($list);
    }

    public function isAllowed(string $host): bool
    {
        $host = strtolower(trim($host));
        if ($host === '') {
            return false;
        }

        return isset($this->domains[$host]);
    }

    /** @return list<string> */
    public function all(): array
    {
        return array_keys($this->domains);
    }

    public function count(): int
    {
        return count($this->domains);
    }

    private function isValidDomainName(string $d): bool
    {
        // Простой regex: a-z0-9, дефис в середине, точки между label
        return (bool) preg_match(
            '/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/',
            $d,
        );
    }
}
