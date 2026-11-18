<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\LangCheckCommand;
use Damoony\Cli\Context;
use Damoony\Core\Files\Paths;
use PHPUnit\Framework\TestCase;

final class LangCheckTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_lang_' . bin2hex(random_bytes(6));
        $dir = $this->root . '/stubs/assemblies/demo';
        mkdir($dir . '/app/.i18nglobal', 0o775, true);

        file_put_contents($dir . '/assembly.json', json_encode([
            'name' => 'Demo',
            'base_lang' => 'ru',
            'aliases' => ['www'],
            'langs' => [
                'project' => ['ru', 'en'],
                'default' => 'ru',
                'aliases' => ['www' => ['ru', 'en']],
            ],
        ]));

        file_put_contents($dir . '/app/.i18nglobal/ru.json', json_encode(['a' => 'А', 'b' => 'Б']));
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    public function testCompleteTranslationPasses(): void
    {
        file_put_contents($this->root . '/stubs/assemblies/demo/app/.i18nglobal/en.json', json_encode(['a' => 'A', 'b' => 'B']));

        $result = new LangCheckCommand(new Paths($this->root))->run([], $this->context());

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertSame(0, $result->data['missing']);
    }

    public function testMissingKeysReportedAndStrictFails(): void
    {
        file_put_contents($this->root . '/stubs/assemblies/demo/app/.i18nglobal/en.json', json_encode(['a' => 'A']));

        $command = new LangCheckCommand(new Paths($this->root));

        $soft = $command->run([], $this->context());
        self::assertTrue($soft->ok);
        self::assertSame(1, $soft->data['missing']);
        self::assertStringContainsString('b', implode(' ', $soft->warnings));

        $strict = $command->run(['strict' => '1'], $this->context());
        self::assertFalse($strict->ok);
    }

    public function testTodoValuesReportedAndStrictFails(): void
    {
        file_put_contents(
            $this->root . '/stubs/assemblies/demo/app/.i18nglobal/en.json',
            json_encode(['a' => 'TODO: А', 'b' => 'B']),
        );

        $command = new LangCheckCommand(new Paths($this->root));

        $soft = $command->run([], $this->context());
        self::assertTrue($soft->ok);
        self::assertSame(1, $soft->data['todo']);

        $strict = $command->run(['strict' => '1'], $this->context());
        self::assertFalse($strict->ok);
    }

    public function testMissingBaseFileFails(): void
    {
        unlink($this->root . '/stubs/assemblies/demo/app/.i18nglobal/ru.json');

        $result = new LangCheckCommand(new Paths($this->root))->run([], $this->context());

        self::assertFalse($result->ok);
        self::assertStringContainsString('базового перевода', implode(' ', $result->errors));
    }

    private function context(): Context
    {
        return new Context(static function (string $m, bool $ok = true, string $d = ''): void {
        });
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
