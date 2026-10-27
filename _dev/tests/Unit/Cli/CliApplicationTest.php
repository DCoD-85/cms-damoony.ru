<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Application;
use Damoony\Cli\ArgvParser;
use Damoony\Cli\CommandInterface;
use Damoony\Cli\CommandRegistry;
use Damoony\Cli\Context;
use Damoony\Cli\HelpCommand;
use Damoony\Cli\Renderer;
use Damoony\Cli\Result;
use PHPUnit\Framework\TestCase;

final class CliApplicationTest extends TestCase
{
    public function testHelpListsCommands(): void
    {
        $registry = new CommandRegistry();
        $registry->add(new HelpCommand($registry));

        ob_start();
        $code = (new Application($registry, new ArgvParser(), new Renderer()))
            ->run(['cli', 'help']);
        $out = (string) ob_get_clean();

        self::assertSame(0, $code);
        self::assertStringContainsString('Damoony CLI', $out);
        self::assertStringContainsString('help', $out);
        self::assertStringContainsString('OK', $out);
    }

    public function testUnknownCommandFails(): void
    {
        $registry = new CommandRegistry();

        ob_start();
        $code = (new Application($registry, new ArgvParser(), new Renderer()))
            ->run(['cli', 'nope']);
        $out = (string) ob_get_clean();

        self::assertSame(1, $code);
        self::assertStringContainsString('Команда не найдена', $out);
        self::assertStringContainsString('FAILED', $out);
    }

    public function testCustomCommandRuns(): void
    {
        $cmd = new class () implements CommandInterface {
            public function name(): string
            {
                return 'greet';
            }

            public function description(): string
            {
                return 'Greets';
            }

            public function run(array $args, Context $context): Result
            {
                $r = new Result();
                $context->step('hello ' . ($args['name'] ?? 'world'));
                return $r;
            }
        };

        $registry = new CommandRegistry();
        $registry->add($cmd);

        ob_start();
        $code = (new Application($registry, new ArgvParser(), new Renderer()))
            ->run(['cli', 'greet', '--name=John']);
        $out = (string) ob_get_clean();

        self::assertSame(0, $code);
        self::assertStringContainsString('hello John', $out);
    }

    public function testCommandThrowableIsCaught(): void
    {
        $cmd = new class () implements CommandInterface {
            public function name(): string
            {
                return 'boom';
            }

            public function description(): string
            {
                return 'Boom';
            }

            public function run(array $args, Context $context): Result
            {
                throw new \RuntimeException('bad');
            }
        };

        $registry = new CommandRegistry();
        $registry->add($cmd);

        ob_start();
        $code = (new Application($registry, new ArgvParser(), new Renderer()))
            ->run(['cli', 'boom']);
        $out = (string) ob_get_clean();

        self::assertSame(1, $code);
        self::assertStringContainsString('bad', $out);
    }

    public function testArgvParser(): void
    {
        $parsed = (new ArgvParser())->parse(['cli', 'greet', '--name=John', '--force', 'a', 'b']);
        self::assertSame('greet', $parsed['command']);
        self::assertSame('John', $parsed['args']['name']);
        self::assertSame('1', $parsed['args']['force']);
        self::assertSame(['force'], $parsed['flags']);
        self::assertSame(['a', 'b'], $parsed['positional']);
    }
}
