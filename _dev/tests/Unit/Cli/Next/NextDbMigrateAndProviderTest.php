<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli\Next;

use Damoony\Cli\Context;
use Damoony\Cli\Next\Commands\DataproviderAddMethodCommand;
use Damoony\Cli\Next\Commands\DbMigrateCommand;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Core\Settings\SiteConfigWriter;
use Damoony\Data\Db\Connection;
use PDO;
use PHPUnit\Framework\TestCase;

final class NextDbMigrateAndProviderTest extends TestCase
{
    private string $root;
    private string $dbFile;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_cli_migrate_' . bin2hex(random_bytes(6));
        mkdir($this->root . '/config', 0o775, true);
        mkdir($this->root . '/migrations', 0o775, true);
        mkdir($this->root . '/var', 0o775, true);

        $writer = SiteConfigWriter::forRoot($this->root);
        $writer->ensureProject('damoony.ru', 'damoony.ru', 'damoony_ru');
        $writer->ensureMirror('damoony.ru', 'damoony.ru');
        $writer->ensureAlias('damoony.ru', 'www');

        mkdir($this->root . '/app/damoony.ru/www', 0o775, true);

        $this->dbFile = $this->root . '/var/test.db';
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    public function testMigrateAppliesAndIsIdempotent(): void
    {
        file_put_contents(
            $this->root . '/migrations/001_create_notes.sql',
            'CREATE TABLE notes (id INTEGER PRIMARY KEY, title TEXT);',
        );

        $first = $this->migrate()->run([], $this->context());
        self::assertTrue($first->ok, implode('; ', $first->errors));
        self::assertCount(1, $first->data['applied']);

        $pdo = new PDO('sqlite:' . $this->dbFile);
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='notes'")->fetchAll(PDO::FETCH_COLUMN);
        self::assertSame(['notes'], $tables);

        $second = $this->migrate()->run([], $this->context());
        self::assertTrue($second->ok, implode('; ', $second->errors));
        self::assertSame([], $second->data['applied']);
    }

    public function testMigrateRejectsDirOutsideMigrations(): void
    {
        mkdir($this->root . '/outside', 0o775, true);

        $result = $this->migrate()->run(['dir' => $this->root . '/outside'], $this->context());

        self::assertFalse($result->ok);
        self::assertStringContainsString('--dir', $result->errors[0]);
    }

    public function testMigrateWithoutDbReportsClearError(): void
    {
        $result = new DbMigrateCommand(new Paths($this->root), null)->run([], $this->context());

        self::assertFalse($result->ok);
        self::assertStringContainsString('БД не сконфигурирована', $result->errors[0]);
    }

    public function testAddMethodCreatesProviderAndMethod(): void
    {
        $args = [
            'method_name' => 'by_status',
            'fields' => ['*'],
            'params' => [['name' => 'status', 'type' => 'string']],
            'where' => [['field' => 'status', 'param' => 'status']],
        ];

        $result = $this->addMethod()->run(
            ['domain' => 'damoony.ru', 'alias' => 'www', 'provider' => 'notes', 'template' => 'select_where', 'args_b64' => base64_encode((string) json_encode($args))],
            $this->context(),
        );

        self::assertTrue($result->ok, implode('; ', $result->errors));

        $file = $this->root . '/app/damoony.ru/www/.dbprovider/NotesProvider.php';
        self::assertFileExists($file);

        $content = (string) file_get_contents($file);
        self::assertStringContainsString('namespace damoony_ru\www\provider;', $content);
        self::assertStringContainsString('final class NotesProvider', $content);
        self::assertStringContainsString('function by_status', $content);
    }

    public function testAddMethodRejectsDuplicate(): void
    {
        $args = [
            'method_name' => 'by_status',
            'fields' => ['*'],
            'params' => [['name' => 'status', 'type' => 'string']],
            'where' => [['field' => 'status', 'param' => 'status']],
        ];
        $encoded = base64_encode((string) json_encode($args));

        $this->addMethod()->run(
            ['domain' => 'damoony.ru', 'alias' => 'www', 'provider' => 'notes', 'template' => 'select_where', 'args_b64' => $encoded],
            $this->context(),
        );

        $second = $this->addMethod()->run(
            ['domain' => 'damoony.ru', 'alias' => 'www', 'provider' => 'notes', 'template' => 'select_where', 'args_b64' => $encoded],
            $this->context(),
        );

        self::assertFalse($second->ok);
        self::assertStringContainsString('уже существует', $second->errors[0]);
    }

    public function testAddMethodRejectsUnknownTemplate(): void
    {
        $result = $this->addMethod()->run(
            ['domain' => 'damoony.ru', 'provider' => 'notes', 'template' => 'drop_table', 'args' => '{}'],
            $this->context(),
        );

        self::assertFalse($result->ok);
        self::assertStringContainsString('Неизвестный шаблон', $result->errors[0]);
    }

    public function testAddMethodRejectsBrokenArgs(): void
    {
        $result = $this->addMethod()->run(
            ['domain' => 'damoony.ru', 'provider' => 'notes', 'template' => 'insert', 'args' => '{broken'],
            $this->context(),
        );

        self::assertFalse($result->ok);
        self::assertStringContainsString('JSON', $result->errors[0]);
    }

    private function migrate(): DbMigrateCommand
    {
        return new DbMigrateCommand(
            new Paths($this->root),
            new Connection(['driver' => 'sqlite', 'path' => $this->dbFile]),
        );
    }

    private function addMethod(): DataproviderAddMethodCommand
    {
        return new DataproviderAddMethodCommand(
            new Paths($this->root),
            SiteConfig::fromFile($this->root . '/config/site.config'),
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
