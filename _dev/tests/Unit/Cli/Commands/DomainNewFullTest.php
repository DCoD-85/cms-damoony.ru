<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli\Commands;

use Damoony\Cli\Commands\AssemblyNewCommand;
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
        // Копируем сборку в тестовое окружение, чтобы AssemblyBuilder её нашел
        $stubsSrc = dirname(__DIR__, 5) . '/stubs/assemblies/developer';
        $stubsDst = $this->root . '/stubs/assemblies/developer';
        if (is_dir($stubsSrc)) {
            mkdir(dirname($stubsDst), 0o777, true);
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($stubsSrc, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST,
            );
            foreach ($it as $item) {
                $target = $stubsDst . '/' . $it->getSubPathname();
                if ($item->isDir()) {
                    @mkdir($target, 0o777, true);
                } else {
                    @copy($item->getPathname(), $target);
                }
            }
        }


        file_put_contents($this->root . '/config/site.config', json_encode([], JSON_THROW_ON_ERROR));
        file_put_contents($this->root . '/stubs/cliajax.js', '// cliajax stub');
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    public function testProfileFullCreatesEverything(): void
    {
        new AssemblyNewCommand($this->paths)->run(
            ['domain' => 'full.ru', 'assembly' => 'developer'],
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
        $this->assertFileExists($this->root . '/app/full.ru/www/algoritm/lang/tests/first/algoritm_lang_tests_first.php');
        $this->assertFileExists($this->root . '/app/full.ru/.schemesglobal/www.php');
    }

    public function testCliajaxIsCopied(): void
    {
        new AssemblyNewCommand($this->paths)->run(
            ['domain' => 'full.ru', 'assembly' => 'developer'],
            $this->ctx(),
        );

        $this->assertStringContainsString(
            'document.addEventListener',
            (string) file_get_contents($this->root . '/www/media/shared/scripts/cliajax.js'),
        );
    }

    public function testFullContentBlocksHaveCorrectText(): void
    {
        new AssemblyNewCommand($this->paths)->run(
            ['domain' => 'full.ru', 'assembly' => 'developer'],
            $this->ctx(),
        );

        $globalPhp = (string) file_get_contents(
            $this->root . '/app/full.ru/.blocksglobal/top/top.php',
        );
        $this->assertStringContainsString("lang('block.top')", $globalPhp);

        $localPhp = (string) file_get_contents(
            $this->root . '/app/full.ru/www/.blockslocal/top/top.php',
        );
        $this->assertStringContainsString("lang('block.top')", $localPhp);
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
