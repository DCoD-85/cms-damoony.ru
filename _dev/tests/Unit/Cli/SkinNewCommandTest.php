<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\SkinNewCommand;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Files\Paths;
use PHPUnit\Framework\TestCase;

final class SkinNewCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_skin_' . bin2hex(random_bytes(4));
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

    private function execute(array $args): Result
    {
        return new SkinNewCommand(new Paths($this->root))
            ->run($args, new Context(static fn () => null));
    }

    public function testCreatesSkin(): void
    {
        $res = $this->execute(['domain' => 'example.ru', 'allias' => 'www', 'name' => 'default']);
        self::assertTrue($res->ok, implode('; ', $res->errors));

        $dir = $this->root . '/www/media/example.ru/www/skin/default';
        self::assertDirectoryExists($dir . '/css');
        self::assertDirectoryExists($dir . '/js');
        self::assertDirectoryExists($dir . '/img');
        self::assertFileExists($dir . '/css/main.css');
        self::assertFileExists($dir . '/js/main.js');
        self::assertFileExists($dir . '/skin.json');

        $site = json_decode((string) file_get_contents($this->root . '/config/site.config'), true);
        self::assertSame('default', $site['alliases_skins']['example.ru']['www']);
        self::assertContains('default', $site['alliases_skins_allowed']['example.ru']['www']);
    }

    public function testRejectsDuplicate(): void
    {
        $this->execute(['domain' => 'example.ru', 'allias' => 'www', 'name' => 'default']);
        $res = $this->execute(['domain' => 'example.ru', 'allias' => 'www', 'name' => 'default']);
        self::assertFalse($res->ok);
    }

    public function testRejectsInvalidName(): void
    {
        $res = $this->execute(['domain' => 'example.ru', 'allias' => 'www', 'name' => 'Bad-Name']);
        self::assertFalse($res->ok);
    }

    public function testRejectsMissingAllias(): void
    {
        $res = $this->execute(['domain' => 'example.ru', 'allias' => 'ghost', 'name' => 'default']);
        self::assertFalse($res->ok);
    }

    public function testRejectsInvalidDomain(): void
    {
        $res = $this->execute(['domain' => 'a..ru', 'allias' => 'www', 'name' => 'default']);
        self::assertFalse($res->ok);
    }

    public function testSecondSkinDoesNotOverrideActive(): void
    {
        $this->execute(['domain' => 'example.ru', 'allias' => 'www', 'name' => 'default']);
        $this->execute(['domain' => 'example.ru', 'allias' => 'www', 'name' => 'dark']);

        $site = json_decode((string) file_get_contents($this->root . '/config/site.config'), true);
        self::assertSame('default', $site['alliases_skins']['example.ru']['www']);
        self::assertContains('dark', $site['alliases_skins_allowed']['example.ru']['www']);
    }
}
