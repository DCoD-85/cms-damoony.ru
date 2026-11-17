<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli\Next;

use Damoony\Cli\Context;
use Damoony\Cli\Next\Commands\BlockEditCommand;
use Damoony\Cli\Next\Commands\BlockListCommand;
use Damoony\Cli\Next\Commands\BlockNewCommand;
use Damoony\Cli\Next\Commands\BlockRemoveCommand;
use Damoony\Cli\Next\Commands\BlockWriteCommand;
use Damoony\Cli\Next\Support\BlockFileEditor;
use Damoony\Cli\Next\Support\Quarantine;
use Damoony\Cli\Next\Support\SessionGuard;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Core\Settings\SiteConfigWriter;
use PHPUnit\Framework\TestCase;

final class NextBlockCommandsTest extends TestCase
{
    private string $root;
    private string $blocksDir;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_cli_block_' . bin2hex(random_bytes(6));
        mkdir($this->root . '/config', 0o775, true);

        $writer = SiteConfigWriter::forRoot($this->root);
        $writer->ensureProject('damoony.ru', 'damoony.ru', 'damoony_ru');
        $writer->ensureMirror('damoony.ru', 'damoony.ru');
        $writer->ensureAlias('damoony.ru', 'www');

        $this->blocksDir = $this->root . '/app/damoony.ru/.blocksglobal';
        mkdir($this->blocksDir, 0o775, true);
        mkdir($this->blocksDir . '/center', 0o775, true);
        file_put_contents($this->blocksDir . '/center/center.php', "<?php\n");
        file_put_contents(
            $this->blocksDir . '/center/center.rules.json',
            json_encode(['enabled' => true, 'type' => 'mixed']),
        );
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    public function testListReportsBlocks(): void
    {
        $result = $this->blockList()->run(['domain' => 'damoony.ru'], $this->context());

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertCount(1, $result->data['rows']);
        self::assertSame('center', $result->data['rows'][0]['name']);
        self::assertSame('mixed', $result->data['rows'][0]['type']);
        self::assertTrue($result->data['rows'][0]['enabled']);
        self::assertFalse($result->data['rows'][0]['has_editor']);
    }

    public function testNewCreatesRegionBlock(): void
    {
        $result = $this->blockNew()->run(['domain' => 'damoony.ru', 'name' => 'top'], $this->context());

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertFileExists($this->blocksDir . '/top/top.php');
        self::assertFileExists($this->blocksDir . '/top/top.rules.json');
        self::assertFileExists($this->blocksDir . '/top/top.editor.json');

        $php = (string) file_get_contents($this->blocksDir . '/top/top.php');
        self::assertStringContainsString('final class blocksglobal_top implements RegionInterface', $php);
        self::assertStringContainsString('namespace damoony_ru;', $php);
    }

    public function testNewCreatesScriptBlock(): void
    {
        $result = $this->blockNew()->run(['domain' => 'damoony.ru', 'name' => 'logic', 'type' => 'script'], $this->context());

        self::assertTrue($result->ok, implode('; ', $result->errors));
        $php = (string) file_get_contents($this->blocksDir . '/logic/logic.php');
        self::assertStringContainsString('blocksglobal_logic_logic', $php);
    }

    public function testNewRejectsInvalidType(): void
    {
        $result = $this->blockNew()->run(['domain' => 'damoony.ru', 'name' => 'x', 'type' => 'weird'], $this->context());

        self::assertFalse($result->ok);
        self::assertStringContainsString('Некорректный тип', $result->errors[0]);
    }

    public function testNewRejectsExistingBlock(): void
    {
        $result = $this->blockNew()->run(['domain' => 'damoony.ru', 'name' => 'center'], $this->context());

        self::assertFalse($result->ok);
        self::assertSame(['Блок уже существует'], $result->errors);
    }

    public function testWriteCompilesJsonToPhp(): void
    {
        $json = json_encode([['type' => 'h1', 'content' => 'Заголовок блока']]);

        $result = $this->blockWrite()->run(
            ['domain' => 'damoony.ru', 'name' => 'center', 'json' => (string) $json],
            $this->context(),
        );

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertSame(1, $result->data['blocks']);

        $php = (string) file_get_contents($this->blocksDir . '/center/center.php');
        self::assertStringContainsString('Заголовок блока', $php);
        self::assertStringContainsString('final class blocksglobal_center', $php);
    }

    public function testWriteRejectsMissingBlock(): void
    {
        $result = $this->blockWrite()->run(
            ['domain' => 'damoony.ru', 'name' => 'ghost', 'json' => '[]'],
            $this->context(),
        );

        self::assertFalse($result->ok);
        self::assertSame(['Блок не найден'], $result->errors);
    }

    public function testWriteRejectsBrokenJson(): void
    {
        $result = $this->blockWrite()->run(
            ['domain' => 'damoony.ru', 'name' => 'center', 'json' => '{broken'],
            $this->context(),
        );

        self::assertFalse($result->ok);
        self::assertStringContainsString('Некорректный JSON', $result->errors[0]);
    }

    public function testEditRulesValidatesType(): void
    {
        $result = $this->blockEdit()->run(
            ['domain' => 'damoony.ru', 'name' => 'center', 'file' => 'rules', 'content' => '{"type":"weird"}'],
            $this->context(),
        );

        self::assertFalse($result->ok);
        self::assertStringContainsString('Недопустимый type', $result->errors[0]);
    }

    public function testEditRulesWritesFile(): void
    {
        $rules = json_encode(['enabled' => false, 'type' => 'visual']);

        $result = $this->blockEdit()->run(
            ['domain' => 'damoony.ru', 'name' => 'center', 'file' => 'rules', 'content' => (string) $rules],
            $this->context(),
        );

        self::assertTrue($result->ok, implode('; ', $result->errors));

        $saved = json_decode((string) file_get_contents($this->blocksDir . '/center/center.rules.json'), true);
        self::assertIsArray($saved);
        self::assertFalse($saved['enabled']);
        self::assertSame('visual', $saved['type']);
    }

    public function testEditCodeRequiresAllowPhp(): void
    {
        $result = $this->blockEdit()->run(
            ['domain' => 'damoony.ru', 'name' => 'center', 'file' => 'code', 'content' => "<?php\n"],
            $this->context(),
        );

        self::assertFalse($result->ok);
        self::assertStringContainsString('allow_php_blocks=false', $result->errors[0]);
    }

    public function testEditRejectsUnknownFile(): void
    {
        $result = $this->blockEdit()->run(
            ['domain' => 'damoony.ru', 'name' => 'center', 'file' => 'nope', 'content' => '{}'],
            $this->context(),
        );

        self::assertFalse($result->ok);
        self::assertStringContainsString('Недопустимый file', $result->errors[0]);
    }

    public function testRemoveRequiresForce(): void
    {
        $result = $this->blockRemove()->run(['domain' => 'damoony.ru', 'name' => 'center'], $this->context());

        self::assertFalse($result->ok);
        self::assertStringContainsString('--force', $result->errors[0]);
    }

    public function testRemoveQuarantinesBlock(): void
    {
        $result = $this->blockRemove()->run(
            ['domain' => 'damoony.ru', 'name' => 'center', 'force' => '1'],
            $this->context(),
        );

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertDirectoryDoesNotExist($this->blocksDir . '/center');
        self::assertNotEmpty(glob($this->blocksDir . '/center_trash_*') ?: []);
    }

    public function testRemoveMissingBlockIsHarmless(): void
    {
        $result = $this->blockRemove()->run(
            ['domain' => 'damoony.ru', 'name' => 'ghost', 'force' => '1'],
            $this->context(),
        );

        self::assertTrue($result->ok);
        self::assertCount(1, $result->warnings);
    }

    private function site(): SiteConfig
    {
        return SiteConfig::fromFile($this->root . '/config/site.config');
    }

    private function blockList(): BlockListCommand
    {
        return new BlockListCommand(new Paths($this->root), $this->site());
    }

    private function blockNew(): BlockNewCommand
    {
        return new BlockNewCommand(new Paths($this->root), $this->site());
    }

    private function blockWrite(): BlockWriteCommand
    {
        $paths = new Paths($this->root);

        return new BlockWriteCommand($paths, $this->site(), new SessionGuard($paths));
    }

    private function blockEdit(): BlockEditCommand
    {
        $paths = new Paths($this->root);

        return new BlockEditCommand($paths, $this->site(), new BlockFileEditor($paths));
    }

    private function blockRemove(): BlockRemoveCommand
    {
        return new BlockRemoveCommand(new Paths($this->root), $this->site(), new Quarantine());
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
