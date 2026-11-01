<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\SkinEditCommand;
use Damoony\Cli\Commands\SkinNewCommand;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Files\Paths;
use PHPUnit\Framework\TestCase;

final class SkinEditCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_skin_edit_' . bin2hex(random_bytes(4));
        mkdir($this->root . '/config', 0o777, true);
        file_put_contents($this->root . '/config/site.config', json_encode([
            'domains_real' => ['example.ru' => 'example.ru'],
            'alliases_real' => ['example.ru' => ['www' => 'www']],
        ]));
        mkdir($this->root . '/app/example.ru/www', 0o777, true);
        (new SkinNewCommand(new Paths($this->root)))
            ->run(['domain' => 'example.ru', 'allias' => 'www', 'name' => 'default'], $this->ctx());
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

    private function execute(array $args): Result
    {
        return (new SkinEditCommand(new Paths($this->root)))->run($args, $this->ctx());
    }

    public function testWritesCss(): void
    {
        $res = $this->execute([
            'domain' => 'example.ru', 'allias' => 'www', 'name' => 'default',
            'file' => 'css/main.css', 'content' => 'body{color:red}',
        ]);
        self::assertTrue($res->ok);
        $file = $this->root . '/www/media/example.ru/www/skin/default/css/main.css';
        self::assertSame('body{color:red}', (string) file_get_contents($file));
    }

    public function testWritesJs(): void
    {
        $res = $this->execute([
            'domain' => 'example.ru', 'allias' => 'www', 'name' => 'default',
            'file' => 'js/main.js', 'content' => 'console.log(1);',
        ]);
        self::assertTrue($res->ok);
    }

    public function testAcceptsBase64(): void
    {
        $content = 'body{color:blue}';
        $res = $this->execute([
            'domain' => 'example.ru', 'allias' => 'www', 'name' => 'default',
            'file' => 'css/main.css', 'content_b64' => base64_encode($content),
        ]);
        self::assertTrue($res->ok);
        self::assertSame(
            $content,
            (string) file_get_contents($this->root . '/www/media/example.ru/www/skin/default/css/main.css'),
        );
    }

    public function testRejectsUnsafeCss(): void
    {
        $res = $this->execute([
            'domain' => 'example.ru', 'allias' => 'www', 'name' => 'default',
            'file' => 'css/main.css', 'content' => 'a{background:url(javascript:alert(1))}',
        ]);
        self::assertFalse($res->ok);
        self::assertStringContainsString('небезопасные', $res->errors[0]);
    }

    public function testRejectsImport(): void
    {
        $res = $this->execute([
            'domain' => 'example.ru', 'allias' => 'www', 'name' => 'default',
            'file' => 'css/main.css', 'content' => '@import url("http://evil.com/x.css");',
        ]);
        self::assertFalse($res->ok);
    }

    public function testRejectsOtherFiles(): void
    {
        $res = $this->execute([
            'domain' => 'example.ru', 'allias' => 'www', 'name' => 'default',
            'file' => 'skin.json', 'content' => '{}',
        ]);
        self::assertFalse($res->ok);
    }

    public function testRejectsPathTraversalInFile(): void
    {
        $res = $this->execute([
            'domain' => 'example.ru', 'allias' => 'www', 'name' => 'default',
            'file' => '../other/css/main.css', 'content' => 'body{}',
        ]);
        self::assertFalse($res->ok);
    }

    public function testRejectsMissingSkin(): void
    {
        $res = $this->execute([
            'domain' => 'example.ru', 'allias' => 'www', 'name' => 'ghost',
            'file' => 'css/main.css', 'content' => 'body{}',
        ]);
        self::assertFalse($res->ok);
    }

    public function testRejectsTooLargeContent(): void
    {
        $res = $this->execute([
            'domain' => 'example.ru', 'allias' => 'www', 'name' => 'default',
            'file' => 'css/main.css', 'content' => str_repeat('a', 1048577),
        ]);
        self::assertFalse($res->ok);
    }
}
