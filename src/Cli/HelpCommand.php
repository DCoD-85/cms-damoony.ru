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
        $commandName = (string) ($args['command'] ?? '');

        if ($commandName !== '') {
            $command = $this->registry->get($commandName);
            if ($command === null) {
                $r->error('Команда не найдена: ' . $commandName);
                return $r;
            }
            $r->step(sprintf('%-20s %s', $command->name(), $command->description()));
            $r->data = [
                'command' => $command->name(),
                'description' => $command->description(),
            ];
            return $r;
        }

        $r->step('Damoony CLI');
        $commands = $this->registry->all();
        usort($commands, static fn (CommandInterface $a, CommandInterface $b): int => strcmp($a->name(), $b->name()));

        $data = [];
        foreach ($commands as $command) {
            $r->step(sprintf('  %-20s %s', $command->name(), $command->description()));
            $data[] = [
                'name' => $command->name(),
                'description' => $command->description(),
            ];
        }
        $r->data = ['commands' => $data];

        return $r;
    }
}
