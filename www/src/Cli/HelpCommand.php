<?php

declare(strict_types=1);

namespace Damoony\Cli;

final class HelpCommand implements CommandInterface
{
    public function __construct(private readonly CommandRegistry $registry)
    {
    }

    public function name(): string
    {
        return 'help';
    }

    public function description(): string
    {
        return 'Список команд';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();
        $r->step('Damoony CLI');

        foreach ($this->registry->all() as $command) {
            $r->step(sprintf('  %-20s %s', $command->name(), $command->description()));
        }

        return $r;
    }
}
