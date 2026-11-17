<?php

declare(strict_types=1);

namespace Damoony\Cli;

use Damoony\Cli\Identity\CliLogger;
use Throwable;

final class Application
{
    public function __construct(
        private readonly CommandRegistry $registry,
        private readonly ArgvParser $parser,
        private readonly Renderer $renderer,
        private readonly ?CliLogger $logger = null,
        private readonly string $user = '',
    ) {
    }

    /** @param list<string> $argv */
    public function run(array $argv): int
    {
        $started = microtime(true);

        $parsed = $this->parser->parse($argv);
        $command = $parsed['command'] === '' ? 'help' : $parsed['command'];

        // help <cmd>: первый positional — имя команды
        if ($command === 'help' && !isset($parsed['args']['command']) && $parsed['positional'] !== []) {
            $parsed['args']['command'] = $parsed['positional'][0];
        }

        $jsonMode = isset($parsed['args']['json']) || in_array('--json', $argv, true);

        $handler = $this->registry->get($command);
        if ($handler === null) {
            $r = new Result();
            $r->error('Команда не найдена: ' . $command);
            echo $jsonMode ? $this->renderer->renderJson($r) : $this->renderer->render($r);
            $this->log($command, $parsed['args'], $r, $started);

            return 1;
        }

        $isVerbose = isset($parsed['args']['verbose']) || in_array('v', $parsed['flags'], true) || in_array('verbose', $parsed['flags'], true);
        $isDryRun = isset($parsed['args']['dry-run']) || in_array('dry-run', $parsed['flags'], true);

        $contextResult = new Result();
        $context = new Context(static function (string $m, bool $ok = true, string $d = '') use ($contextResult): void {
            $contextResult->step($m, $ok, $d);
        }, $isVerbose, $isDryRun);

        try {
            $result = $handler->run($parsed['args'], $context);
        } catch (Throwable $e) {
            $result = new Result();
            $result->error($e->getMessage());
        }

        if ($contextResult->steps !== []) {
            $result->steps = array_merge($contextResult->steps, $result->steps);
        }
        if (!$contextResult->ok) {
            $result->ok = false;
        }

        echo $jsonMode ? $this->renderer->renderJson($result) : $this->renderer->render($result);

        $this->log($command, $parsed['args'], $result, $started);

        return $result->ok ? 0 : 1;
    }

    /**
     * Журнал не должен ломать выполнение команды.
     *
     * @param array<string, string> $args
     */
    private function log(string $command, array $args, Result $result, float $started): void
    {
        if ($this->logger === null) {
            return;
        }

        try {
            $this->logger->log($this->user, $command, $args, $result, (microtime(true) - $started) * 1000);
        } catch (Throwable) {
            // осознанно: отказ записи в журнал не отменяет результат команды
        }
    }
}
