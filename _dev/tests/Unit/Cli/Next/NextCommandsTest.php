<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli\Next;

use Damoony\Cli\Context;
use Damoony\Cli\Next\Commands\AlliasListCommand;
use Damoony\Cli\Next\Commands\AlliasNewCommand;
use Damoony\Cli\Next\Commands\DomainListCommand;
use Damoony\Cli\Next\Commands\DomainNewCommand;
use Damoony\Cli\Next\Registry;
use Damoony\Cli\Next\Scaffold\Scaffolder;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Core\Settings\SiteConfigWriter;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class NextCommandsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_cli_next_' . bin2hex(random_bytes(6));
        mkdir($this->root . '/config', 0o775, true);
        mkdir($this->root . '/app/damoony.ru/www', 0o775, true);

        $writer = SiteConfigWriter::forRoot($this->root);
        $writer->ensureProject('damoony.ru', 'damoony.ru', 'damoony_ru');
        $writer->ensureMirror('damoony.ru', 'damoony.ru');
        $writer->ensureMirror('damoony.ru', 'damoony3.ru', false);
        $writer->ensureAlias('damoony.ru', 'www', ['ru', 'en']);
        $writer->ensureAlias('damoony.ru', 'docs', ['ru']);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    public function testRegistryThrowsWhenConfigMissing(): void
    {
        $root = sys_get_temp_dir() . '/damoony_cli_next_empty_' . bin2hex(random_bytes(6));
        mkdir($root . '/config', 0o775, true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('не найден');
        Registry::build($root);
    }

    public function testRegistryBuildsCommands(): void
    {
        $registry = Registry::build($this->root);

        self::assertNotNull($registry->get('domain:list'));
        self::assertNotNull($registry->get('allias:list'));
        self::assertNotNull($registry->get('help'));
    }

    public function testDomainListReportsProjects(): void
    {
        $paths = new Paths($this->root);
        $site = SiteConfig::fromFile(Registry::configPath($this->root));
        $command = new DomainListCommand($paths, $site);

        $result = $command->run([], $this->context());

        self::assertTrue($result->ok);
        self::assertCount(1, $result->data['rows']);

        $row = $result->data['rows'][0];
        self::assertSame('damoony.ru', $row['project']);
        self::assertSame('damoony.ru', $row['folder']);
        self::assertSame('damoony_ru', $row['namespace']);
        self::assertTrue($row['dir_exists']);
        self::assertSame(['docs', 'www'], $row['aliases']);
        self::assertSame([
            ['host' => 'damoony.ru', 'available' => true],
            ['host' => 'damoony3.ru', 'available' => false],
        ], $row['mirrors']);
    }

    public function testAlliasListByMirrorHost(): void
    {
        $paths = new Paths($this->root);
        $site = SiteConfig::fromFile(Registry::configPath($this->root));
        $command = new AlliasListCommand($paths, $site);

        $result = $command->run(['domain' => 'damoony3.ru'], $this->context());

        self::assertTrue($result->ok);
        self::assertSame('damoony.ru', $result->data['project']);
        self::assertSame(['www', 'docs'], array_column($result->data['rows'], 'allias'));
        self::assertSame([true, false], array_column($result->data['rows'], 'dir_exists'));
    }

    public function testAlliasListRequiresDomain(): void
    {
        $paths = new Paths($this->root);
        $site = SiteConfig::fromFile(Registry::configPath($this->root));
        $command = new AlliasListCommand($paths, $site);

        $result = $command->run([], $this->context());

        self::assertFalse($result->ok);
        self::assertSame(['Укажите --domain'], $result->errors);
    }

    public function testAlliasListUnknownProject(): void
    {
        $paths = new Paths($this->root);
        $site = SiteConfig::fromFile(Registry::configPath($this->root));
        $command = new AlliasListCommand($paths, $site);

        $result = $command->run(['domain' => 'nope.ru'], $this->context());

        self::assertFalse($result->ok);
        self::assertStringContainsString('не найден в domains_app', $result->errors[0]);
    }

    public function testDomainNewCreatesStructureAndConfig(): void
    {
        $result = $this->domainNew()->run(['domain' => 'example.ru'], $this->context());

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertSame('example.ru', $result->data['project']);
        self::assertSame('example.ru', $result->data['folder']);
        self::assertSame('example_ru', $result->data['namespace']);

        $algoritm = $this->root . '/app/example.ru/www/algoritm';
        self::assertDirectoryExists($algoritm);
        self::assertFileExists($algoritm . '/algoritm.php');
        self::assertFileExists($algoritm . '/algoritm.rules.json');
        self::assertFileExists($algoritm . '/algoritm.editor.json');
        self::assertFileExists($algoritm . '/lang/algoritm_lang.php');
        self::assertFileExists($this->root . '/app/example.ru/.schemesglobal/www.php');
        self::assertFileExists($this->root . '/app/example.ru/.blocksglobal/center/center.php');
        self::assertFileExists($this->root . '/www/media/example.ru/www/skin/default/css/main.css');

        $php = (string) file_get_contents($algoritm . '/algoritm.php');
        self::assertStringContainsString('namespace example_ru\www;', $php);

        $site = SiteConfig::fromFile(Registry::configPath($this->root));
        self::assertSame('example_ru', $site->namespace('example.ru'));
        self::assertSame('example.ru', $site->folderByHost('example.ru'));
        self::assertSame(['ru', 'en'], $site->requireProject('example.ru')->aliasLangs('www'));
    }

    public function testDomainNewDryRunCreatesNothing(): void
    {
        $result = $this->domainNew()->run(['domain' => 'example.ru', 'dry-run' => '1'], $this->dryRunContext());

        self::assertTrue($result->ok);
        self::assertTrue($result->data['dry_run']);
        self::assertDirectoryDoesNotExist($this->root . '/app/example.ru');

        $site = SiteConfig::fromFile(Registry::configPath($this->root));
        self::assertFalse($site->has('example.ru'));
    }

    public function testDomainNewRejectsExistingProject(): void
    {
        $result = $this->domainNew()->run(['domain' => 'damoony.ru'], $this->context());

        self::assertFalse($result->ok);
        self::assertSame(['Проект уже существует в конфиге'], $result->errors);
    }

    public function testDomainNewRejectsExistingFolder(): void
    {
        mkdir($this->root . '/app/example.ru', 0o775, true);

        $result = $this->domainNew()->run(['domain' => 'example.ru'], $this->context());

        self::assertFalse($result->ok);
        self::assertSame(['Папка проекта уже существует'], $result->errors);
    }

    public function testDomainNewRejectsInvalidDomain(): void
    {
        $result = $this->domainNew()->run(['domain' => 'not a domain'], $this->context());

        self::assertFalse($result->ok);
        self::assertSame(['Некорректный домен'], $result->errors);
    }

    public function testAlliasNewCreatesStructureAndConfig(): void
    {
        $result = $this->alliasNew()->run(['domain' => 'damoony3.ru', 'name' => 'blog'], $this->context());

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertSame('damoony.ru', $result->data['project']);

        $algoritm = $this->root . '/app/damoony.ru/blog/algoritm';
        self::assertFileExists($algoritm . '/algoritm.php');
        self::assertStringContainsString(
            'namespace damoony_ru\blog;',
            (string) file_get_contents($algoritm . '/algoritm.php'),
        );
        self::assertFileExists($this->root . '/app/damoony.ru/.schemesglobal/blog.php');
        self::assertFileExists($this->root . '/www/media/damoony.ru/blog/skin/default/css/main.css');

        $site = SiteConfig::fromFile(Registry::configPath($this->root));
        self::assertTrue($site->requireProject('damoony.ru')->hasAlias('blog'));
    }

    public function testAlliasNewRejectsExistingAlias(): void
    {
        $result = $this->alliasNew()->run(['domain' => 'damoony.ru', 'name' => 'www'], $this->context());

        self::assertFalse($result->ok);
        self::assertSame(['Алиас уже существует в конфиге'], $result->errors);
    }

    public function testAlliasNewRejectsUnknownProject(): void
    {
        $result = $this->alliasNew()->run(['domain' => 'nope.ru', 'name' => 'blog'], $this->context());

        self::assertFalse($result->ok);
        self::assertStringContainsString('не найден в domains_app', $result->errors[0]);
    }

    public function testAlliasNewRejectsInvalidName(): void
    {
        $result = $this->alliasNew()->run(['domain' => 'damoony.ru', 'name' => 'Bad-Name'], $this->context());

        self::assertFalse($result->ok);
        self::assertSame(['Некорректный алиас'], $result->errors);
    }

    private function domainNew(): DomainNewCommand
    {
        $paths = new Paths($this->root);
        $configPath = Registry::configPath($this->root);

        return new DomainNewCommand(
            $paths,
            SiteConfig::fromFile($configPath),
            new SiteConfigWriter($configPath),
            new Scaffolder($paths),
        );
    }

    private function alliasNew(): AlliasNewCommand
    {
        $paths = new Paths($this->root);
        $configPath = Registry::configPath($this->root);

        return new AlliasNewCommand(
            $paths,
            SiteConfig::fromFile($configPath),
            new SiteConfigWriter($configPath),
            new Scaffolder($paths),
        );
    }

    private function context(): Context
    {
        return new Context(static function (string $m, bool $ok = true, string $d = ''): void {
        });
    }

    private function dryRunContext(): Context
    {
        return new Context(static function (string $m, bool $ok = true, string $d = ''): void {
        }, false, true);
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
