<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli\Next;

use Damoony\Cli\Context;
use Damoony\Cli\Next\Commands\DbDescribeCommand;
use Damoony\Cli\Next\Commands\DbMethodsCommand;
use Damoony\Cli\Next\Commands\DbSchemaCommand;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Core\Settings\SiteConfigWriter;
use Damoony\Data\Db\Connection;
use PDO;
use PHPUnit\Framework\TestCase;

final class NextDbCommandsTest extends TestCase
{
    private string $root;
    private string $dbFile;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_cli_db_' . bin2hex(random_bytes(6));
        mkdir($this->root . '/config', 0o775, true);
        mkdir($this->root . '/var', 0o775, true);

        $writer = SiteConfigWriter::forRoot($this->root);
        $writer->ensureProject('damoony.ru', 'damoony.ru', 'damoony_ru');
        $writer->ensureMirror('damoony.ru', 'damoony.ru');
        $writer->ensureAlias('damoony.ru', 'www');

        $this->dbFile = $this->root . '/var/test.db';

        $pdo = new PDO('sqlite:' . $this->dbFile);
        $pdo->exec('CREATE TABLE news (id INTEGER PRIMARY KEY, title TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE d1_www__products (id INTEGER PRIMARY KEY, price INTEGER)');
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    public function testSchemaListsTables(): void
    {
        $result = $this->schema()->run(['domain' => 'damoony.ru', 'alias' => 'www'], $this->context());

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertSame('sqlite', $result->data['driver']);

        $tables = array_column($result->data['tables'], 'table');
        sort($tables);

        self::assertContains('news', $tables);
        self::assertContains('d1_www__products', $tables);
    }

    public function testSchemaAppliesStoragePrefix(): void
    {
        $writer = SiteConfigWriter::forRoot($this->root);
        $data = $writer->load();
        $data['domains_app']['damoony.ru']['aliases_storage'] = [
            'www' => ['db' => '', 'prefix' => 'd1_www__'],
        ];
        $writer->save($data);

        $result = $this->schema()->run(['domain' => 'damoony.ru', 'alias' => 'www'], $this->context());

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertSame('d1_www__', $result->data['prefix']);

        $tables = array_column($result->data['tables'], 'table');
        self::assertContains('products', $tables);
        self::assertNotContains('d1_www__products', $tables);
    }

    public function testSchemaRejectsUnknownProject(): void
    {
        $result = $this->schema()->run(['domain' => 'nope.ru'], $this->context());

        self::assertFalse($result->ok);
        self::assertStringContainsString('не найден в domains_app', $result->errors[0]);
    }

    public function testSchemaWithoutDbReportsClearError(): void
    {
        $paths = new Paths($this->root);
        $command = new DbSchemaCommand($this->site(), null);

        $result = $command->run(['domain' => 'damoony.ru'], $this->context());

        self::assertFalse($result->ok);
        self::assertStringContainsString('БД не сконфигурирована', $result->errors[0]);
        self::assertDirectoryExists($paths->root());
    }

    public function testMethodsRejectsUnknownProvider(): void
    {
        $result = $this->methods()->run(
            ['domain' => 'damoony.ru', 'alias' => 'www', 'provider' => 'ghost'],
            $this->context(),
        );

        self::assertFalse($result->ok);
        self::assertStringContainsString('Неизвестный провайдер', $result->errors[0]);
    }

    public function testMethodsListsCoreProvider(): void
    {
        $result = $this->methods()->run(
            ['domain' => 'damoony.ru', 'alias' => 'www', 'provider' => 'news'],
            $this->context(),
        );

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertNotEmpty($result->data['methods']);

        $names = array_column($result->data['methods'], 'name');
        self::assertNotContains('table', $names);
        self::assertNotContains('physicalTable', $names);
    }

    public function testDescribeUnknownProvider(): void
    {
        $result = $this->describe()->run(
            ['domain' => 'damoony.ru', 'alias' => 'www', 'provider' => 'ghost', 'method' => 'x'],
            $this->context(),
        );

        self::assertFalse($result->ok);
        self::assertStringContainsString('Неизвестный провайдер', $result->errors[0]);
    }

    public function testDescribeUnknownMethod(): void
    {
        $result = $this->describe()->run(
            ['domain' => 'damoony.ru', 'alias' => 'www', 'provider' => 'news', 'method' => 'ghost'],
            $this->context(),
        );

        self::assertFalse($result->ok);
        self::assertStringContainsString('не найден в провайдере', $result->errors[0]);
    }

    public function testDescribeKnownMethod(): void
    {
        $result = $this->describe()->run(
            ['domain' => 'damoony.ru', 'alias' => 'www', 'provider' => 'news', 'method' => 'recent'],
            $this->context(),
        );

        if (!$result->ok) {
            self::markTestSkipped('NewsProvider::recent отсутствует');
        }

        self::assertSame('news', $result->data['provider']);
        self::assertSame('recent', $result->data['method']);
    }

    public function testCallRejectsUnknownProvider(): void
    {
        $result = $this->call()->run(
            ['domain' => 'damoony.ru', 'provider' => 'ghost', 'method' => 'recent'],
            $this->context(),
        );

        self::assertFalse($result->ok);
        self::assertStringContainsString('Неизвестный провайдер', $result->errors[0]);
    }

    public function testCallRejectsMethodOutsideWhitelist(): void
    {
        $result = $this->call()->run(
            ['domain' => 'damoony.ru', 'alias' => 'www', 'provider' => 'news', 'method' => 'delete'],
            $this->context(),
        );

        self::assertFalse($result->ok);
        self::assertStringContainsString('не в whitelist', $result->errors[0]);
    }

    public function testMutateRequiresForce(): void
    {
        $result = $this->mutate()->run(
            ['domain' => 'damoony.ru', 'alias' => 'www', 'provider' => 'news', 'method' => 'create'],
            $this->context(),
        );

        self::assertFalse($result->ok);
        self::assertStringContainsString('--force=1', $result->errors[0]);
    }

    public function testMutateRejectsReadMethod(): void
    {
        $result = $this->mutate()->run(
            ['domain' => 'damoony.ru', 'alias' => 'www', 'provider' => 'news', 'method' => 'recent', 'force' => '1'],
            $this->context(),
        );

        self::assertFalse($result->ok);
        self::assertStringContainsString('не в whitelist', $result->errors[0]);
    }

    public function testCallWithoutDbReportsClearError(): void
    {
        $command = new \Damoony\Cli\Next\Commands\DbCallCommand(
            $this->site(),
            null,
            new \Damoony\Cli\Next\Support\ProviderInvoker(new Paths($this->root)),
        );

        $result = $command->run(['domain' => 'damoony.ru', 'provider' => 'news', 'method' => 'recent'], $this->context());

        self::assertFalse($result->ok);
        self::assertStringContainsString('БД не сконфигурирована', $result->errors[0]);
    }

    private function call(): \Damoony\Cli\Next\Commands\DbCallCommand
    {
        return new \Damoony\Cli\Next\Commands\DbCallCommand(
            $this->site(),
            new Connection(['driver' => 'sqlite', 'path' => $this->dbFile]),
            new \Damoony\Cli\Next\Support\ProviderInvoker(new Paths($this->root)),
        );
    }

    private function mutate(): \Damoony\Cli\Next\Commands\DbMutateCommand
    {
        return new \Damoony\Cli\Next\Commands\DbMutateCommand(
            $this->site(),
            new Connection(['driver' => 'sqlite', 'path' => $this->dbFile]),
            new \Damoony\Cli\Next\Support\ProviderInvoker(new Paths($this->root)),
        );
    }

    private function site(): SiteConfig
    {
        return SiteConfig::fromFile($this->root . '/config/site.config');
    }

    private function schema(): DbSchemaCommand
    {
        return new DbSchemaCommand($this->site(), new Connection(['driver' => 'sqlite', 'path' => $this->dbFile]));
    }

    private function methods(): DbMethodsCommand
    {
        return new DbMethodsCommand(new Paths($this->root), $this->site());
    }

    private function describe(): DbDescribeCommand
    {
        return new DbDescribeCommand(new Paths($this->root), $this->site());
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
