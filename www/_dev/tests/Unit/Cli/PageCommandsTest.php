<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\PageNewCommand;
use Damoony\Cli\Commands\PageReadCommand;
use Damoony\Cli\Commands\PageToggleCommand;
use Damoony\Cli\Commands\PageWriteCommand;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Files\Paths;
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

        // Root stub по новой модели
        $algoritm = $this->root . '/app/example.ru/www/algoritm';
        mkdir($algoritm, 0o777, true);
        file_put_contents(
            $algoritm . '/algoritm.php',
            "<?php\n\ndeclare(strict_types=1);\n\nnamespace example\\www;\n",
        );
        file_put_contents(
            $algoritm . '/algoritm.rules.json',
            json_encode(['available' => [], 'auth' => false]),
        );
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
        $res = new PageNewCommand(new Paths($this->root))
            ->run(['domain' => 'example.ru', 'allias' => 'www', 'name' => 'about'], $this->ctx($r));

        self::assertTrue($res->ok, implode('; ', $res->errors));
        $dir = $this->root . '/app/example.ru/www/algoritm/about';
        self::assertDirectoryExists($dir);
        self::assertFileExists($dir . '/algoritm_about.php');
        self::assertFileExists($dir . '/algoritm_about.rules.json');
        self::assertFileExists($dir . '/algoritm_about.editor.json');

        $rules = json_decode((string) file_get_contents($dir . '/algoritm_about.rules.json'), true);
        self::assertIsArray($rules);
        self::assertArrayHasKey('available', $rules);
        self::assertArrayHasKey('auth', $rules);

        // Root available в algoritm/algoritm.rules.json
        $root = json_decode(
            (string) file_get_contents($this->root . '/app/example.ru/www/algoritm/algoritm.rules.json'),
            true,
        );
        self::assertArrayHasKey('about', $root['available']);
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
        $res = new PageNewCommand(new Paths($this->root))
            ->run(['domain' => 'example.ru', 'allias' => 'ghost', 'name' => 'about'], $this->ctx(new Result()));
        self::assertFalse($res->ok);
    }

    public function testS09ToggleAuth(): void
    {
        $paths = new Paths($this->root);
        new PageNewCommand($paths)->run(
            ['domain' => 'example.ru', 'allias' => 'www', 'name' => 'about'],
            $this->ctx(new Result()),
        );

        // --name вместо --path
        $res = new PageToggleCommand($paths)
            ->run(
                ['domain' => 'example.ru', 'allias' => 'www', 'name' => 'about', 'key' => 'auth', 'value' => '1'],
                $this->ctx(new Result()),
            );

        self::assertTrue($res->ok, implode('; ', $res->errors));
        $rules = json_decode(
            (string) file_get_contents($this->root . '/app/example.ru/www/algoritm/about/algoritm_about.rules.json'),
            true,
        );
        self::assertTrue($rules['auth']);

    }

    public function testS09RejectsBadKey(): void
    {
        $res = new PageToggleCommand(new Paths($this->root))
            ->run(
                ['domain' => 'example.ru', 'allias' => 'www', 'name' => '__root__', 'key' => 'weird'],
                $this->ctx(new Result()),
            );
        self::assertFalse($res->ok);
    }

    public function testS09RejectsMissingPage(): void
    {
        $res = new PageToggleCommand(new Paths($this->root))
            ->run(
                ['domain' => 'example.ru', 'allias' => 'www', 'name' => 'ghost', 'key' => 'auth'],
                $this->ctx(new Result()),
            );
        self::assertFalse($res->ok);
    }

    public function testWriteCompilesContent(): void
    {
        $paths = new Paths($this->root);
        new PageNewCommand($paths)->run(
            ['domain' => 'example.ru', 'allias' => 'www', 'name' => 'about'],
            $this->ctx(new Result()),
        );

        $res = new PageWriteCommand($paths)->run(
            [
                'domain' => 'example.ru',
                'allias' => 'www',
                'name' => 'about',
                'json' => '[{"type":"h1","content":"About"}]',
            ],
            $this->ctx(new Result()),
        );

        self::assertTrue($res->ok, implode('; ', $res->errors));
        $php = (string) file_get_contents(
            $this->root . '/app/example.ru/www/algoritm/about/algoritm_about.php',
        );
        self::assertStringContainsString('About', $php);
    }

    public function testWriteRejectsMissingPage(): void
    {
        $res = new PageWriteCommand(new Paths($this->root))->run(
            [
                'domain' => 'example.ru',
                'allias' => 'www',
                'name' => 'ghost',
                'json' => '[]',
            ],
            $this->ctx(new Result()),
        );
        self::assertFalse($res->ok);
    }

    public function testReadReturnsCode(): void
    {
        $paths = new Paths($this->root);
        new PageNewCommand($paths)->run(
            ['domain' => 'example.ru', 'allias' => 'www', 'name' => 'about'],
            $this->ctx(new Result()),
        );

        $r = new Result();
        $res = new PageReadCommand($paths)->run(
            ['domain' => 'example.ru', 'allias' => 'www', 'name' => 'about'],
            $this->ctx($r),
        );

        self::assertTrue($res->ok, implode('; ', $res->errors));
        self::assertNotEmpty($r->steps);
        self::assertStringContainsString('algoritm_about', (string) ($r->steps[0]['message'] ?? ''));
    }

    public function testRootWriteViaUnderscoreUnderscoreRoot(): void
    {
        $paths = new Paths($this->root);

        // __root__ указывает на algoritm/algoritm.php
        $res = new PageWriteCommand($paths)->run(
            [
                'domain' => 'example.ru',
                'allias' => 'www',
                'name' => '__root__',
                'json' => '[{"type":"h1","content":"Home"}]',
            ],
            $this->ctx(new Result()),
        );

        self::assertTrue($res->ok, implode('; ', $res->errors));
        $php = (string) file_get_contents(
            $this->root . '/app/example.ru/www/algoritm/algoritm.php',
        );
        self::assertStringContainsString('Home', $php);
    }

    public function testWriteRejectsPathTraversalInName(): void
    {
        $res = new PageWriteCommand(new Paths($this->root))->run(
            [
                'domain' => 'example.ru',
                'allias' => 'www',
                'name' => '../../etc/passwd',
                'json' => '[]',
            ],
            $this->ctx(new Result()),
        );

        self::assertFalse($res->ok);
        self::assertStringContainsString('Некорректное имя страницы', $res->errors[0]);
    }

    public function testWriteRejectsInvalidName(): void
    {
        $res = new PageWriteCommand(new Paths($this->root))->run(
            [
                'domain' => 'example.ru',
                'allias' => 'www',
                'name' => 'BadName',
                'json' => '[]',
            ],
            $this->ctx(new Result()),
        );

        self::assertFalse($res->ok);
        self::assertStringContainsString('Некорректное имя страницы', $res->errors[0]);
    }

    public function testReadRejectsPathTraversalInName(): void
    {
        $res = new PageReadCommand(new Paths($this->root))->run(
            [
                'domain' => 'example.ru',
                'allias' => 'www',
                'name' => '../../etc/passwd',
            ],
            $this->ctx(new Result()),
        );

        self::assertFalse($res->ok);
        self::assertStringContainsString('Некорректное имя страницы', $res->errors[0]);
    }

    public function testReadRejectsInvalidName(): void
    {
        $res = new PageReadCommand(new Paths($this->root))->run(
            [
                'domain' => 'example.ru',
                'allias' => 'www',
                'name' => 'BadName',
            ],
            $this->ctx(new Result()),
        );

        self::assertFalse($res->ok);
        self::assertStringContainsString('Некорректное имя страницы', $res->errors[0]);
    }
}
