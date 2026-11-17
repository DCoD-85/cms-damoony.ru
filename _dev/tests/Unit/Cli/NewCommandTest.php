<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\NewCommand;
use Damoony\Cli\Context;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Core\Settings\SiteConfigWriter;
use PHPUnit\Framework\TestCase;

final class NewCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_cli_new_' . bin2hex(random_bytes(6));
        mkdir($this->root . '/config', 0o775, true);

        SiteConfigWriter::forRoot($this->root)->save(['domains_app' => []]);

        // Мини-сборка: app/ + media/ + shared/ с плейсхолдерами.
        $asm = $this->root . '/stubs/assemblies/demo';
        mkdir($asm . '/app/www/algoritm', 0o775, true);
        mkdir($asm . '/media/skin/default/css', 0o775, true);
        mkdir($asm . '/shared/css', 0o775, true);

        file_put_contents($asm . '/assembly.json', json_encode(['name' => 'Demo']));
        file_put_contents(
            $asm . '/app/www/algoritm/algoritm.php',
            "<?php\n\nnamespace {{namespace}}\\{{alias}};\n// domain={{domain}} real={{real}} alias={{alias}}\n",
        );
        file_put_contents($asm . '/app/www/algoritm/algoritm.rules.json', json_encode(['available' => ['' => '^$']]));
        file_put_contents($asm . '/media/skin/default/css/main.css', '/* {{domain}} */');
        file_put_contents($asm . '/shared/css/base.css', '/* shared */');
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    public function testBuildsProjectFromAssembly(): void
    {
        $result = $this->command()->run(['domain' => 'damoony.ru', 'assembly' => 'demo'], $this->context());

        self::assertTrue($result->ok, implode('; ', $result->errors));

        $php = (string) file_get_contents($this->root . '/app/damoony.ru/www/algoritm/algoritm.php');
        self::assertStringContainsString('namespace damoony_ru\www;', $php);
        self::assertStringContainsString('domain=damoony.ru', $php);

        self::assertFileExists($this->root . '/www/media/damoony.ru/www/skin/default/css/main.css');
        self::assertFileExists($this->root . '/www/media/shared/css/base.css');

        $site = SiteConfig::fromFile($this->root . '/config/site.config');
        self::assertSame('damoony.ru', $site->folder('damoony.ru'));
        self::assertSame('damoony_ru', $site->namespace('damoony.ru'));
        self::assertSame('damoony.ru', $site->folderByHost('damoony.ru'));
        self::assertSame(['ru', 'en'], $site->requireProject('damoony.ru')->aliasLangs('www'));
        self::assertSame(['ru', 'en'], $site->requireProject('damoony.ru')->langs);
    }

    public function testRejectsUnknownAssembly(): void
    {
        $result = $this->command()->run(['domain' => 'damoony.ru', 'assembly' => 'ghost'], $this->context());

        self::assertFalse($result->ok);
        self::assertStringContainsString('не найдена в stubs/assemblies', $result->errors[0]);
    }

    public function testRejectsInvalidDomain(): void
    {
        $result = $this->command()->run(['domain' => 'not a domain', 'assembly' => 'demo'], $this->context());

        self::assertFalse($result->ok);
        self::assertStringContainsString('Некорректный домен', $result->errors[0]);
    }

    public function testDryRunCreatesNothing(): void
    {
        $result = $this->command()->run(['domain' => 'damoony.ru', 'assembly' => 'demo'], $this->dryRunContext());

        self::assertTrue($result->ok);
        self::assertTrue($result->data['dry_run']);
        self::assertDirectoryDoesNotExist($this->root . '/app/damoony.ru');
        self::assertFalse(SiteConfig::fromFile($this->root . '/config/site.config')->has('damoony.ru'));
    }

    public function testReusesExistingProject(): void
    {
        $writer = SiteConfigWriter::forRoot($this->root);
        $writer->ensureProject('damoony.ru', 'damoony.ru', 'custom_ns');
        $writer->ensureMirror('damoony.ru', 'damoony.ru');
        $writer->ensureAlias('damoony.ru', 'www');

        $result = $this->command()->run(['domain' => 'damoony.ru', 'assembly' => 'demo'], $this->context());

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertSame('custom_ns', $result->data['namespace']);

        $php = (string) file_get_contents($this->root . '/app/damoony.ru/www/algoritm/algoritm.php');
        self::assertStringContainsString('namespace custom_ns\www;', $php);
    }

    private function command(): NewCommand
    {
        return new NewCommand(
            new Paths($this->root),
            SiteConfig::fromFile($this->root . '/config/site.config'),
            SiteConfigWriter::forRoot($this->root),
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
