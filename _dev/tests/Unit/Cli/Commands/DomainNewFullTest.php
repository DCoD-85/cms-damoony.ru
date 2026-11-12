<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli\Commands;

use Damoony\Cli\Commands\DomainNewCommand;
use Damoony\Cli\Context;
use Damoony\Core\Files\Paths;
use PHPUnit\Framework\TestCase;

final class DomainNewFullTest extends TestCase
{
    private string $root;
    private Paths $paths;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_dnfull_' . bin2hex(random_bytes(6));
        mkdir($this->root . '/config', 0o775, true);
        mkdir($this->root . '/www/media', 0o775, true);
        mkdir($this->root . '/stubs', 0o775, true);
        $this->paths = new Paths($this->root);

        file_put_contents($this->root . '/config/site.config', json_encode([], JSON_THROW_ON_ERROR));
        file_put_contents($this->root . '/stubs/cliajax.js', '// cliajax stub');
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    public function testProfileFullCreatesEverything(): void
    {
        new DomainNewCommand($this->paths)->run(
            ['domain' => 'full.ru', 'profile' => 'full'],
            $this->ctx(),
        );

        // Глобальные блоки (8)
        foreach (['top', 'left', 'center', 'right', 'bottom', 'footer', 'policy', 'toast'] as $b) {
            $this->assertFileExists(
                $this->root . '/app/full.ru/.blocksglobal/' . $b . '/' . $b . '.php',
                "global block {$b} missing",
            );
        }

        // Локальные блоки (7)
        foreach (['top', 'left', 'center', 'right', 'bottom', 'footer', 'policy'] as $b) {
            $this->assertFileExists(
                $this->root . '/app/full.ru/www/.blockslocal/' . $b . '/' . $b . '.php',
                "local block {$b} missing",
            );
        }

        // i18n
        $this->assertFileExists($this->root . '/app/full.ru/.i18nglobal/ru.json');
        $this->assertFileExists($this->root . '/app/full.ru/.i18nglobal/en.json');
        $this->assertFileExists($this->root . '/app/full.ru/www/.i18nlocal/ru.json');
        $this->assertFileExists($this->root . '/app/full.ru/www/.i18nlocal/en.json');

        // DB
        $this->assertFileExists($this->root . '/app/full.ru/.dbglobal/schema.json');
        $this->assertFileExists($this->root . '/app/full.ru/www/.dblocal/schema.json');

        // Media
        $this->assertFileExists($this->root . '/www/media/shared/scripts/cliajax.js');
        $this->assertDirectoryExists($this->root . '/www/media/full.ru/www/css');

        // Tests
        $this->assertFileExists($this->root . '/app/full.ru/www/algoritm/testscheme/algoritm_testscheme.php');
        $this->assertFileExists($this->root . '/app/full.ru/www/.schemeslocal/testscheme.php');
    }

    public function testCliajaxIsCopied(): void
    {
        new DomainNewCommand($this->paths)->run(
            ['domain' => 'full.ru', 'profile' => 'full'],
            $this->ctx(),
        );

        $this->assertSame(
            '// cliajax stub',
            (string) file_get_contents($this->root . '/www/media/shared/scripts/cliajax.js'),
        );
    }

    public function testFullContentBlocksHaveCorrectText(): void
    {
        new DomainNewCommand($this->paths)->run(
            ['domain' => 'full.ru', 'profile' => 'full'],
            $this->ctx(),
        );

        $globalPhp = (string) file_get_contents(
            $this->root . '/app/full.ru/.blocksglobal/top/top.php',
        );
        $this->assertStringContainsString('это глобальный блок top', $globalPhp);

        $localPhp = (string) file_get_contents(
            $this->root . '/app/full.ru/www/.blockslocal/top/top.php',
        );
        $this->assertStringContainsString('это локальный блок top', $localPhp);
    }

    private function ctx(): Context
    {
        return new Context(static fn (): null => null);
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
            $p = $dir . '/' . $item;
            is_dir($p) ? $this->rrmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }
}
