<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\FillerEditCommand;
use Damoony\Cli\Commands\FillerListCommand;
use Damoony\Cli\Commands\FillerNewCommand;
use Damoony\Cli\Commands\FillerToggleCommand;
use Damoony\Cli\Commands\FillerWriteCommand;
use Damoony\Cli\Context;
use Damoony\Cli\Support\BlockFileEditor;
use Damoony\Cli\Support\SessionGuard;
use Damoony\Cli\Support\TargetResolver;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Core\Settings\SiteConfigWriter;
use PHPUnit\Framework\TestCase;

final class FillerCommandsTest extends TestCase
{
    private string $root;
    private string $localDir;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_cli_filler_' . bin2hex(random_bytes(6));
        mkdir($this->root . '/config', 0o775, true);

        $writer = SiteConfigWriter::forRoot($this->root);
        $writer->ensureProject('damoony.ru', 'damoony.ru', 'damoony_ru');
        $writer->ensureMirror('damoony.ru', 'damoony.ru');
        $writer->ensureAlias('damoony.ru', 'www');

        mkdir($this->root . '/app/damoony.ru/www', 0o775, true);
        mkdir($this->root . '/app/damoony.ru/.blocksglobal/top', 0o775, true);
        file_put_contents($this->root . '/app/damoony.ru/.blocksglobal/top/top.php', "<?php\n");

        $this->localDir = $this->root . '/app/damoony.ru/www/.blockslocal';
        mkdir($this->localDir, 0o775, true);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    public function testNewCreatesLocalBlock(): void
    {
        $result = $this->newFiller()->run(
            ['domain' => 'damoony.ru', 'allias' => 'www', 'block' => 'top'],
            $this->context(),
        );

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertFileExists($this->localDir . '/top/top.php');
        self::assertFileExists($this->localDir . '/top/top.rules.json');
        self::assertFileExists($this->localDir . '/top/top.editor.json');

        $php = (string) file_get_contents($this->localDir . '/top/top.php');
        self::assertStringContainsString('final class blockslocal_www_top', $php);
        self::assertStringContainsString('namespace damoony_ru;', $php);
    }

    public function testNewRequiresGlobalBlock(): void
    {
        $result = $this->newFiller()->run(
            ['domain' => 'damoony.ru', 'allias' => 'www', 'block' => 'ghost'],
            $this->context(),
        );

        self::assertFalse($result->ok);
        self::assertStringContainsString('Блок не найден', $result->errors[0]);
    }

    public function testNewRejectsDuplicate(): void
    {
        $this->newFiller()->run(['domain' => 'damoony.ru', 'allias' => 'www', 'block' => 'top'], $this->context());

        $result = $this->newFiller()->run(
            ['domain' => 'damoony.ru', 'allias' => 'www', 'block' => 'top'],
            $this->context(),
        );

        self::assertFalse($result->ok);
        self::assertSame(['Filler уже существует'], $result->errors);
    }

    public function testListShowsEnrichedState(): void
    {
        $this->newFiller()->run(['domain' => 'damoony.ru', 'allias' => 'www', 'block' => 'top'], $this->context());

        $result = $this->listFiller()->run(['domain' => 'damoony.ru', 'allias' => 'www'], $this->context());

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertCount(1, $result->data['rows']);
        self::assertSame('top', $result->data['rows'][0]['block']);
        self::assertTrue($result->data['rows'][0]['enabled']);
        self::assertTrue($result->data['rows'][0]['has_rules']);
    }

    public function testToggleWritesRulesNotConfig(): void
    {
        $this->newFiller()->run(['domain' => 'damoony.ru', 'allias' => 'www', 'block' => 'top'], $this->context());
        $configBefore = (string) file_get_contents($this->root . '/config/site.config');

        $result = $this->toggleFiller()->run(
            ['domain' => 'damoony.ru', 'allias' => 'www', 'block' => 'top', 'value' => '0'],
            $this->context(),
        );

        self::assertTrue($result->ok, implode('; ', $result->errors));

        $rules = json_decode((string) file_get_contents($this->localDir . '/top/top.rules.json'), true);
        self::assertIsArray($rules);
        self::assertFalse($rules['enabled']);

        self::assertSame($configBefore, (string) file_get_contents($this->root . '/config/site.config'));
    }

    public function testToggleIsIdempotent(): void
    {
        $this->newFiller()->run(['domain' => 'damoony.ru', 'allias' => 'www', 'block' => 'top'], $this->context());

        $result = $this->toggleFiller()->run(
            ['domain' => 'damoony.ru', 'allias' => 'www', 'block' => 'top', 'value' => '1'],
            $this->context(),
        );

        self::assertTrue($result->ok);
        self::assertSame([], $result->data);
    }

    public function testToggleRequiresExistingFiller(): void
    {
        $result = $this->toggleFiller()->run(
            ['domain' => 'damoony.ru', 'allias' => 'www', 'block' => 'top'],
            $this->context(),
        );

        self::assertFalse($result->ok);
        self::assertStringContainsString('Filler не найден', $result->errors[0]);
    }

    public function testWriteCompilesIntoLebpocalBlock(): void
    {
        $this->newFiller()->run(['domain' => 'damoony.ru', 'allias' => 'www', 'block' => 'top'], $this->context());

        $json = json_encode([['type' => 'h2', 'content' => 'Локальная шапка']]);

        $result = $this->writeFiller()->run(
            ['domain' => 'damoony.ru', 'allias' => 'www', 'block' => 'top', 'json' => (string) $json],
            $this->context(),
        );

        self::assertTrue($result->ok, implode('; ', $result->errors));

        $php = (string) file_get_contents($this->localDir . '/top/top.php');
        self::assertStringContainsString('Локальная шапка', $php);
        self::assertStringContainsString('final class blockslocal_www_top', $php);
    }

    public function testEditRules(): void
    {
        $this->newFiller()->run(['domain' => 'damoony.ru', 'allias' => 'www', 'block' => 'top'], $this->context());

        $result = $this->editFiller()->run(
            [
                'domain' => 'damoony.ru',
                'allias' => 'www',
                'block' => 'top',
                'file' => 'rules',
                'content' => '{"enabled": false, "type": "visual"}',
            ],
            $this->context(),
        );

        self::assertTrue($result->ok, implode('; ', $result->errors));

        $rules = json_decode((string) file_get_contents($this->localDir . '/top/top.rules.json'), true);
        self::assertIsArray($rules);
        self::assertFalse($rules['enabled']);
    }

    public function testEditCodeStillRequiresAllowPhp(): void
    {
        $this->newFiller()->run(['domain' => 'damoony.ru', 'allias' => 'www', 'block' => 'top'], $this->context());

        $result = $this->editFiller()->run(
            [
                'domain' => 'damoony.ru',
                'allias' => 'www',
                'block' => 'top',
                'file' => 'code',
                'content' => "<?php\n",
            ],
            $this->context(),
        );

        self::assertFalse($result->ok);
        self::assertStringContainsString('allow_php_blocks=false', $result->errors[0]);
    }

    private function site(): SiteConfig
    {
        return SiteConfig::fromFile($this->root . '/config/site.config');
    }

    private function resolver(): TargetResolver
    {
        return new TargetResolver($this->site());
    }

    private function newFiller(): FillerNewCommand
    {
        return new FillerNewCommand(new Paths($this->root), $this->resolver());
    }

    private function listFiller(): FillerListCommand
    {
        return new FillerListCommand(new Paths($this->root), $this->resolver());
    }

    private function toggleFiller(): FillerToggleCommand
    {
        return new FillerToggleCommand(new Paths($this->root), $this->resolver());
    }

    private function writeFiller(): FillerWriteCommand
    {
        $paths = new Paths($this->root);

        return new FillerWriteCommand($paths, $this->resolver(), new SessionGuard($paths));
    }

    private function editFiller(): FillerEditCommand
    {
        $paths = new Paths($this->root);

        return new FillerEditCommand($paths, $this->resolver(), new BlockFileEditor($paths));
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
