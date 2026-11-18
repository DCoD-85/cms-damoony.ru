<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\SiteScanCommand;
use Damoony\Cli\Context;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Core\Settings\SiteConfigWriter;
use PHPUnit\Framework\TestCase;

final class SiteScanTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_scan_' . bin2hex(random_bytes(6));
        mkdir($this->root . '/config', 0o775, true);
        mkdir($this->root . '/app', 0o775, true);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    public function testReportWithoutWrite(): void
    {
        $this->makeProject('site1.ru', ['www', 'blog'], ['ru', 'en']);

        $result = $this->command()->run([], $this->context());

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertFalse($result->data['written']);
        self::assertCount(1, $result->data['projects']);

        $row = $result->data['projects'][0];
        self::assertSame('site1.ru', $row['project']);
        self::assertSame(['www', 'blog'], array_keys($row['aliases']));
        self::assertSame(['ru', 'en'], $row['langs']);
        self::assertSame(['site1.ru'], $row['mirrors']);
        self::assertFileDoesNotExist($this->root . '/config/site.config');
    }

    public function testWriteCreatesConfig(): void
    {
        $this->makeProject('site1.ru', ['www', 'blog'], ['ru', 'en']);

        $result = $this->command()->run(['write' => '1'], $this->context());

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertTrue($result->data['written']);

        $project = SiteConfig::fromFile($this->root . '/config/site.config')->project('site1.ru');
        self::assertNotNull($project);
        self::assertSame('site1.ru', $project->folder);
        self::assertSame('site1_ru', $project->namespace);
        self::assertSame(['ru', 'en'], $project->langs);
        self::assertTrue($project->hasAlias('www'));
        self::assertTrue($project->hasAlias('blog'));
        self::assertSame(['site1.ru'], $project->hostNames());
    }

    public function testPreservesExistingNamespaceAndMirrors(): void
    {
        $writer = SiteConfigWriter::forRoot($this->root);
        $writer->ensureProject('site1.ru', 'site1.ru', 'custom_ns');
        $writer->ensureMirror('site1.ru', 'www.site1.ru');
        $writer->ensureAlias('site1.ru', 'www', ['ru']);

        $this->makeProject('site1.ru', ['www', 'blog'], ['ru', 'en']);

        $result = $this->command()->run(['write' => '1'], $this->context());

        self::assertTrue($result->ok, implode('; ', $result->errors));

        $project = SiteConfig::fromFile($this->root . '/config/site.config')->project('site1.ru');
        self::assertNotNull($project);
        self::assertSame('custom_ns', $project->namespace);
        self::assertSame(['www.site1.ru'], $project->hostNames());
        self::assertTrue($project->hasAlias('blog'));
    }

    public function testIgnoresServiceDirsAndTrash(): void
    {
        $this->makeProject('site1.ru', ['www'], ['ru']);
        mkdir($this->root . '/app/.admin/algoritm', 0o775, true);
        mkdir($this->root . '/app/site1.ru_trash_123/www/algoritm', 0o775, true);
        mkdir($this->root . '/app/broken_site', 0o775, true);

        $result = $this->command()->run([], $this->context());

        self::assertTrue($result->ok);
        self::assertCount(1, $result->data['projects']);
        self::assertSame('site1.ru', $result->data['projects'][0]['project']);
    }

    public function testWwwIsAlwaysFirstAlias(): void
    {
        $this->makeProject('site2.ru', ['blog'], ['ru']);

        $result = $this->command()->run([], $this->context());

        self::assertTrue($result->ok);
        self::assertSame(['www', 'blog'], array_keys($result->data['projects'][0]['aliases']));
    }

    /**
     * @param list<string> $aliases
     * @param list<string> $langs
     */
    private function makeProject(string $host, array $aliases, array $langs): void
    {
        $base = $this->root . '/app/' . $host;
        mkdir($base . '/.i18nglobal', 0o775, true);
        foreach ($langs as $lang) {
            file_put_contents($base . '/.i18nglobal/' . $lang . '.json', '{}');
        }

        foreach ($aliases as $alias) {
            mkdir($base . '/' . $alias . '/algoritm', 0o775, true);
            file_put_contents($base . '/' . $alias . '/algoritm/algoritm.php', "<?php\n");
        }
    }

    private function command(): SiteScanCommand
    {
        $configFile = $this->root . '/config/site.config';

        return new SiteScanCommand(
            new Paths($this->root),
            is_file($configFile) ? SiteConfig::fromFile($configFile) : SiteConfig::empty(),
            SiteConfigWriter::forRoot($this->root),
        );
    }

    private function context(): Context
    {
        return new Context(static function (string $m, bool $ok = true, string $d = ''): void {
        });
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
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
