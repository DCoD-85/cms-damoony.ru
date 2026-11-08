<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\FillerEditCommand;
use Damoony\Cli\Commands\FillerNewCommand;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Files\Paths;
use PHPUnit\Framework\TestCase;

final class FillerEditCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_fe_' . bin2hex(random_bytes(4));
        mkdir($this->root . '/config', 0o777, true);
        file_put_contents($this->root . '/config/site.config', json_encode([
            'domains_real' => ['example.ru' => 'example.ru'],
            'domains_available' => ['example.ru' => true],
            'domains_namespace' => ['example.ru' => 'example'],
            'alliases_real' => ['example.ru' => ['www' => 'www']],
        ]));
        mkdir($this->root . '/app/example.ru/www/algoritm', 0o777, true);
        mkdir($this->root . '/app/example.ru/.alliasglobal/top', 0o777, true);
        file_put_contents($this->root . '/app/example.ru/.alliasglobal/top/top.php', '<?php');
        file_put_contents($this->root . '/app/example.ru/.alliasglobal/top/top.rules.json', '{"enabled":true,"type":"mixed"}');
        file_put_contents($this->root . '/app/example.ru/.alliasglobal/top/top.editor.json', '[]');
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

    private function createFiller(string $allias = 'www', string $block = 'top'): void
    {
        new FillerNewCommand(new Paths($this->root))
            ->run(['domain' => 'example.ru', 'allias' => $allias, 'block' => $block], $this->ctx(new Result()));
    }

    private function enablePhp(): void
    {
        file_put_contents(
            $this->root . '/config/engine.config',
            json_encode(['security' => ['allow_php_blocks' => true]]),
        );
    }

    // --- S81: rules / editor ---

    public function testS81UpdatesRules(): void
    {
        $this->createFiller();
        $res = new FillerEditCommand(new Paths($this->root))->run([
            'domain' => 'example.ru',
            'allias' => 'www',
            'block' => 'top',
            'file' => 'rules',
            'content' => '{"enabled":false}',
        ], $this->ctx(new Result()));

        self::assertTrue($res->ok, implode('; ', $res->errors));
        $rules = json_decode((string) file_get_contents(
            $this->root . '/app/example.ru/.alliasglobal/top/top_www.rules.json',
        ), true);
        self::assertFalse($rules['enabled']);
    }

    public function testS81UpdatesEditor(): void
    {
        $this->createFiller();
        $res = new FillerEditCommand(new Paths($this->root))->run([
            'domain' => 'example.ru',
            'allias' => 'www',
            'block' => 'top',
            'file' => 'editor',
            'content' => '{"x":1}',
        ], $this->ctx(new Result()));

        self::assertTrue($res->ok, implode('; ', $res->errors));
        $editor = json_decode((string) file_get_contents(
            $this->root . '/app/example.ru/.alliasglobal/top/top_www.editor.json',
        ), true);
        self::assertSame(1, $editor['x']);
    }

    public function testS81RejectsInvalidJson(): void
    {
        $this->createFiller();
        $res = new FillerEditCommand(new Paths($this->root))->run([
            'domain' => 'example.ru',
            'allias' => 'www',
            'block' => 'top',
            'file' => 'rules',
            'content' => '{broken',
        ], $this->ctx(new Result()));

        self::assertFalse($res->ok);
        self::assertStringContainsString('JSON', $res->errors[0]);
    }

    // --- S81: code ---

    public function testS81RejectsCodeWhenAllowPhpBlocksFalse(): void
    {
        $this->createFiller();
        $res = new FillerEditCommand(new Paths($this->root))->run([
            'domain' => 'example.ru',
            'allias' => 'www',
            'block' => 'top',
            'file' => 'code',
            'content' => '<?php echo "x";',
            'allow_php' => '1',
        ], $this->ctx(new Result()));

        self::assertFalse($res->ok);
        self::assertStringContainsString('allow_php_blocks', $res->errors[0]);
    }

    public function testS81AcceptsCodeWithAllFlags(): void
    {
        $this->createFiller();
        $this->enablePhp();
        $res = new FillerEditCommand(new Paths($this->root))->run([
            'domain' => 'example.ru',
            'allias' => 'www',
            'block' => 'top',
            'file' => 'code',
            'content' => '<?php echo "override";',
            'allow_php' => '1',
        ], $this->ctx(new Result()));

        self::assertTrue($res->ok, implode('; ', $res->errors));
        $code = (string) file_get_contents(
            $this->root . '/app/example.ru/.alliasglobal/top/top_www.php',
        );
        self::assertStringContainsString('override', $code);
    }

    // --- S81: домен / блок / filler / file ---

    public function testS81RejectsMissingFiller(): void
    {
        // Filler не создан — нет top_www.php
        $res = new FillerEditCommand(new Paths($this->root))->run([
            'domain' => 'example.ru',
            'allias' => 'www',
            'block' => 'top',
            'file' => 'rules',
            'content' => '{"enabled":true}',
        ], $this->ctx(new Result()));

        self::assertFalse($res->ok);
        self::assertStringContainsString('Filler', $res->errors[0]);
    }

    public function testS81RejectsMissingBlock(): void
    {
        $this->createFiller();
        $res = new FillerEditCommand(new Paths($this->root))->run([
            'domain' => 'example.ru',
            'allias' => 'www',
            'block' => 'ghost',
            'file' => 'rules',
            'content' => '{"enabled":true}',
        ], $this->ctx(new Result()));

        self::assertFalse($res->ok);
        self::assertStringContainsString('Блок не найден', $res->errors[0]);
    }

    public function testS81RejectsInvalidFile(): void
    {
        $this->createFiller();
        $res = new FillerEditCommand(new Paths($this->root))->run([
            'domain' => 'example.ru',
            'allias' => 'www',
            'block' => 'top',
            'file' => 'shell',
            'content' => '{}',
        ], $this->ctx(new Result()));

        self::assertFalse($res->ok);
        self::assertStringContainsString('Недопустимый файл', $res->errors[0]);
    }

    public function testS81RejectsPathTraversal(): void
    {
        $res = new FillerEditCommand(new Paths($this->root))->run([
            'domain' => 'a..ru',
            'allias' => 'www',
            'block' => 'top',
            'file' => 'rules',
            'content' => '{"enabled":true}',
        ], $this->ctx(new Result()));

        self::assertFalse($res->ok);
    }

    public function testS81RejectsInvalidAllias(): void
    {
        $res = new FillerEditCommand(new Paths($this->root))->run([
            'domain' => 'example.ru',
            'allias' => 'Bad-Allias',
            'block' => 'top',
            'file' => 'rules',
            'content' => '{"enabled":true}',
        ], $this->ctx(new Result()));

        self::assertFalse($res->ok);
        self::assertStringContainsString('алиас', $res->errors[0]);
    }
}
