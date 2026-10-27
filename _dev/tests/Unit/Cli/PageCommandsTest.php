<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\PageNewCommand;
use Damoony\Cli\Commands\PageToggleCommand;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Files\Paths;
use PHPUnit\Framework\TestCase;

final class PageCommandsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_pg_' . bin2hex(random_bytes(4));
        mkdir($this->root . '/config', 0o777, true);
        file_put_contents($this->root . '/config/site.config', json_encode([
            'domains_real' => ['example.ru' => 'example.ru'],
            'domains_available' => ['example.ru' => true],
            'domains_namespace' => ['example.ru' => 'example'],
            'alliases_real' => ['example.ru' => ['www' => 'www']],
        ]));
        $algoritm = $this->root . '/app/example.ru/www/algoritm';
        mkdir($algoritm, 0o777, true);
        file_put_contents($algoritm . '/.rules.json', json_encode(['available' => ['']]));
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
        foreach (scandir($dir) ?: [] as $i) {
            if ($i === '.' || $i === '..') {
                continue;
            }
            $p = $dir . '/' . $i;
            is_dir($p) ? $this->rmrf($p) : @unlink($p);
        }
        @rmdir($dir);
    }

    private function ctx(Result $r): Context
    {
        return new Context(static function (string $m, bool $ok = true, string $d = '') use ($r): void {
            $r->step($m, $ok, $d);
        });
    }

    public function testS08CreatePage(): void
    {
        $r = new Result();
        $res = (new PageNewCommand(new Paths($this->root)))
            ->run(['domain' => 'example.ru', 'allias' => 'www', 'name' => 'about'], $this->ctx($r));

        self::assertTrue($res->ok);
        $dir = $this->root . '/app/example.ru/www/algoritm/about';
        self::assertDirectoryExists($dir);
        self::assertFileExists($dir . '/algoritm_about.php');
        self::assertFileExists($dir . '/.rules.json');

        $rules = json_decode((string) file_get_contents($dir . '/.rules.json'), true);
        self::assertSame(['about'], $rules['available']);

        $parent = json_decode((string) file_get_contents($this->root . '/app/example.ru/www/algoritm/.rules.json'), true);
        self::assertContains('about', $parent['available']);
    }

    public function testS08RejectsDuplicate(): void
    {
        $cmd = new PageNewCommand(new Paths($this->root));
        $cmd->run(['domain' => 'example.ru', 'allias' => 'www', 'name' => 'about'], $this->ctx(new Result()));
        $res = $cmd->run(['domain' => 'example.ru', 'allias' => 'www', 'name' => 'about'], $this->ctx(new Result()));
        self::assertFalse($res->ok);
    }

    public function testS08RejectsMissingAllias(): void
    {
        $res = (new PageNewCommand(new Paths($this->root)))
            ->run(['domain' => 'example.ru', 'allias' => 'ghost', 'name' => 'about'], $this->ctx(new Result()));
        self::assertFalse($res->ok);
    }

    public function testS09ToggleAuth(): void
    {
        $paths = new Paths($this->root);
        (new PageNewCommand($paths))->run(['domain' => 'example.ru', 'allias' => 'www', 'name' => 'about'], $this->ctx(new Result()));

        $res = (new PageToggleCommand($paths))
            ->run(['domain' => 'example.ru', 'allias' => 'www', 'path' => 'about', 'key' => 'auth', 'value' => '1'], $this->ctx(new Result()));

        self::assertTrue($res->ok);
        $rules = json_decode((string) file_get_contents($this->root . '/app/example.ru/www/algoritm/about/.rules.json'), true);
        self::assertTrue($rules['auth']);
        self::assertTrue($rules['search']);
        self::assertFalse($rules['default']);
    }

    public function testS09RejectsBadKey(): void
    {
        $res = (new PageToggleCommand(new Paths($this->root)))
            ->run(['domain' => 'example.ru', 'allias' => 'www', 'path' => '', 'key' => 'weird'], $this->ctx(new Result()));
        self::assertFalse($res->ok);
    }

    public function testS09RejectsMissingPage(): void
    {
        $res = (new PageToggleCommand(new Paths($this->root)))
            ->run(['domain' => 'example.ru', 'allias' => 'www', 'path' => 'ghost', 'key' => 'auth'], $this->ctx(new Result()));
        self::assertFalse($res->ok);
    }
}
