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
}
