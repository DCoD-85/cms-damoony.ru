<?php

declare(strict_types=1);

namespace Damoony\Cli;

final class Application
{
    public function __construct(
        private readonly CommandRegistry $registry,
        private readonly ArgvParser $parser,
        private readonly Renderer $renderer,
    ) {
    }

    /** @param list<string> $argv */
    public function run(array $argv): int
    {
        $parsed = $this->parser->parse($argv);
        $command = $parsed['command'] === '' ? 'help' : $parsed['command'];

        $handler = $this->registry->get($command);
        if ($handler === null) {
            $r = new Result();
            $r->error('Команда не найдена: ' . $command);
            echo $this->renderer->render($r);
            return 1;
        }

        $contextResult = new Result();
        $context = new Context(static function (string $m, bool $ok = true, string $d = '') use ($contextResult): void {
            $contextResult->step($m, $ok, $d);
        });

        try {
            $result = $handler->run($parsed['args'], $context);
        } catch (\Throwable $e) {
            $result = new Result();
            $result->error($e->getMessage());
        }

        if ($contextResult->steps !== []) {
            $result->steps = array_merge($contextResult->steps, $result->steps);
        }
        if (!$contextResult->ok) {
            $result->ok = false;
        }

        echo $this->renderer->render($result);

        return $result->ok ? 0 : 1;
    }
}