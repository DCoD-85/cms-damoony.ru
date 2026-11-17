<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\MigrateSiteConfigCommand;
use Damoony\Cli\Context;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Core\Settings\SiteConfigWriter;
use PHPUnit\Framework\TestCase;

final class MigrateSiteConfigTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_cli_sitecfg_' . bin2hex(random_bytes(6));
        mkdir($this->root . '/config', 0o775, true);

        $legacy = [
            'domains_real' => ['damoony.ru' => 'damoony_ru'],
            'domains_mirrors' => [
                'damoony.ru' => 'damoony.ru',
                'damoony3.ru' => 'damoony.ru',
            ],
            'domains_mirrors_available' => [
                'damoony.ru' => true,
                'damoony3.ru' => false,
            ],
            'domains_scheme' => ['damoony.ru' => 'landing'],
            'domains_stub' => ['damoony.ru' => ['current' => 'offline']],
            'domains_aliases_storage' => [
                'damoony.ru' => ['www' => ['db' => 'main', 'prefix' => 'd1_www__']],
            ],
            'alliases' => [
                'damoony.ru' => [
                    'www' => ['available' => true, 'langs' => ['ru', 'en']],
                    'docs' => ['available' => false, 'langs' => ['ru']],
                ],
            ],
            'fillers' => ['damoony.ru' => ['www' => ['top' => true]]],
        ];

        file_put_contents($this->root . '/config/site.config.old', json_encode($legacy, JSON_PRETTY_PRINT));
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    public function testMigratesLegacyConfig(): void
    {
        $result = $this->command()->run([], $this->context());

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertSame(['damoony.ru'], $result->data['projects']);

        $site = SiteConfig::fromFile($this->root . '/config/site.config');
        $project = $site->requireProject('damoony.ru');

        self::assertSame('damoony.ru', $project->folder);
        self::assertSame('damoony_ru', $project->namespace);
        self::assertSame('landing', $project->scheme);
        self::assertSame('offline', $project->stub);
        self::assertSame(['ru', 'en'], $project->langs);
        self::assertSame('d1_www__', $project->storagePrefix('www'));

        self::assertTrue($project->isHostAvailable('damoony.ru'));
        self::assertFalse($project->isHostAvailable('damoony3.ru'));
        self::assertSame('damoony.ru', $site->folderByHost('damoony3.ru'));

        self::assertSame(['ru', 'en'], $project->aliasLangs('www'));
        self::assertFalse($project->isAliasAvailable('docs'));
    }

    public function testDoesNotMigrateFillers(): void
    {
        $this->command()->run([], $this->context());

        $raw = json_decode((string) file_get_contents($this->root . '/config/site.config'), true);
        self::assertIsArray($raw);
        self::assertArrayNotHasKey('fillers', $raw);
    }

    public function testRefusesExistingTargetWithoutForce(): void
    {
        SiteConfigWriter::forRoot($this->root)->save(['domains_app' => []]);

        $result = $this->command()->run([], $this->context());

        self::assertFalse($result->ok);
        self::assertStringContainsString('--force', $result->errors[0]);
    }

    public function testForceQuarantinesExistingTarget(): void
    {
        SiteConfigWriter::forRoot($this->root)->save(['domains_app' => []]);

        $result = $this->command()->run(['force' => '1'], $this->context());

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertNotEmpty(glob($this->root . '/config/.trash/site.config_*') ?: []);
        self::assertTrue(SiteConfig::fromFile($this->root . '/config/site.config')->has('damoony.ru'));
    }

    public function testDryRunWritesNothing(): void
    {
        $result = $this->command()->run(['dry' => '1'], $this->context());

        self::assertTrue($result->ok);
        self::assertTrue($result->data['dry_run']);
        self::assertFileDoesNotExist($this->root . '/config/site.config');
    }

    public function testFailsWithoutSource(): void
    {
        unlink($this->root . '/config/site.config.old');

        $result = $this->command()->run([], $this->context());

        self::assertFalse($result->ok);
        self::assertStringContainsString('Источник не найден', $result->errors[0]);
    }

    public function testSelfMappingGetsDerivedNamespace(): void
    {
        file_put_contents($this->root . '/config/site.config.old', json_encode([
            'domains_real' => ['example.ru' => 'example.ru'],
            'domains_mirrors' => ['example.ru' => 'example.ru'],
            'alliases' => ['example.ru' => ['www' => ['available' => true, 'langs' => ['ru']]]],
        ]));

        $result = $this->command()->run(['force' => '1'], $this->context());

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertSame('example_ru', SiteConfig::fromFile($this->root . '/config/site.config')->namespace('example.ru'));
    }

    private function command(): MigrateSiteConfigCommand
    {
        $paths = new Paths($this->root);

        return new MigrateSiteConfigCommand($paths, SiteConfigWriter::forRoot($this->root));
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
