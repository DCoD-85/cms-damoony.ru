<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Core\Files;

use Damoony\Core\Files\Paths;
use PHPUnit\Framework\TestCase;

final class PathsV4Test extends TestCase
{
    private string $root;
    private Paths $paths;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_paths_' . bin2hex(random_bytes(6));
        mkdir($this->root . '/config', 0o775, true);
        $this->paths = new Paths($this->root);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    public function testStructureOfDefaultsToV3(): void
    {
        $this->seedSite([]);
        $this->assertSame('v3', $this->paths->structureOf('real.ru'));
        $this->assertFalse($this->paths->isV4('real.ru'));
    }

    public function testStructureOfExplicitV4(): void
    {
        $this->seedSite(['domains_structure' => ['real.ru' => 'v4']]);
        $this->assertSame('v4', $this->paths->structureOf('real.ru'));
        $this->assertTrue($this->paths->isV4('real.ru'));
    }

    public function testStructureOfIgnoresUnknownValue(): void
    {
        $this->seedSite(['domains_structure' => ['real.ru' => 'v99']]);
        $this->assertSame('v3', $this->paths->structureOf('real.ru'));
    }

    public function testI18nGlobalV3(): void
    {
        $this->seedSite([]);
        $this->assertSame(
            $this->root . '/app/real.ru/.i18n',
            $this->paths->i18nGlobal('real.ru'),
        );
    }

    public function testI18nGlobalV4(): void
    {
        $this->seedSite(['domains_structure' => ['real.ru' => 'v4']]);
        $this->assertSame(
            $this->root . '/app/real.ru/.i18nglobal',
            $this->paths->i18nGlobal('real.ru'),
        );
    }

    public function testI18nGlobalFallbackToV4IfOnlyV4Exists(): void
    {
        // v3 в config, но на диске только .i18nglobal (после миграции)
        $this->seedSite([]);
        mkdir($this->root . '/app/real.ru/.i18nglobal', 0o775, true);
        $this->assertSame(
            $this->root . '/app/real.ru/.i18nglobal',
            $this->paths->i18nGlobal('real.ru'),
        );
    }

    public function testI18nLocalV3(): void
    {
        $this->seedSite([]);
        $this->assertSame(
            $this->root . '/app/real.ru/www/.i18n',
            $this->paths->i18nLocal('real.ru', 'www'),
        );
    }

    public function testI18nLocalV4(): void
    {
        $this->seedSite(['domains_structure' => ['real.ru' => 'v4']]);
        $this->assertSame(
            $this->root . '/app/real.ru/www/.i18nlocal',
            $this->paths->i18nLocal('real.ru', 'www'),
        );
    }

    public function testDbGlobalV3(): void
    {
        $this->seedSite([]);
        $this->assertSame(
            $this->root . '/app/real.ru/.db',
            $this->paths->dbGlobal('real.ru'),
        );
    }

    public function testDbGlobalV4(): void
    {
        $this->seedSite(['domains_structure' => ['real.ru' => 'v4']]);
        $this->assertSame(
            $this->root . '/app/real.ru/.dbglobal',
            $this->paths->dbGlobal('real.ru'),
        );
    }

    public function testDbLocalAlwaysV4Path(): void
    {
        $this->seedSite([]);
        $this->assertSame(
            $this->root . '/app/real.ru/www/.dblocal',
            $this->paths->dbLocal('real.ru', 'www'),
        );
    }

    public function testBlocksAndSchemes(): void
    {
        $this->seedSite([]);
        $this->assertSame(
            $this->root . '/app/real.ru/.blocksglobal',
            $this->paths->blocksGlobal('real.ru'),
        );
        $this->assertSame(
            $this->root . '/app/real.ru/www/.blockslocal',
            $this->paths->blocksLocal('real.ru', 'www'),
        );
        $this->assertSame(
            $this->root . '/app/real.ru/.schemesglobal',
            $this->paths->schemesGlobal('real.ru'),
        );
        $this->assertSame(
            $this->root . '/app/real.ru/www/.schemeslocal',
            $this->paths->schemesLocal('real.ru', 'www'),
        );
    }

    public function testMediaSharedAndAlias(): void
    {
        $this->seedSite([]);
        $this->assertSame(
            $this->root . '/www/media/shared',
            $this->paths->mediaShared(),
        );
        $this->assertSame(
            $this->root . '/www/media/real.ru/www',
            $this->paths->mediaAlias('real.ru', 'www'),
        );
    }

    public function testLegacyMethodsUntouched(): void
    {
        $this->seedSite([]);
        // Старые методы не изменились
        $this->assertSame($this->root . '/app/real.ru/.i18n', $this->paths->i18nDomain('real.ru'));
        $this->assertSame($this->root . '/app/real.ru/www/.i18n', $this->paths->i18nAllias('real.ru', 'www'));
        $this->assertSame($this->root . '/app/real.ru/www', $this->paths->allias('real.ru', 'www'));
    }

    /** @param array<string, mixed> $site */
    private function seedSite(array $site): void
    {
        file_put_contents(
            $this->root . '/config/site.config',
            json_encode($site, JSON_THROW_ON_ERROR),
        );
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
