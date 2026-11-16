<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\CommandRegistry;
use Damoony\Cli\Context;
use Damoony\Cli\HelpCommand;
use Damoony\Tests\Unit\Cli\Stubs\FakeCommand;
use PHPUnit\Framework\TestCase;

final class HelpCommandTest extends TestCase
{
    public function testListsAllCommandsSortedAlphabetically(): void
    {
        $registry = new CommandRegistry();
        $registry->add(new FakeCommand('zeta', 'Z'));
        $registry->add(new FakeCommand('alpha', 'A'));
        $registry->add(new FakeCommand('beta', 'B'));

        $result = new HelpCommand($registry)->run([], $this->ctx());

        self::assertTrue($result->ok);
        self::assertArrayHasKey('commands', $result->data);
        $names = array_column($result->data['commands'], 'name');
        self::assertSame(['alpha', 'beta', 'zeta'], $names);
    }

    public function testShowsCommandDetails(): void
    {
        $registry = new CommandRegistry();
        $registry->add(new FakeCommand('page:new', 'Создать страницу'));

        $result = new HelpCommand($registry)->run(['command' => 'page:new'], $this->ctx());

        self::assertTrue($result->ok);
        self::assertSame('page:new', $result->data['command']);
        self::assertSame('Создать страницу', $result->data['description']);
        self::assertCount(1, $result->steps);
    }

    public function testUnknownCommandReturnsError(): void
    {
        $registry = new CommandRegistry();

        $result = new HelpCommand($registry)->run(['command' => 'nonexistent'], $this->ctx());

        self::assertFalse($result->ok);
        self::assertStringContainsString('не найдена', $result->errors[0]);
        self::assertArrayNotHasKey('command', $result->data ?? []);
    }

    private function ctx(): Context
    {
        return new Context(static fn (): null => null);
    }
}
