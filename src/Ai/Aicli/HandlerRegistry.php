<?php

declare(strict_types=1);

namespace Damoony\Ai\Aicli;

/**
 * Реестр AI-handlers: точные команды + prefix-команды (wizard:*).
 */
final class HandlerRegistry
{
    /** @var array<string, AicliHandlerInterface> */
    private array $exact = [];

    /** @var array<string, AicliHandlerInterface> */
    private array $prefixes = [];

    public function add(AicliHandlerInterface $handler): void
    {
        foreach ($handler->commands() as $cmd) {
            if (str_ends_with($cmd, ':*')) {
                $this->prefixes[substr($cmd, 0, -2)] = $handler;
            } else {
                $this->exact[$cmd] = $handler;
            }
        }
    }

    public function find(string $command): ?AicliHandlerInterface
    {
        if (isset($this->exact[$command])) {
            return $this->exact[$command];
        }

        foreach ($this->prefixes as $prefix => $handler) {
            if (str_starts_with($command, $prefix . ':')) {
                return $handler;
            }
        }

        return null;
    }

    public function has(string $command): bool
    {
        return $this->find($command) !== null;
    }

    /**
     * @return list<string>
     */
    public function commands(): array
    {
        $prefixes = array_map(
            static fn (string $p): string => $p . ':*',
            array_keys($this->prefixes),
        );

        return array_values(array_merge(array_keys($this->exact), $prefixes));
    }
}
