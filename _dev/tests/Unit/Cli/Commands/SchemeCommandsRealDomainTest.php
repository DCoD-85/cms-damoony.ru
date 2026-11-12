<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli\Commands;

use Damoony\Cli\Commands\SchemeCopyCommand;
use Damoony\Cli\Commands\SchemeNewCommand;
use Damoony\Cli\Commands\SchemeRemoveCommand;
use Damoony\Cli\Commands\SchemeSaveCommand;
use Damoony\Cli\Context;
use Damoony\Core\Files\Paths;
use PHPUnit\Framework\TestCase;

final class SchemeCommandsRealDomainTest extends TestCase
{
    private string $root;
    private Paths $paths;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_scheme_' . bin2hex(random_bytes(6));
        mkdir($this->root . '/config', 0o775, true);
        $this->paths = new Paths($this->root);

        file_put_contents(
            $this->root . '/config/site.config',
            json_encode([
                'domains_real' => ['real.ru' => 'real.ru', 'alias.ru' => 'real.ru'],
            ], JSON_THROW_ON_ERROR),
        );
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    public function testSchemeNewWithAliasDomainCreatesInRealDir(): void
    {
        new SchemeNewCommand($this->paths)->run(
            ['domain' => 'alias.ru', 'name' => 'default'],
            $this->ctx(),
        );

        $this->assertFileExists(
            $this->root . '/app/real.ru/.schemesglobal/default.php',
        );
        $this->assertFileExists(
            $this->root . '/app/real.ru/.schemesglobal/default.editor.json',
        );
        $this->assertDirectoryDoesNotExist($this->root . '/app/alias.ru');
    }

    public function testSchemeCopyWithAliasDomainCopiesInRealDir(): void
    {
        $dir = $this->root . '/app/real.ru/.schemesglobal';
        mkdir($dir, 0o775, true);
        file_put_contents($dir . '/default.php', "<?php\n\nreturn ['tag' => 'div'];\n");
        file_put_contents($dir . '/default.rules.json', json_encode(['enabled' => true], JSON_THROW_ON_ERROR));

        new SchemeCopyCommand($this->paths)->run(
            ['domain' => 'alias.ru', 'name' => 'default'],
            $this->ctx(),
        );

        $this->assertFileExists($dir . '/default_copy.php');
        $this->assertDirectoryDoesNotExist($this->root . '/app/alias.ru');
    }

    public function testSchemeRemoveWithAliasDomainMovesRealToTrash(): void
    {
        $dir = $this->root . '/app/real.ru/.schemesglobal';
        mkdir($dir, 0o775, true);
        file_put_contents($dir . '/default.php', "<?php\n\nreturn [];\n");
        file_put_contents($dir . '/default.editor.json', '{}\n');
        $this->assertFileExists($dir . '/default.php');

        new SchemeRemoveCommand($this->paths)->run(
            ['domain' => 'alias.ru', 'name' => 'default', 'force' => true],
            $this->ctx(),
        );

        $this->assertFileDoesNotExist($dir . '/default.php', 'должен быть перемещён');
        $trash = glob($dir . '/default_trash_*') ?: [];
        $this->assertNotEmpty($trash, 'в trash должна быть папка default_trash_*');
        $this->assertDirectoryDoesNotExist($this->root . '/app/alias.ru');
    }

    public function testSchemeSaveWithAliasDomainWritesToRealDir(): void
    {
        $dir = $this->root . '/app/real.ru/.schemesglobal';
        mkdir($dir, 0o775, true);
        $editor = $dir . '/default.editor.json';
        file_put_contents($editor, json_encode(['scheme' => []], JSON_THROW_ON_ERROR));
        $before = (string) file_get_contents($editor);

        new SchemeSaveCommand($this->paths)->run(
            [
                'domain' => 'alias.ru',
                'name' => 'default',
                'scheme_json' => '{"children":[],"meta":{},"scripts":[]}',
            ],
            $this->ctx(),
        );

        $after = (string) file_get_contents($editor);
        $this->assertNotSame($before, $after, 'editor.json в real.ru должен обновиться');
        $this->assertDirectoryDoesNotExist($this->root . '/app/alias.ru');
    }

    private function ctx(): Context
    {
        return new Context(static fn (): null => null);
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
