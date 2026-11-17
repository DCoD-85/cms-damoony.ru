<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\SchemeCopyCommand;
use Damoony\Cli\Commands\SchemeListCommand;
use Damoony\Cli\Commands\SchemeNewCommand;
use Damoony\Cli\Commands\SchemeRemoveCommand;
use Damoony\Cli\Commands\SchemeSaveCommand;
use Damoony\Cli\Context;
use Damoony\Cli\Support\Quarantine;
use Damoony\Cli\Support\SessionGuard;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Core\Settings\SiteConfigWriter;
use PHPUnit\Framework\TestCase;

final class SchemeCommandsTest extends TestCase
{
    private string $root;
    private string $schemesDir;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_cli_scheme_' . bin2hex(random_bytes(6));
        mkdir($this->root . '/config', 0o775, true);

        $writer = SiteConfigWriter::forRoot($this->root);
        $writer->ensureProject('damoony.ru', 'damoony.ru', 'damoony_ru');
        $writer->ensureMirror('damoony.ru', 'damoony.ru');
        $writer->ensureMirror('damoony.ru', 'damoony3.ru');
        $writer->ensureAlias('damoony.ru', 'www');

        $this->schemesDir = $this->root . '/app/damoony.ru/.schemesglobal';
        mkdir($this->schemesDir, 0o775, true);
        mkdir($this->root . '/app/damoony.ru/.blocksglobal/top', 0o775, true);
        file_put_contents(
            $this->root . '/app/damoony.ru/.blocksglobal/top/top.rules.json',
            json_encode(['enabled' => true, 'type' => 'script']),
        );
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    public function testListEmpty(): void
    {
        $result = $this->list()->run(['domain' => 'damoony.ru'], $this->context());

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertSame([], $result->data['rows']);
    }

    public function testListReportsSchemes(): void
    {
        file_put_contents($this->schemesDir . '/www.php', "<?php return [];\n");
        file_put_contents($this->schemesDir . '/www.rules.json', json_encode(['enabled' => true]));
        file_put_contents($this->schemesDir . '/www.editor.json', '{}');
        file_put_contents($this->schemesDir . '/old.php', "<?php return [];\n");
        file_put_contents($this->schemesDir . '/old.rules.json', json_encode(['enabled' => false]));

        $result = $this->list()->run(['domain' => 'damoony3.ru'], $this->context());

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertSame('damoony.ru', $result->data['project']);
        self::assertCount(2, $result->data['rows']);

        $rows = $result->data['rows'];
        self::assertSame('old', $rows[0]['name']);
        self::assertFalse($rows[0]['enabled']);
        self::assertFalse($rows[0]['has_editor']);

        self::assertSame('www', $rows[1]['name']);
        self::assertTrue($rows[1]['enabled']);
        self::assertTrue($rows[1]['has_editor']);
        self::assertTrue($rows[1]['is_default']);
    }

    public function testNewCreatesThreeFiles(): void
    {
        $result = $this->newScheme()->run(['domain' => 'damoony.ru', 'name' => 'landing'], $this->context());

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertFileExists($this->schemesDir . '/landing.php');
        self::assertFileExists($this->schemesDir . '/landing.rules.json');
        self::assertFileExists($this->schemesDir . '/landing.editor.json');

        $editor = json_decode((string) file_get_contents($this->schemesDir . '/landing.editor.json'), true);
        self::assertIsArray($editor);
        self::assertSame([['name' => 'top', 'type' => 'script']], $editor['fields']);
    }

    public function testNewRejectsExisting(): void
    {
        file_put_contents($this->schemesDir . '/landing.php', "<?php return [];\n");

        $result = $this->newScheme()->run(['domain' => 'damoony.ru', 'name' => 'landing'], $this->context());

        self::assertFalse($result->ok);
        self::assertSame(['Схема уже существует'], $result->errors);
    }

    public function testNewRejectsInvalidName(): void
    {
        $result = $this->newScheme()->run(['domain' => 'damoony.ru', 'name' => 'Bad'], $this->context());

        self::assertFalse($result->ok);
        self::assertSame(['Некорректное имя схемы'], $result->errors);
    }

    public function testNewDryRun(): void
    {
        $result = $this->newScheme()->run(['domain' => 'damoony.ru', 'name' => 'landing'], $this->dryRunContext());

        self::assertTrue($result->ok);
        self::assertTrue($result->data['dry_run']);
        self::assertFileDoesNotExist($this->schemesDir . '/landing.php');
    }

    public function testCopyCreatesUniqueName(): void
    {
        file_put_contents($this->schemesDir . '/www.php', "<?php return [];\n");
        file_put_contents($this->schemesDir . '/www.rules.json', json_encode(['enabled' => true]));
        file_put_contents($this->schemesDir . '/www.editor.json', '{}');

        $first = $this->copy()->run(['domain' => 'damoony.ru', 'name' => 'www'], $this->context());
        self::assertTrue($first->ok, implode('; ', $first->errors));
        self::assertSame('www_copy', $first->data['to']);
        self::assertFileExists($this->schemesDir . '/www_copy.php');
        self::assertFileExists($this->schemesDir . '/www_copy.rules.json');
        self::assertFileExists($this->schemesDir . '/www_copy.editor.json');

        $second = $this->copy()->run(['domain' => 'damoony.ru', 'name' => 'www'], $this->context());
        self::assertTrue($second->ok, implode('; ', $second->errors));
        self::assertSame('www_copy_1', $second->data['to']);
    }

    public function testCopyRejectsMissingScheme(): void
    {
        $result = $this->copy()->run(['domain' => 'damoony.ru', 'name' => 'ghost'], $this->context());

        self::assertFalse($result->ok);
        self::assertStringContainsString('Схема не найдена', $result->errors[0]);
    }

    public function testRemoveRequiresForce(): void
    {
        $result = $this->remove()->run(['domain' => 'damoony.ru', 'name' => 'www'], $this->context());

        self::assertFalse($result->ok);
        self::assertStringContainsString('--force', $result->errors[0]);
    }

    public function testRemoveQuarantinesThreeFiles(): void
    {
        file_put_contents($this->schemesDir . '/www.php', "<?php return [];\n");
        file_put_contents($this->schemesDir . '/www.rules.json', json_encode(['enabled' => true]));
        file_put_contents($this->schemesDir . '/www.editor.json', '{}');

        $result = $this->remove()->run(['domain' => 'damoony.ru', 'name' => 'www', 'force' => '1'], $this->context());

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertFileDoesNotExist($this->schemesDir . '/www.php');
        self::assertFileDoesNotExist($this->schemesDir . '/www.rules.json');
        self::assertFileDoesNotExist($this->schemesDir . '/www.editor.json');

        $trash = glob($this->schemesDir . '/www_trash_*') ?: [];
        self::assertCount(1, $trash);
        self::assertFileExists($trash[0] . '/www.php');
        self::assertSame(['www.php', 'www.rules.json', 'www.editor.json'], $result->data['quarantined_files']);
    }

    public function testRemoveDryRunKeepsFiles(): void
    {
        file_put_contents($this->schemesDir . '/www.php', "<?php return [];\n");

        $result = $this->remove()->run(['domain' => 'damoony.ru', 'name' => 'www', 'force' => '1'], $this->dryRunContext());

        self::assertTrue($result->ok);
        self::assertTrue($result->data['dry_run']);
        self::assertFileExists($this->schemesDir . '/www.php');
    }

    public function testRemoveMissingSchemeIsHarmless(): void
    {
        $result = $this->remove()->run(['domain' => 'damoony.ru', 'name' => 'ghost', 'force' => '1'], $this->context());

        self::assertTrue($result->ok);
        self::assertCount(1, $result->warnings);
    }

    public function testSaveWritesEditorAndCompilesPhp(): void
    {
        file_put_contents($this->schemesDir . '/www.php', "<?php return [];\n");
        file_put_contents(
            $this->schemesDir . '/www.editor.json',
            json_encode(['fields' => [], 'scheme' => ['children' => []]]),
        );

        $scheme = json_encode([
            'children' => [
                ['type' => 'slot', 'name' => 'center'],
                ['type' => 'element', 'tag' => 'header', 'class' => 'top'],
                ['type' => 'element', 'tag' => 'script'],
            ],
            'meta' => ['css' => ['/a.css']],
        ]);

        $result = $this->save()->run(
            ['domain' => 'damoony3.ru', 'name' => 'www', 'scheme_json' => (string) $scheme],
            $this->context(),
        );

        self::assertTrue($result->ok, implode('; ', $result->errors));

        $editor = json_decode((string) file_get_contents($this->schemesDir . '/www.editor.json'), true);
        self::assertIsArray($editor);
        self::assertCount(3, $editor['scheme']['children']);

        $php = (string) file_get_contents($this->schemesDir . '/www.php');
        self::assertStringContainsString('center', $php);
        self::assertStringContainsString('header', $php);
        self::assertStringNotContainsString("'tag' => 'script'", $php);
        self::assertStringContainsString('/a.css', $php);
    }

    public function testSaveRequiresEditor(): void
    {
        $result = $this->save()->run(
            ['domain' => 'damoony.ru', 'name' => 'ghost', 'scheme_json' => '{"children":[]}'],
            $this->context(),
        );

        self::assertFalse($result->ok);
        self::assertStringContainsString('editor.json не найден', $result->errors[0]);
    }

    public function testSaveRequiresJson(): void
    {
        $result = $this->save()->run(['domain' => 'damoony.ru', 'name' => 'www'], $this->context());

        self::assertFalse($result->ok);
        self::assertStringContainsString('--scheme_json', $result->errors[0]);
    }

    public function testSaveRejectsBrokenJson(): void
    {
        $result = $this->save()->run(
            ['domain' => 'damoony.ru', 'name' => 'www', 'scheme_json' => '{broken'],
            $this->context(),
        );

        self::assertFalse($result->ok);
        self::assertStringContainsString('Некорректный JSON', $result->errors[0]);
    }

    public function testSaveDryRunKeepsFiles(): void
    {
        file_put_contents($this->schemesDir . '/www.php', "<?php return [];\n");
        file_put_contents($this->schemesDir . '/www.editor.json', json_encode(['scheme' => ['children' => []]]));
        $before = (string) file_get_contents($this->schemesDir . '/www.editor.json');

        $result = $this->save()->run(
            ['domain' => 'damoony.ru', 'name' => 'www', 'scheme_json' => '{"children":[{"type":"slot","name":"center"}]}'],
            $this->dryRunContext(),
        );

        self::assertTrue($result->ok);
        self::assertTrue($result->data['dry_run']);
        self::assertSame($before, (string) file_get_contents($this->schemesDir . '/www.editor.json'));
    }

    public function testSaveHonoursSessionPermission(): void
    {
        file_put_contents($this->schemesDir . '/www.php', "<?php return [];\n");
        file_put_contents($this->schemesDir . '/www.editor.json', json_encode(['scheme' => ['children' => []]]));

        $tab = 'abcdef1234567890';
        $sessionDir = $this->root . '/app/.sess_' . $tab . '.damoony.ru';
        mkdir($sessionDir, 0o775, true);
        file_put_contents(
            $sessionDir . '/.session.json',
            json_encode(['permissions' => ['scheme:save' => false]]),
        );

        $result = $this->save()->run(
            ['domain' => 'damoony.ru', 'name' => 'www', 'scheme_json' => '{"children":[]}', 'session' => $tab],
            $this->context(),
        );

        self::assertFalse($result->ok);
        self::assertStringContainsString('запрещена в этой сессии', $result->errors[0]);
    }

    private function site(): SiteConfig
    {
        return SiteConfig::fromFile($this->root . '/config/site.config');
    }

    private function list(): SchemeListCommand
    {
        return new SchemeListCommand(new Paths($this->root), $this->site());
    }

    private function newScheme(): SchemeNewCommand
    {
        return new SchemeNewCommand(new Paths($this->root), $this->site());
    }

    private function copy(): SchemeCopyCommand
    {
        return new SchemeCopyCommand(new Paths($this->root), $this->site());
    }

    private function remove(): SchemeRemoveCommand
    {
        return new SchemeRemoveCommand(new Paths($this->root), $this->site(), new Quarantine());
    }

    private function save(): SchemeSaveCommand
    {
        $paths = new Paths($this->root);

        return new SchemeSaveCommand($paths, $this->site(), new SessionGuard($paths));
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
