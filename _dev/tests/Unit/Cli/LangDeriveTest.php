<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\LangDeriveCommand;
use Damoony\Cli\Context;
use Damoony\Core\Files\Paths;
use PHPUnit\Framework\TestCase;

final class LangDeriveTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_derive_' . bin2hex(random_bytes(6));
        $dir = $this->root . '/stubs/assemblies/demo/app/.i18nglobal';
        mkdir($dir, 0o775, true);

        file_put_contents($this->root . '/stubs/assemblies/demo/assembly.json', json_encode([
            'name' => 'Demo',
            'base_lang' => 'ru',
            'aliases' => ['www'],
            'langs' => [
                'project' => ['ru', 'en'],
                'default' => 'ru',
                'aliases' => ['www' => ['ru', 'en']],
            ],
        ]));

        file_put_contents($dir . '/ru.json', json_encode(['a' => 'А', 'b' => 'Б']));
        file_put_contents($dir . '/en.json', json_encode(['a' => 'A']));
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    public function testAddsMissingKeysAsTodo(): void
    {
        $result = new LangDeriveCommand(new Paths($this->root))->run(
            ['assembly' => 'demo', 'to' => 'en'],
            $this->context(),
        );

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertSame(1, $result->data['added']);

        $en = json_decode((string) file_get_contents($this->root . '/stubs/assemblies/demo/app/.i18nglobal/en.json'), true);
        self::assertSame('A', $en['a']);
        self::assertSame('TODO: Б', $en['b']);
    }

    public function testRejectsUnknownLanguage(): void
    {
        $result = new LangDeriveCommand(new Paths($this->root))->run(
            ['assembly' => 'demo', 'to' => 'de'],
            $this->context(),
        );

        self::assertFalse($result->ok);
    }

    private function context(): Context
    {
        return new Context(static function (string $m, bool $ok = true, string $d = ''): void {
        });
    }

    private function remove(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->remove($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
