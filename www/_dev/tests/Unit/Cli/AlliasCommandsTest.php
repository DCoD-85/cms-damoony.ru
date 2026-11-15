<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\AlliasNewCommand;
use Damoony\Cli\Commands\AlliasRemoveCommand;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Files\Paths;
use PHPUnit\Framework\TestCase;

final class AlliasCommandsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_al_' . bin2hex(random_bytes(4));
        mkdir($this->root . '/config', 0o777, true);
        file_put_contents($this->root . '/config/site.config', json_encode([
            'domains_real' => ['example.ru' => 'example.ru'],
            'domains_available' => ['example.ru' => true],
            'domains_namespace' => ['example.ru' => 'example'],
            'alliases_real' => ['example.ru' => ['www' => 'www']],
            'alliases_available' => ['example.ru' => ['www' => true]],
        ]));
        mkdir($this->root . '/app/example.ru/www/algoritm', 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->rmrf($this->root);
    }

    private function context(Result $result): Context
    {
        return new Context(static function (string $m, bool $ok = true, string $d = '') use ($result): void {
            $result->step($m, $ok, $d);
        });
    }

    public function testS02CreateAllias(): void
    {
        $paths = new Paths($this->root);
        $cmd = new AlliasNewCommand($paths);

        $r = new Result();
        $res = $cmd->run(['domain' => 'example.ru', 'name' => 'admin'], $this->context($r));

        self::assertTrue($res->ok, implode('; ', $res->errors));
        self::assertDirectoryExists($this->root . '/app/example.ru/admin');

        // Новая модель: 3 файла с единым префиксом
        self::assertFileExists($this->root . '/app/example.ru/admin/algoritm/algoritm.php');
        self::assertFileExists($this->root . '/app/example.ru/admin/algoritm/algoritm.rules.json');
        self::assertFileExists($this->root . '/app/example.ru/admin/algoritm/algoritm.editor.json');

        // Старой модели нет
        self::assertFileDoesNotExist($this->root . '/app/example.ru/admin/algoritm/.rules.json');

        $site = json_decode((string) file_get_contents($this->root . '/config/site.config'), true);
        self::assertSame('admin', $site['alliases_real']['example.ru']['admin']);
        self::assertTrue($site['alliases_available']['example.ru']['admin']);
    }

    public function testS02RejectsDuplicate(): void
    {
        $cmd = new AlliasNewCommand(new Paths($this->root));
        $r = new Result();
        $res = $cmd->run(['domain' => 'example.ru', 'name' => 'www'], $this->context($r));
        self::assertFalse($res->ok);
    }

    public function testS02RejectsInvalidName(): void
    {
        $cmd = new AlliasNewCommand(new Paths($this->root));
        $r = new Result();
        $res = $cmd->run(['domain' => 'example.ru', 'name' => 'BadName'], $this->context($r));
        self::assertFalse($res->ok);
    }

    public function testS03RemoveAlliasInTrash(): void
    {
        $paths = new Paths($this->root);
        $create = new AlliasNewCommand($paths);
        $r1 = new Result();
        $create->run(['domain' => 'example.ru', 'name' => 'admin'], $this->context($r1));

        // Добавляем связанные ключи, чтобы проверить полную очистку
        $site = json_decode((string) file_get_contents($this->root . '/config/site.config'), true);
        $site['alliases_langs']['example.ru']['admin'] = ['ru'];
        $site['alliases_titles']['example.ru']['admin'] = 'Admin';
        $site['fillers']['example.ru']['admin'] = ['top' => true];
        file_put_contents($this->root . '/config/site.config', json_encode($site));

        $cmd = new AlliasRemoveCommand($paths);
        $r = new Result();
        $res = $cmd->run(['domain' => 'example.ru', 'name' => 'admin', 'force' => '1'], $this->context($r));

        self::assertTrue($res->ok, implode('; ', $res->errors));
        self::assertDirectoryDoesNotExist($this->root . '/app/example.ru/admin');

        $site = json_decode((string) file_get_contents($this->root . '/config/site.config'), true);
        self::assertArrayNotHasKey('admin', $site['alliases_real']['example.ru']);
        self::assertArrayNotHasKey('admin', $site['alliases_available']['example.ru']);
        self::assertArrayNotHasKey('admin', $site['alliases_langs']['example.ru']);
        self::assertArrayNotHasKey('admin', $site['alliases_titles']['example.ru']);
        self::assertArrayNotHasKey('admin', $site['fillers']['example.ru']);
    }

    public function testS03WithoutForceCancels(): void
    {
        $paths = new Paths($this->root);
        new AlliasNewCommand($paths)->run(['domain' => 'example.ru', 'name' => 'admin'], $this->context(new Result()));

        $cmd = new AlliasRemoveCommand($paths);
        $r = new Result();
        $res = $cmd->run(['domain' => 'example.ru', 'name' => 'admin'], $this->context($r));

        self::assertFalse($res->ok);
        self::assertDirectoryExists($this->root . '/app/example.ru/admin');
    }

    public function testS03MissingFolderCleansConfig(): void
    {
        $paths = new Paths($this->root);
        $site = json_decode((string) file_get_contents($this->root . '/config/site.config'), true);
        $site['alliases_real']['example.ru']['ghost'] = 'ghost';
        $site['alliases_available']['example.ru']['ghost'] = true;
        file_put_contents($this->root . '/config/site.config', json_encode($site));

        $cmd = new AlliasRemoveCommand($paths);
        $r = new Result();
        $res = $cmd->run(['domain' => 'example.ru', 'name' => 'ghost', 'force' => '1'], $this->context($r));

        self::assertTrue($res->ok);
        $site = json_decode((string) file_get_contents($this->root . '/config/site.config'), true);
        self::assertArrayNotHasKey('ghost', $site['alliases_real']['example.ru']);
        self::assertArrayNotHasKey('ghost', $site['alliases_available']['example.ru']);
    }

    public function testS03RejectsPathTraversal(): void
    {
        $cmd = new AlliasRemoveCommand(new Paths($this->root));
        $res = $cmd->run(['domain' => 'a..ru', 'name' => 'admin', 'force' => '1'], $this->context(new Result()));

        self::assertFalse($res->ok);
    }

    public function testS03ForceRequiredForConfigOnlyRemoval(): void
    {
        // Config entry exists, folder does not — old code removed config
        // without --force. Now any mutation requires --force.
        $site = json_decode((string) file_get_contents($this->root . '/config/site.config'), true);
        $site['alliases_real']['example.ru']['ghost'] = 'ghost';
        $site['alliases_available']['example.ru']['ghost'] = true;
        file_put_contents($this->root . '/config/site.config', json_encode($site));

        $cmd = new AlliasRemoveCommand(new Paths($this->root));
        $res = $cmd->run(['domain' => 'example.ru', 'name' => 'ghost'], $this->context(new Result()));

        self::assertFalse($res->ok);
        self::assertStringContainsString('--force', $res->errors[0]);

        $siteAfter = json_decode((string) file_get_contents($this->root . '/config/site.config'), true);
        self::assertArrayHasKey('ghost', $siteAfter['alliases_real']['example.ru'], 'config не тронут без --force');
    }

    public function testS03RejectsNonSelfMappedDomain(): void
    {
        // alias.ru -> real.ru: paths->allias() and config keys diverge.
        mkdir($this->root . '/app/real.ru/www/algoritm', 0o777, true);
        $site = json_decode((string) file_get_contents($this->root . '/config/site.config'), true);
        $site['domains_real']['alias.ru'] = 'real.ru';
        $site['alliases_real']['alias.ru'] = ['www' => 'www'];
        file_put_contents($this->root . '/config/site.config', json_encode($site));

        $cmd = new AlliasRemoveCommand(new Paths($this->root));
        $res = $cmd->run(
            ['domain' => 'alias.ru', 'name' => 'www', 'force' => '1'],
            $this->context(new Result()),
        );

        self::assertFalse($res->ok);
        self::assertStringContainsString('мапится', $res->errors[0]);
        self::assertDirectoryExists($this->root . '/app/real.ru/www');
    }

    private function rmrf(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $i) {
            if ($i === '.' || $i === '..') {
                continue;
            }
            $p = $dir . '/' . $i;
            is_dir($p) ? $this->rmrf($p) : @unlink($p);
        }
        @rmdir($dir);
    }
}
