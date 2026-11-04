<?php

declare(strict_types=1);

namespace Damoony\Ai\Aicli;

/**
 * Базовый класс для AI-handlers.
 *
 * Даёт доступ к AicliClient, root (для чтения config) и allowedDomains
 * (из config/agent.config — для проверки домена).
 */
abstract class AbstractHandler implements AicliHandlerInterface
{
    /**
     * @param list<string> $allowedDomains
     */
    public function __construct(
        protected readonly AicliClient $client,
        protected readonly string $root,
        protected readonly array $allowedDomains = [],
    ) {
    }

    /**
     * @param array<string, mixed> $args
     */
    protected function stringArg(array $args, string $key, string $default = ''): string
    {
        $value = $args[$key] ?? $default;

        return is_string($value) ? $value : $default;
    }

    /**
     * @param array<string, mixed> $args
     */
    protected function intArg(array $args, string $key, int $default = 0): int
    {
        $value = $args[$key] ?? $default;

        return is_int($value) ? $value : (int) (is_string($value) ? $value : $default);
    }

    /**
     * @param array<string, mixed> $args
     *
     * @return array<mixed>
     */
    protected function arrayArg(array $args, string $key): array
    {
        $value = $args[$key] ?? [];

        return is_array($value) ? $value : [];
    }

    protected function requireDomain(string $domain): void
    {
        if (!in_array($domain, $this->allowedDomains, true)) {
            throw new \RuntimeException(
                "Security violation: Domain '{$domain}' is not in the allowed list.",
            );
        }
    }

    protected function validateDomain(string $domain): void
    {
        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain) || str_contains($domain, '..')) {
            throw new \RuntimeException('Invalid domain.');
        }
    }

    protected function validateAllias(string $allias): void
    {
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $allias)) {
            throw new \RuntimeException('Invalid alias name.');
        }
    }
}
