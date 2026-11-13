<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\SessionApplyCommand;
use Damoony\Cli\Commands\SessionDiscardCommand;
use Damoony\Cli\Commands\SessionListCommand;
use Damoony\Cli\Commands\SessionStartCommand;
use Damoony\Cli\Context;
use Damoony\Core\Files\Paths;
use PHPUnit\Framework\TestCase;

final class SessionCommandsTest extends TestCase
{
    private string $root;
    private Paths $paths;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/sess_' . uniqid('', true);
        mkdir($this->root . '/app/damoony3.ru/www/algoritm', 0o775, true);
        file_put_contents(
            $this->root . '/app/damoony3.ru/www/algoritm/algoritm.editor.json',
            json_encode([['type' => 'h1', 'content' => 'Original']]),
        );
        $this->paths = new Paths($this->root);
        @mkdir($this->root . '/config', 0o777, true);
        file_put_contents($this->root . '/config/site.config', json_encode(['domains_real' => ['example.ru' => 'example.ru', 'damoony3.ru' => 'damoony3.ru'], 'domains_namespace' => ['example.ru' => 'example_ru']]));
    }

    protected function tearDown(): void
    {
        $this->rmrf($this->root);
    }

    private function ctx(): Context
    {
        return new Context(static fn () => null);
    }

    public function testStartCreatesSession(): void
    {
        $cmd = new SessionStartCommand($this->paths);
        $r = $cmd->run([
            'domain' => 'damoony3.ru',
            'tab' => 'a1b2c3d4',
            'admin-sess' => 'mysess',
        ], $this->ctx());

        $this->assertTrue($r->ok, implode(',', $r->errors));
        $sessionDir = $this->root . '/app/.sess_a1b2c3d4.damoony3.ru';
        $this->assertDirectoryExists($sessionDir);
        $this->assertFileExists($sessionDir . '/.session.json');

        $meta = json_decode((string) file_get_contents($sessionDir . '/.session.json'), true);
        $this->assertIsArray($meta);
        $this->assertSame('a1b2c3d4', $meta['tab_id']);
        $this->assertSame('mysess', $meta['admin_session_id']);
    }

    public function testStartRejectsExisting(): void
    {
        $cmd = new SessionStartCommand($this->paths);
        $cmd->run(['domain' => 'damoony3.ru', 'tab' => 'a1b2c3d4'], $this->ctx());
        $r = $cmd->run(['domain' => 'damoony3.ru', 'tab' => 'a1b2c3d4'], $this->ctx());

        $this->assertFalse($r->ok);
        $this->assertStringContainsString('already exists', $r->errors[0]);
    }

    public function testStartRejectsInvalidTab(): void
    {
        $cmd = new SessionStartCommand($this->paths);
        $r = $cmd->run(['domain' => 'damoony3.ru', 'tab' => 'BAD-TAB'], $this->ctx());

        $this->assertFalse($r->ok);
        $this->assertStringContainsString('Invalid --tab', $r->errors[0]);
    }

    public function testApplySucceedsWhenHashMatches(): void
    {
        $start = new SessionStartCommand($this->paths);
        $start->run(['domain' => 'damoony3.ru', 'tab' => 'a1b2c3d4', 'admin-sess' => 'mysess'], $this->ctx());

        // Изменяем сессию
        $sessionJson = $this->root . '/app/.sess_a1b2c3d4.damoony3.ru/www/algoritm/algoritm.editor.json';
        file_put_contents($sessionJson, json_encode([['type' => 'h1', 'content' => 'New']]));

        $apply = new SessionApplyCommand($this->paths);
        $r = $apply->run([
            'domain' => 'damoony3.ru',
            'tab' => 'a1b2c3d4',
            'admin-sess' => 'mysess',
        ], $this->ctx());

        $this->assertTrue($r->ok, implode(',', $r->errors));

        $prodJson = $this->root . '/app/damoony3.ru/www/algoritm/algoritm.editor.json';
        $this->assertSame(
            [['type' => 'h1', 'content' => 'New']],
            json_decode((string) file_get_contents($prodJson), true),
        );
        $this->assertDirectoryDoesNotExist($this->root . '/app/.sess_a1b2c3d4.damoony3.ru');
    }

    public function testApplyRejectsOnHashMismatch(): void
    {
        $start = new SessionStartCommand($this->paths);
        $start->run(['domain' => 'damoony3.ru', 'tab' => 'a1b2c3d4'], $this->ctx());

        // Кто-то изменил production после старта
        $prodJson = $this->root . '/app/damoony3.ru/www/algoritm/algoritm.editor.json';
        file_put_contents($prodJson, json_encode([['type' => 'h1', 'content' => 'Concurrent']]));

        $apply = new SessionApplyCommand($this->paths);
        $r = $apply->run([
            'domain' => 'damoony3.ru',
            'tab' => 'a1b2c3d4',
            'admin-sess' => 'dummy',
        ], $this->ctx());

        $this->assertFalse($r->ok);
        $this->assertStringContainsString('Conflict', $r->errors[0]);
    }

    public function testApplyRejectsEmptyAdminSession(): void
    {
        $start = new SessionStartCommand($this->paths);
        $start->run(['domain' => 'damoony3.ru', 'tab' => 'a1b2c3d4'], $this->ctx());

        $apply = new SessionApplyCommand($this->paths);
        $r = $apply->run([
            'domain' => 'damoony3.ru',
            'tab' => 'a1b2c3d4',
        ], $this->ctx());

        $this->assertFalse($r->ok);
        $this->assertStringContainsString('Access denied', $r->errors[0]);
    }

    public function testApplyIgnoresMismatchedAdminSession(): void
    {
        // Новый контракт: admin_session_id не сравнивается (может меняться при релогине)
        $start = new SessionStartCommand($this->paths);
        $start->run(['domain' => 'damoony3.ru', 'tab' => 'a1b2c3d4', 'admin-sess' => 'mysess'], $this->ctx());

        $apply = new SessionApplyCommand($this->paths);
        $r = $apply->run([
            'domain' => 'damoony3.ru',
            'tab' => 'a1b2c3d4',
            'admin-sess' => 'other',
        ], $this->ctx());

        $this->assertTrue($r->ok);
        $this->assertTrue($r->data['applied']);
    }

    public function testDiscardMovesToTrash(): void
    {
        $start = new SessionStartCommand($this->paths);
        $start->run(['domain' => 'damoony3.ru', 'tab' => 'a1b2c3d4'], $this->ctx());

        $discard = new SessionDiscardCommand($this->paths);
        $r = $discard->run(['domain' => 'damoony3.ru', 'tab' => 'a1b2c3d4'], $this->ctx());

        $this->assertTrue($r->ok, implode(',', $r->errors));
        $this->assertDirectoryDoesNotExist($this->root . '/app/.sess_a1b2c3d4.damoony3.ru');
    }

    public function testListShowsActiveSessions(): void
    {
        $start = new SessionStartCommand($this->paths);
        $start->run(['domain' => 'damoony3.ru', 'tab' => 'a1b2c3d4'], $this->ctx());
        $start->run(['domain' => 'damoony3.ru', 'tab' => 'beefcafe'], $this->ctx());

        $list = new SessionListCommand($this->paths);
        $r = $list->run([], $this->ctx());

        $this->assertTrue($r->ok);
        $this->assertCount(2, $r->data['sessions']);
    }

    private function rmrf(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $e) {
            if ($e === '.' || $e === '..') {
                continue;
            }
            $p = $dir . '/' . $e;
            is_dir($p) ? $this->rmrf($p) : @unlink($p);
        }
        @rmdir($dir);
    }
}
