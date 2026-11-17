<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli\Next;

use Damoony\Cli\Context;
use Damoony\Cli\Next\Commands\LangAddCommand;
use Damoony\Cli\Next\Commands\LangListCommand;
use Damoony\Cli\Next\Commands\LangRemoveCommand;
use Damoony\Cli\Next\Commands\LangRepairCommand;
use Damoony\Cli\Next\Commands\LangSyncCommand;
use Damoony\Cli\Next\Support\Quarantine;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Core\Settings\SiteConfigWriter;
use PHPUnit\Framework\TestCase;

final class NextLangCommandsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_cli_lang_' . bin2hex(random_bytes(6));
        mkdir($this->root . '/config', 0o775, true);

        $writer = SiteConfigWriter::forRoot($this->root);
        $writer->ensureProject('damoony.ru', 'damoony.ru', 'damoony_ru');
        $writer->ensureMirror('damoony.ru', 'damoony.ru');
        $writer->ensureAlias('damoony.ru', 'www', []);
        $writer->ensureAlias('damoony.ru', 'docs', []);

        mkdir($this->root . '/app/damoony.ru/www', 0o775, true);
        mkdir($this->root . '/app/damoony.ru/docs', 0o775, true);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    public function testAddProjectLangCreatesFileAndConfig(): void
    {
        $result = $this->add()->run(['domain' => 'damoony.ru', 'lang' => 'en'], $this->context());

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertFileExists($this->root . '/app/damoony.ru/.i18nglobal/en.json');
        self::assertSame(['en'], $this->site()->requireProject('damoony.ru')->langs);
    }

    public function testAddAliasLangRequiresProjectLang(): void
    {
        $this->add()->run(['domain' => 'damoony.ru', 'lang' => 'en'], $this->context());

        $result = $this->add()->run(['domain' => 'damoony.ru', 'allias' => 'www', 'lang' => 'de'], $this->context());

        self::assertFalse($result->ok);
        self::assertStringContainsString('не объявлен у проекта', $result->errors[0]);
    }

    public function testAddAliasLangWhenProjectHasLangs(): void
    {
        $this->add()->run(['domain' => 'damoony.ru', 'lang' => 'en'], $this->context());

        $result = $this->add()->run(['domain' => 'damoony.ru', 'allias' => 'www', 'lang' => 'en'], $this->context());

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertFileExists($this->root . '/app/damoony.ru/www/.i18nlocal/en.json');
        self::assertContains('en', $this->site()->requireProject('damoony.ru')->aliasLangs('www'));
    }

    public function testAddIsIdempotentWithWarning(): void
    {
        $this->add()->run(['domain' => 'damoony.ru', 'lang' => 'en'], $this->context());

        $result = $this->add()->run(['domain' => 'damoony.ru', 'lang' => 'en'], $this->context());

        self::assertTrue($result->ok);
        self::assertCount(1, $result->warnings);
    }

    public function testListShowsFilesAndConfig(): void
    {
        $this->add()->run(['domain' => 'damoony.ru', 'lang' => 'en'], $this->context());

        $result = $this->listLangs()->run(['domain' => 'damoony.ru', 'with-config' => '1'], $this->context());

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertSame(['en'], $result->data['effective_langs']);
        self::assertSame(['en'], $result->data['config_langs']);
        self::assertTrue($result->data['rows'][0]['has_file']);
        self::assertTrue($result->data['rows'][0]['in_config']);
    }

    public function testRemoveRequiresForce(): void
    {
        $result = $this->remove()->run(['domain' => 'damoony.ru', 'lang' => 'en'], $this->context());

        self::assertFalse($result->ok);
        self::assertStringContainsString('--force', $result->errors[0]);
    }

    public function testRemoveLastProjectLangRejected(): void
    {
        $this->add()->run(['domain' => 'damoony.ru', 'lang' => 'en'], $this->context());

        $result = $this->remove()->run(['domain' => 'damoony.ru', 'lang' => 'en', 'force' => '1'], $this->context());

        self::assertFalse($result->ok);
        self::assertStringContainsString('последний язык проекта', $result->errors[0]);
    }

    public function testRemoveProjectLangBlockedByAliases(): void
    {
        $this->add()->run(['domain' => 'damoony.ru', 'lang' => 'en'], $this->context());
        $this->add()->run(['domain' => 'damoony.ru', 'lang' => 'de'], $this->context());
        $this->add()->run(['domain' => 'damoony.ru', 'allias' => 'www', 'lang' => 'en'], $this->context());

        $result = $this->remove()->run(['domain' => 'damoony.ru', 'lang' => 'en', 'force' => '1'], $this->context());

        self::assertFalse($result->ok);
        self::assertStringContainsString('используется алиасами', $result->errors[0]);
    }

    public function testRemoveAliasLangQuarantinesFile(): void
    {
        $this->add()->run(['domain' => 'damoony.ru', 'lang' => 'en'], $this->context());
        $this->add()->run(['domain' => 'damoony.ru', 'lang' => 'de'], $this->context());
        $this->add()->run(['domain' => 'damoony.ru', 'allias' => 'www', 'lang' => 'en'], $this->context());
        $this->add()->run(['domain' => 'damoony.ru', 'allias' => 'www', 'lang' => 'de'], $this->context());

        $result = $this->remove()->run(
            ['domain' => 'damoony.ru', 'allias' => 'www', 'lang' => 'en', 'force' => '1'],
            $this->context(),
        );

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertFileDoesNotExist($this->root . '/app/damoony.ru/www/.i18nlocal/en.json');
        self::assertNotContains('en', $this->site()->requireProject('damoony.ru')->aliasLangs('www'));
        self::assertNotEmpty(glob($this->root . '/var/trash/lang_damoony.ru_www_*') ?: []);
    }

    public function testSyncCopiesProjectLangs(): void
    {
        $this->add()->run(['domain' => 'damoony.ru', 'lang' => 'en'], $this->context());
        $this->add()->run(['domain' => 'damoony.ru', 'lang' => 'de'], $this->context());

        $result = $this->sync()->run(['domain' => 'damoony.ru', 'allias' => 'www'], $this->context());

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertSame(['en', 'de'], $this->site()->requireProject('damoony.ru')->aliasLangs('www'));
        self::assertFileExists($this->root . '/app/damoony.ru/www/.i18nlocal/en.json');
    }

    public function testRepairReportsDrift(): void
    {
        $this->add()->run(['domain' => 'damoony.ru', 'lang' => 'en'], $this->context());
        unlink($this->root . '/app/damoony.ru/.i18nglobal/en.json');

        $result = $this->repair()->run(['domain' => 'damoony.ru'], $this->context());

        self::assertTrue($result->ok);
        self::assertSame(['en'], $result->data['missing_files']);
        self::assertTrue($result->data['dry_run']);
    }

    public function testRepairFixCreatesMissingFiles(): void
    {
        $this->add()->run(['domain' => 'damoony.ru', 'lang' => 'en'], $this->context());
        unlink($this->root . '/app/damoony.ru/.i18nglobal/en.json');

        $result = $this->repair()->run(['domain' => 'damoony.ru', 'fix' => '1'], $this->context());

        self::assertTrue($result->ok);
        self::assertFileExists($this->root . '/app/damoony.ru/.i18nglobal/en.json');
    }

    private function site(): SiteConfig
    {
        return SiteConfig::fromFile($this->root . '/config/site.config');
    }

    private function writer(): SiteConfigWriter
    {
        return SiteConfigWriter::forRoot($this->root);
    }

    private function listLangs(): LangListCommand
    {
        return new LangListCommand(new Paths($this->root), $this->site());
    }

    private function add(): LangAddCommand
    {
        return new LangAddCommand(new Paths($this->root), $this->site(), $this->writer());
    }

    private function remove(): LangRemoveCommand
    {
        return new LangRemoveCommand(new Paths($this->root), $this->site(), $this->writer(), new Quarantine());
    }

    private function repair(): LangRepairCommand
    {
        return new LangRepairCommand(new Paths($this->root), $this->site(), $this->writer());
    }

    private function sync(): LangSyncCommand
    {
        return new LangSyncCommand(new Paths($this->root), $this->site(), $this->writer());
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
