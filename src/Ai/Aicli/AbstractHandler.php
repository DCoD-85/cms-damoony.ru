<?php

declare(strict_types=1);

namespace Damoony\Ai\Aicli;

/**
 * Базовый класс для AI-handlers.
 *
 * Даёт доступ к AicliClient и минимальные хелперы для типизации args.
 */
abstract class AbstractHandler implements AicliHandlerInterface
{
    public function __construct(protected readonly AicliClient $client)
    {
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
}
