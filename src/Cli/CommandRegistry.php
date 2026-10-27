<?php

declare(strict_types=1);

namespace Damoony\Cli;

final class CommandRegistry
{
    /** @var array<string, CommandInterface> */
    private array $commands = [];

    public function add(CommandInterface $command): self
    {
        $this->commands[$command->name()] = $command;
        return $this;
    }

    public function get(string $name): ?CommandInterface
    {
        return $this->commands[$name] ?? null;
    }

    /** @return list<CommandInterface> */
    public function all(): array
    {
        return array_values($this->commands);
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->commands);
    }
}
