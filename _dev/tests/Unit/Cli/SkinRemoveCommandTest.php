<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\SkinNewCommand;
use Damoony\Cli\Commands\SkinRemoveCommand;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Files\Paths;
use PHPUnit\Framework\TestCase;

final class SkinRemoveCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_skin_rm_' . bin2hex(random_bytes(4));
        mkdir($this->root . '/config', 0o777, true);
        file_put_contents($this->root . '/config/site.config', json_encode([
            'domains_real' => ['example.ru' => 'example.ru'],
            'alliases_real' => ['example.ru' => ['www' => 'www']],
        ]));
        mkdir($this->root . '/app/example.ru/www', 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->rmrf($this->root);
    }

    private function rmrf(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $e) {
            if ($e === '.' || $e === '..') {
                continue;
            }
            $p = $dir . '/' . $e;
            is_dir($p) ? $this->rmrf($p) : @unlink($p);
        }
        @rmdir($dir);
    }

    private function ctx(): Context
    {
        return new Context(static fn () => null);
    }

    private function makeSkin(string $name): void
    {
        (new SkinNewCommand(new Paths($this->root)))
            ->run(['domain' => 'example.ru', 'allias' => 'www', 'name' => $name], $this->ctx());
    }

    private function execute(array $args): Result
    {
        return (new SkinRemoveCommand(new Paths($this->root)))->run($args, $this->ctx());
    }

    public function testRemovesToTrash(): void
    {
        $this->makeSkin('default');
        $dir = $this->root . '/www/media/example.ru/www/skin/default';
        self::assertDirectoryExists($dir);

        $res = $this->execute([
            'domain' => 'example.ru', 'allias' => 'www', 'name' => 'default', 'force' => '1',
        ]);
        self::assertTrue($res->ok, implode('; ', $res->errors));
        self::assertDirectoryDoesNotExist($dir);

        $trash = glob($this->root . '/www/media/.trash/example.ru_www_skin_default_trash_*');
        self::assertNotEmpty($trash);
        self::assertDirectoryExists($trash[0] . '/default');
    }

    public function testRejectsWithoutForce(): void
    {
        $this->makeSkin('default');
        $res = $this->execute([
            'domain' => 'example.ru', 'allias' => 'www', 'name' => 'default',
        ]);
        self::assertFalse($res->ok);
        self::assertStringContainsString('--force', $res->errors[0]);
    }

    public function testIdempotentOnMissing(): void
    {
        $res = $this->execute([
            'domain' => 'example.ru', 'allias' => 'www', 'name' => 'ghost', 'force' => '1',
        ]);
        self::assertTrue($res->ok);
        self::assertNotEmpty($res->warnings);
    }

    public function testUpdatesConfigOnActiveRemoval(): void
    {
        $this->makeSkin('default');
        $this->makeSkin('dark');

        // active = default (первый созданный)
        $this->execute([
            'domain' => 'example.ru', 'allias' => 'www', 'name' => 'default', 'force' => '1',
        ]);

        $site = json_decode((string) file_get_contents($this->root . '/config/site.config'), true);
        // fallback на dark
        self::assertSame('dark', $site['alliases_skins']['example.ru']['www']);
        self::assertNotContains('default', $site['alliases_skins_allowed']['example.ru']['www']);
        self::assertContains('dark', $site['alliases_skins_allowed']['example.ru']['www']);
    }
}
