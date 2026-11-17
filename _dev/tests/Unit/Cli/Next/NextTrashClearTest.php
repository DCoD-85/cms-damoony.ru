<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli\Next;

use Damoony\Cli\Context;
use Damoony\Cli\Next\Commands\TrashClearCommand;
use Damoony\Cli\Next\Support\TrashScanner;
use Damoony\Core\Files\Paths;
use PHPUnit\Framework\TestCase;

final class NextTrashClearTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_cli_trash_' . bin2hex(random_bytes(6));
        mkdir($this->root . '/app/damoony.ru/www', 0o775, true);
        mkdir($this->root . '/app/damoony.ru/.schemesglobal', 0o775, true);
        mkdir($this->root . '/app/damoony.ru/.blocksglobal', 0o775, true);
        mkdir($this->root . '/www/media/.trash', 0o775, true);
        mkdir($this->root . '/content/.trash', 0o775, true);
        mkdir($this->root . '/var/trash', 0o775, true);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    public function testFindsAllKindsOfTrash(): void
    {
        $now = time();

        // 1. проект
        mkdir($this->root . '/app/old.ru_trash_' . $now);
        // 2. алиас
        mkdir($this->root . '/app/damoony.ru/blog_trash_' . $now);
        // 3. схема
        mkdir($this->root . '/app/damoony.ru/.schemesglobal/www_trash_' . $now);
        // 4. блок
        mkdir($this->root . '/app/damoony.ru/.blocksglobal/top_trash_' . $now);
        // 5. сессия
        mkdir($this->root . '/app/_trash_sess_abcdef12_damoony.ru_' . $now);
        // 6. media
        mkdir($this->root . '/www/media/.trash/damoony.ru_www_default_trash_' . $now);
        // 7. content
        mkdir($this->root . '/content/.trash/damoony.ru_www_general_abcdef0123456789_trash_' . $now);
        // 8. языки
        mkdir($this->root . '/var/trash/lang_damoony.ru_www_trash_' . $now);

        $result = $this->command()->run([], $this->context());

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertSame(8, $result->data['found']);
        self::assertFalse($result->data['force']);

        $kinds = array_column($result->data['items'], 'kind');
        sort($kinds);
        self::assertSame(
            ['allias', 'block', 'content', 'domain', 'lang', 'media', 'scheme', 'session'],
            $kinds,
        );
    }

    public function testDryRunKeepsTrash(): void
    {
        $dir = $this->root . '/app/old.ru_trash_' . time();
        mkdir($dir);

        $this->command()->run([], $this->context());

        self::assertDirectoryExists($dir);
    }

    public function testForceRemovesTrash(): void
    {
        $dir = $this->root . '/app/old.ru_trash_' . time();
        mkdir($dir);
        file_put_contents($dir . '/x.txt', 'data');

        $result = $this->command()->run(['force' => '1'], $this->context());

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertDirectoryDoesNotExist($dir);
        self::assertSame(1, $result->data['found']);
    }

    public function testOlderFilterSkipsFresh(): void
    {
        mkdir($this->root . '/app/fresh.ru_trash_' . time());
        mkdir($this->root . '/app/old.ru_trash_' . (time() - 10 * 86400));

        $result = $this->command()->run(['older' => '7d'], $this->context());

        self::assertTrue($result->ok);
        self::assertSame(1, $result->data['found']);
        self::assertSame('old.ru_trash_', substr(basename((string) $result->data['items'][0]['path']), 0, 13));
    }

    public function testDomainFilter(): void
    {
        mkdir($this->root . '/www/media/.trash/damoony.ru_www_trash_' . time());
        mkdir($this->root . '/www/media/.trash/other.ru_www_trash_' . time());

        $result = $this->command()->run(['domain' => 'damoony.ru'], $this->context());

        self::assertTrue($result->ok);
        self::assertSame(1, $result->data['found']);
    }

    public function testInvalidOlderRejected(): void
    {
        $result = $this->command()->run(['older' => 'soon'], $this->context());

        self::assertFalse($result->ok);
        self::assertStringContainsString('--older', $result->errors[0]);
    }

    public function testEmptyTrashIsHarmless(): void
    {
        $result = $this->command()->run(['force' => '1'], $this->context());

        self::assertTrue($result->ok);
        self::assertSame(0, $result->data['found']);
    }

    private function command(): TrashClearCommand
    {
        $paths = new Paths($this->root);

        return new TrashClearCommand($paths, new TrashScanner($paths));
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
