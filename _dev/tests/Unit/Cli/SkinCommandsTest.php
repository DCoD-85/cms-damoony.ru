<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\SkinEditCommand;
use Damoony\Cli\Commands\SkinListCommand;
use Damoony\Cli\Commands\SkinNewCommand;
use Damoony\Cli\Commands\SkinRemoveCommand;
use Damoony\Cli\Context;
use Damoony\Cli\Support\Quarantine;
use Damoony\Cli\Support\SessionGuard;
use Damoony\Cli\Support\TargetResolver;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Core\Settings\SiteConfigWriter;
use PHPUnit\Framework\TestCase;

final class SkinCommandsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_cli_skin_' . bin2hex(random_bytes(6));
        mkdir($this->root . '/config', 0o775, true);

        $writer = SiteConfigWriter::forRoot($this->root);
        $writer->ensureProject('damoony.ru', 'damoony.ru', 'damoony_ru');
        $writer->ensureMirror('damoony.ru', 'damoony.ru');
        $writer->ensureMirror('damoony.ru', 'damoony3.ru');
        $writer->ensureAlias('damoony.ru', 'www');

        mkdir($this->root . '/app/damoony.ru/www', 0o775, true);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    public function testNewCreatesSkin(): void
    {
        $result = $this->skinNew()->run(['domain' => 'damoony.ru', 'allias' => 'www'], $this->context());

        self::assertTrue($result->ok, implode('; ', $result->errors));

        $skinDir = $this->root . '/www/media/damoony.ru/www/skin/default';
        self::assertFileExists($skinDir . '/css/main.css');
        self::assertFileExists($skinDir . '/js/main.js');
        self::assertFileExists($skinDir . '/skin.json');
        self::assertDirectoryExists($skinDir . '/img');
    }

    public function testNewRejectsDuplicate(): void
    {
        $this->skinNew()->run(['domain' => 'damoony.ru', 'allias' => 'www'], $this->context());

        $result = $this->skinNew()->run(['domain' => 'damoony.ru', 'allias' => 'www'], $this->context());

        self::assertFalse($result->ok);
        self::assertSame(['Скин уже существует'], $result->errors);
    }

    public function testListReportsSkins(): void
    {
        $this->skinNew()->run(['domain' => 'damoony3.ru', 'allias' => 'www', 'name' => 'dark'], $this->context());

        $result = $this->skinList()->run(['domain' => 'damoony.ru', 'allias' => 'www'], $this->context());

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertCount(1, $result->data['rows']);
        self::assertSame('dark', $result->data['rows'][0]['name']);
        self::assertTrue($result->data['rows'][0]['has_css']);
        self::assertTrue($result->data['rows'][0]['has_meta']);
    }

    public function testEditWritesCss(): void
    {
        $this->skinNew()->run(['domain' => 'damoony.ru', 'allias' => 'www'], $this->context());

        $result = $this->skinEdit()->run(
            [
                'domain' => 'damoony.ru',
                'allias' => 'www',
                'name' => 'default',
                'file' => 'css/top.css',
                'content' => ".top { color: red; }\n",
            ],
            $this->context(),
        );

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertStringContainsString(
            '.top',
            (string) file_get_contents($this->root . '/www/media/damoony.ru/www/skin/default/css/top.css'),
        );
    }

    public function testEditRepairsUnbalancedCss(): void
    {
        $this->skinNew()->run(['domain' => 'damoony.ru', 'allias' => 'www'], $this->context());

        $result = $this->skinEdit()->run(
            [
                'domain' => 'damoony.ru',
                'allias' => 'www',
                'name' => 'default',
                'file' => 'css/main.css',
                'content' => ".a { color: red;\n",
            ],
            $this->context(),
        );

        self::assertTrue($result->ok, implode('; ', $result->errors));

        $css = (string) file_get_contents($this->root . '/www/media/damoony.ru/www/skin/default/css/main.css');
        self::assertSame(substr_count($css, '{'), substr_count($css, '}'));
    }

    public function testEditRejectsUnsafeCss(): void
    {
        $this->skinNew()->run(['domain' => 'damoony.ru', 'allias' => 'www'], $this->context());

        $result = $this->skinEdit()->run(
            [
                'domain' => 'damoony.ru',
                'allias' => 'www',
                'name' => 'default',
                'file' => 'css/main.css',
                'content' => '@import url("evil.css");',
            ],
            $this->context(),
        );

        self::assertFalse($result->ok);
        self::assertStringContainsString('небезопасные конструкции', $result->errors[0]);
    }

    public function testEditRejectsExtraClosingBrace(): void
    {
        $this->skinNew()->run(['domain' => 'damoony.ru', 'allias' => 'www'], $this->context());

        $result = $this->skinEdit()->run(
            [
                'domain' => 'damoony.ru',
                'allias' => 'www',
                'name' => 'default',
                'file' => 'css/main.css',
                'content' => '.a { } }',
            ],
            $this->context(),
        );

        self::assertFalse($result->ok);
        self::assertStringContainsString('лишние закрывающие', $result->errors[0]);
    }

    public function testEditRejectsForeignFile(): void
    {
        $this->skinNew()->run(['domain' => 'damoony.ru', 'allias' => 'www'], $this->context());

        $result = $this->skinEdit()->run(
            [
                'domain' => 'damoony.ru',
                'allias' => 'www',
                'name' => 'default',
                'file' => 'js/other.js',
                'content' => 'alert(1)',
            ],
            $this->context(),
        );

        self::assertFalse($result->ok);
        self::assertStringContainsString('Недопустимый файл', $result->errors[0]);
    }

    public function testEditHonoursSessionPermission(): void
    {
        $this->skinNew()->run(['domain' => 'damoony.ru', 'allias' => 'www'], $this->context());

        $tab = 'abcdef1234567890';
        $sessionDir = $this->root . '/app/.sess_' . $tab . '.damoony.ru';
        mkdir($sessionDir, 0o775, true);
        file_put_contents($sessionDir . '/.session.json', json_encode(['permissions' => ['skin:edit' => false]]));

        $result = $this->skinEdit()->run(
            [
                'domain' => 'damoony.ru',
                'allias' => 'www',
                'name' => 'default',
                'file' => 'css/main.css',
                'content' => '.a{}',
                'session' => $tab,
            ],
            $this->context(),
        );

        self::assertFalse($result->ok);
        self::assertStringContainsString('запрещена в этой сессии', $result->errors[0]);
    }

    public function testRemoveRequiresForce(): void
    {
        $this->skinNew()->run(['domain' => 'damoony.ru', 'allias' => 'www'], $this->context());

        $result = $this->skinRemove()->run(
            ['domain' => 'damoony.ru', 'allias' => 'www', 'name' => 'default'],
            $this->context(),
        );

        self::assertFalse($result->ok);
        self::assertStringContainsString('--force', $result->errors[0]);
    }

    public function testRemoveQuarantinesSkin(): void
    {
        $this->skinNew()->run(['domain' => 'damoony.ru', 'allias' => 'www'], $this->context());

        $result = $this->skinRemove()->run(
            ['domain' => 'damoony.ru', 'allias' => 'www', 'name' => 'default', 'force' => '1'],
            $this->context(),
        );

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertDirectoryDoesNotExist($this->root . '/www/media/damoony.ru/www/skin/default');
        self::assertNotEmpty(glob($this->root . '/www/media/.trash/damoony.ru_www_default_trash_*') ?: []);
    }

    public function testRemoveMissingSkinIsHarmless(): void
    {
        $result = $this->skinRemove()->run(
            ['domain' => 'damoony.ru', 'allias' => 'www', 'name' => 'ghost', 'force' => '1'],
            $this->context(),
        );

        self::assertTrue($result->ok);
        self::assertCount(1, $result->warnings);
    }

    private function site(): SiteConfig
    {
        return SiteConfig::fromFile($this->root . '/config/site.config');
    }

    private function resolver(): TargetResolver
    {
        return new TargetResolver($this->site());
    }

    private function skinNew(): SkinNewCommand
    {
        return new SkinNewCommand(new Paths($this->root), $this->resolver());
    }

    private function skinList(): SkinListCommand
    {
        return new SkinListCommand(new Paths($this->root), $this->resolver());
    }

    private function skinEdit(): SkinEditCommand
    {
        $paths = new Paths($this->root);

        return new SkinEditCommand($paths, $this->resolver(), new SessionGuard($paths));
    }

    private function skinRemove(): SkinRemoveCommand
    {
        return new SkinRemoveCommand(new Paths($this->root), $this->resolver(), new Quarantine());
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
