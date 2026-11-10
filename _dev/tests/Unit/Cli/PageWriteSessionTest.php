<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\PageWriteCommand;
use Damoony\Cli\Context;
use Damoony\Core\Files\Paths;
use PHPUnit\Framework\TestCase;

final class PageWriteSessionTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/pws_' . bin2hex(random_bytes(4));

        // Config
        mkdir($this->root . '/config', 0o775, true);
        file_put_contents(
            $this->root . '/config/site.config',
            json_encode([
                'domains_namespace' => ['damoony.ru' => 'damoony_ru'],
            ]),
        );

        // Prod page
        mkdir($this->root . '/app/damoony.ru/www/algoritm', 0o775, true);
        file_put_contents(
            $this->root . '/app/damoony.ru/www/algoritm/algoritm.php',
            "<?php // prod original\n",
        );
        file_put_contents(
            $this->root . '/app/damoony.ru/www/algoritm/algoritm.editor.json',
            '[]',
        );

        // Session copy
        mkdir($this->root . '/app/.sess_abc12345.damoony.ru/www/algoritm', 0o775, true);
        file_put_contents(
            $this->root . '/app/.sess_abc12345.damoony.ru/www/algoritm/algoritm.php',
            "<?php // session original\n",
        );
        file_put_contents(
            $this->root . '/app/.sess_abc12345.damoony.ru/www/algoritm/algoritm.editor.json',
            '[]',
        );
    }

    protected function tearDown(): void
    {
        $this->rmrf($this->root);
    }

    public function testWriteToSessionOnly(): void
    {
        $paths = new Paths($this->root);
        $cmd = new PageWriteCommand($paths);

        $r = $cmd->run([
            'domain' => 'damoony.ru',
            'allias' => 'www',
            'name' => '__root__',
            'session' => 'abc12345',
            'json' => json_encode([['type' => 'h1', 'content' => 'Session H1']]),
            'with_schema' => '1',
        ], new Context(static fn () => null));

        self::assertTrue($r->ok, 'page:write failed: ' . implode('; ', $r->errors));

        $sessionPhp = (string) file_get_contents(
            $this->root . '/app/.sess_abc12345.damoony.ru/www/algoritm/algoritm.php',
        );
        $prodPhp = (string) file_get_contents(
            $this->root . '/app/damoony.ru/www/algoritm/algoritm.php',
        );

        $sessionEditor = (string) file_get_contents(
            $this->root . '/app/.sess_abc12345.damoony.ru/www/algoritm/algoritm.editor.json',
        );
        $prodEditor = (string) file_get_contents(
            $this->root . '/app/damoony.ru/www/algoritm/algoritm.editor.json',
        );

        self::assertStringContainsString('Session H1', $sessionPhp);
        self::assertStringNotContainsString('Session H1', $prodPhp);
        self::assertStringContainsString('Session H1', $sessionEditor);
        self::assertStringNotContainsString('Session H1', $prodEditor);
    }

    public function testRejectsMissingSession(): void
    {
        $paths = new Paths($this->root);
        $cmd = new PageWriteCommand($paths);

        $r = $cmd->run([
            'domain' => 'damoony.ru',
            'allias' => 'www',
            'name' => '__root__',
            'session' => 'deadbeef',
            'json' => '[]',
        ], new Context(static fn () => null));

        self::assertFalse($r->ok);
        self::assertStringContainsString('Сессия не найдена', $r->errors[0]);
    }

    public function testRejectsInvalidSessionFormat(): void
    {
        $paths = new Paths($this->root);
        $cmd = new PageWriteCommand($paths);

        $r = $cmd->run([
            'domain' => 'damoony.ru',
            'allias' => 'www',
            'name' => '__root__',
            'session' => '../etc/passwd',
            'json' => '[]',
        ], new Context(static fn () => null));

        self::assertFalse($r->ok);
        self::assertStringContainsString('Некорректный --session', $r->errors[0]);
    }

    public function testWithoutSessionWritesToProd(): void
    {
        $paths = new Paths($this->root);
        $cmd = new PageWriteCommand($paths);

        $r = $cmd->run([
            'domain' => 'damoony.ru',
            'allias' => 'www',
            'name' => '__root__',
            'json' => json_encode([['type' => 'h1', 'content' => 'Prod H1']]),
            'with_schema' => '1',
        ], new Context(static fn () => null));

        self::assertTrue($r->ok, 'page:write failed: ' . implode('; ', $r->errors));

        $prodPhp = (string) file_get_contents(
            $this->root . '/app/damoony.ru/www/algoritm/algoritm.php',
        );
        $sessionPhp = (string) file_get_contents(
            $this->root . '/app/.sess_abc12345.damoony.ru/www/algoritm/algoritm.php',
        );

        self::assertStringContainsString('Prod H1', $prodPhp);
        self::assertStringNotContainsString('Prod H1', $sessionPhp);
    }

    private function rmrf(string $d): void
    {
        if (!is_dir($d)) {
            return;
        }
        foreach (scandir($d) ?: [] as $i) {
            if ($i === '.' || $i === '..') {
                continue;
            }
            $p = $d . '/' . $i;
            is_dir($p) ? $this->rmrf($p) : @unlink($p);
        }
        @rmdir($d);
    }
}
