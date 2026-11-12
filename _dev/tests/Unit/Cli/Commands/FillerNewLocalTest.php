<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli\Commands;

use Damoony\Cli\Commands\FillerNewCommand;
use Damoony\Cli\Context;
use Damoony\Core\Files\Paths;
use PHPUnit\Framework\TestCase;

final class FillerNewLocalTest extends TestCase
{
    private string $root;
    private Paths $paths;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_fillerlocal_' . bin2hex(random_bytes(6));
        mkdir($this->root . '/config', 0o775, true);
        $this->paths = new Paths($this->root);

        file_put_contents(
            $this->root . '/config/site.config',
            json_encode([
                'domains_real' => ['real.ru' => 'real.ru', 'alias.ru' => 'real.ru'],
                'domains_namespace' => ['real.ru' => 'real_ru'],
                'alliases_real' => [
                    'real.ru' => ['www' => 'www'],
                    'alias.ru' => ['www' => 'www'],
                ],
                'fillers' => ['real.ru' => ['www' => []]],
            ], JSON_THROW_ON_ERROR),
        );

        // Базовый блок + алиас
        mkdir($this->root . '/app/real.ru/.blocksglobal/center', 0o775, true);
        file_put_contents($this->root . '/app/real.ru/.blocksglobal/center/center.php', "<?php // base\n");
        mkdir($this->root . '/app/real.ru/www/algoritm', 0o775, true);
        file_put_contents($this->root . '/app/real.ru/www/algoritm/algoritm.php', "<?php // root\n");
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    public function testFillerNewWithAliasDomainCreatesInBlockslocal(): void
    {
        new FillerNewCommand($this->paths)->run(
            ['domain' => 'alias.ru', 'allias' => 'www', 'block' => 'center'],
            $this->ctx(),
        );

        $this->assertFileExists(
            $this->root . '/app/real.ru/www/.blockslocal/center/center.php',
            '.php должен быть в .blockslocal',
        );
        $this->assertFileExists(
            $this->root . '/app/real.ru/www/.blockslocal/center/center.rules.json',
        );
        $this->assertFileExists(
            $this->root . '/app/real.ru/www/.blockslocal/center/center.editor.json',
        );
        $this->assertDirectoryDoesNotExist($this->root . '/app/alias.ru');
    }

    public function testFillerNewTextIsLocalBlock(): void
    {
        new FillerNewCommand($this->paths)->run(
            ['domain' => 'real.ru', 'allias' => 'www', 'block' => 'center'],
            $this->ctx(),
        );

        $php = (string) file_get_contents(
            $this->root . '/app/real.ru/www/.blockslocal/center/center.php',
        );
        $this->assertStringContainsString('это локальный блок center', $php);
        $this->assertStringContainsString('blockslocal_www_center', $php);
        $this->assertStringNotContainsString('blocksglobal_', $php);
    }

    public function testFillerNewRulesHaveType(): void
    {
        new FillerNewCommand($this->paths)->run(
            ['domain' => 'real.ru', 'allias' => 'www', 'block' => 'center'],
            $this->ctx(),
        );

        $rules = json_decode((string) file_get_contents(
            $this->root . '/app/real.ru/www/.blockslocal/center/center.rules.json',
        ), true);
        $this->assertSame(true, $rules['enabled'] ?? null);
        $this->assertSame('mixed', $rules['type'] ?? null);
    }

    public function testFillerNewConfigWrittenToRealDomain(): void
    {
        new FillerNewCommand($this->paths)->run(
            ['domain' => 'alias.ru', 'allias' => 'www', 'block' => 'center'],
            $this->ctx(),
        );

        $site = json_decode((string) file_get_contents($this->root . '/config/site.config'), true);
        $this->assertTrue(
            $site['fillers']['real.ru']['www']['center'] ?? false,
            'fillers должен быть под real.ru',
        );
        $this->assertArrayNotHasKey(
            'alias.ru',
            $site['fillers'] ?? [],
            'fillers НЕ должен быть под alias.ru',
        );
    }

    public function testFillerNewRejectsMissingBaseBlock(): void
    {
        new FillerNewCommand($this->paths)->run(
            ['domain' => 'real.ru', 'allias' => 'www', 'block' => 'nonexistent'],
            $this->ctx(),
        );

        $this->assertDirectoryDoesNotExist(
            $this->root . '/app/real.ru/www/.blockslocal/nonexistent',
        );
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
