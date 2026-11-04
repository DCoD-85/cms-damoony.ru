<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\DbMutateCommand;
use Damoony\Cli\Context;
use Damoony\Core\Config;
use Damoony\Db\Connection;
use Damoony\Files\Paths;
use PHPUnit\Framework\TestCase;

final class DbMutateCommandTest extends TestCase
{
    private Connection $conn;

    private Config $site;
    private Paths $paths;

    protected function setUp(): void
    {
        $this->conn = new Connection(['driver' => 'sqlite', 'path' => ':memory:']);
        $pdo = $this->conn->pdo();
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, login TEXT NOT NULL UNIQUE, email TEXT NOT NULL, password_hash TEXT NOT NULL, status TEXT NOT NULL DEFAULT "active", created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $pdo->exec('CREATE TABLE news (id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT NOT NULL UNIQUE, lang TEXT NOT NULL DEFAULT "ru", title TEXT NOT NULL, content TEXT NOT NULL, status TEXT NOT NULL DEFAULT "draft", published_at TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $pdo->exec('CREATE TABLE files (id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT NOT NULL UNIQUE, filename TEXT NOT NULL, filesize INTEGER NOT NULL DEFAULT 0, status TEXT NOT NULL DEFAULT "active", downloads INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $pdo->exec('CREATE TABLE audit_log (id INTEGER PRIMARY KEY AUTOINCREMENT, domain TEXT NOT NULL, alias_name TEXT NOT NULL, provider TEXT NOT NULL, method TEXT NOT NULL, params_json TEXT, result_ok INTEGER NOT NULL, error_text TEXT, source TEXT NOT NULL, role_name TEXT NOT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $this->site = new Config([]);
        $this->paths = new Paths(sys_get_temp_dir());
    }

    private function cmd(): DbMutateCommand
    {
        return new DbMutateCommand($this->paths, $this->site, $this->conn);
    }

    private function ctx(): Context
    {
        return new Context(static function (): void {
        });
    }

    public function testName(): void
    {
        $this->assertSame('db:mutate', $this->cmd()->name());
    }

    public function testMissingDomainFails(): void
    {
        $r = $this->cmd()->run([], $this->ctx());
        $this->assertFalse($r->ok);
    }

    public function testReadMethodRejected(): void
    {
        $r = $this->cmd()->run([
            'domain' => 'example.ru', 'provider' => 'news', 'method' => 'recent',
            'params' => '{}', 'force' => '1',
        ], $this->ctx());
        $this->assertFalse($r->ok);
        $this->assertStringContainsString('not in write whitelist', $r->errors[0]);
    }

    public function testForceRequired(): void
    {
        $r = $this->cmd()->run([
            'domain' => 'example.ru', 'provider' => 'news', 'method' => 'create',
            'params' => '{"slug":"a","title":"A"}',
        ], $this->ctx());
        $this->assertFalse($r->ok);
        $this->assertStringContainsString('require --force', $r->errors[0]);
    }

    public function testCreateNewsSucceeds(): void
    {
        $r = $this->cmd()->run([
            'domain' => 'example.ru', 'provider' => 'news', 'method' => 'create',
            'params' => '{"slug":"hello","title":"Hello","content":"Body"}',
            'force' => '1',
        ], $this->ctx());
        $this->assertTrue($r->ok);
        $this->assertGreaterThan(0, $r->data['result']);

        $pdo = $this->conn->pdo();
        $row = $pdo->query("SELECT * FROM news WHERE slug='hello'")->fetch();
        $this->assertNotEmpty($row);
        $this->assertSame('Hello', $row['title']);
    }

    public function testCreateWritesAuditLog(): void
    {
        $this->cmd()->run([
            'domain' => 'example.ru', 'provider' => 'news', 'method' => 'create',
            'params' => '{"slug":"audited","title":"A"}',
            'force' => '1',
            'source' => 'ai',
            'role' => 'executor',
        ], $this->ctx());

        $pdo = $this->conn->pdo();
        $logs = $pdo->query('SELECT * FROM audit_log')->fetchAll();
        $this->assertCount(1, $logs);
        $this->assertSame('news', $logs[0]['provider']);
        $this->assertSame('create', $logs[0]['method']);
        $this->assertSame(1, (int) $logs[0]['result_ok']);
        $this->assertSame('ai', $logs[0]['source']);
        $this->assertSame('executor', $logs[0]['role_name']);
    }

    public function testCreateFilesB64(): void
    {
        $b64 = base64_encode((string) json_encode(['slug' => 'doc1', 'filename' => 'doc1.pdf', 'filesize' => 1024]));
        $r = $this->cmd()->run([
            'domain' => 'example.ru', 'provider' => 'files', 'method' => 'create',
            'params_b64' => $b64,
            'force' => '1',
        ], $this->ctx());
        $this->assertTrue($r->ok);
    }

    public function testDeleteNews(): void
    {
        $pdo = $this->conn->pdo();
        $pdo->exec("INSERT INTO news (slug, lang, title, content, status) VALUES ('del', 'ru', 'D', 'C', 'draft')");

        $r = $this->cmd()->run([
            'domain' => 'example.ru', 'provider' => 'news', 'method' => 'delete',
            'params' => '{"id":1}', 'force' => '1',
        ], $this->ctx());
        $this->assertTrue($r->ok);

        $row = $pdo->query('SELECT COUNT(*) FROM news')->fetchColumn();
        $this->assertSame(0, (int) $row);
    }

    public function testMissingRequiredParamFails(): void
    {
        $r = $this->cmd()->run([
            'domain' => 'example.ru', 'provider' => 'files', 'method' => 'create',
            'params' => '{"slug":"a"}', 'force' => '1', // filename is missing
        ], $this->ctx());
        $this->assertFalse($r->ok);
    }

    public function testUnknownParamFails(): void
    {
        $r = $this->cmd()->run([
            'domain' => 'example.ru', 'provider' => 'files', 'method' => 'create',
            'params' => '{"slug":"a","filename":"a.pdf","nope":1}', 'force' => '1',
        ], $this->ctx());
        $this->assertFalse($r->ok);
        $this->assertStringContainsString('Unknown params: nope', $r->errors[0]);
    }

    public function testMutationFailureAuditsError(): void
    {
        // Два insert с одним slug → UNIQUE constraint violation
        $pdo = $this->conn->pdo();
        $pdo->exec("INSERT INTO news (slug, lang, title, content, status) VALUES ('dup', 'ru', 'T', 'C', 'draft')");

        $r = $this->cmd()->run([
            'domain' => 'example.ru', 'provider' => 'news', 'method' => 'create',
            'params' => '{"slug":"dup","title":"T2"}', 'force' => '1',
        ], $this->ctx());
        $this->assertFalse($r->ok);

        $logs = $pdo->query('SELECT * FROM audit_log')->fetchAll();
        $this->assertCount(1, $logs);
        $this->assertSame(0, (int) $logs[0]['result_ok']);
        $this->assertNotEmpty($logs[0]['error_text']);
    }

    public function testAuditFailureDoesNotBreakMutation(): void
    {
        // Drop audit_log — mutate должен пройти, audit упадёт, но mutation ok
        $pdo = $this->conn->pdo();
        $pdo->exec('DROP TABLE audit_log');

        $r = $this->cmd()->run([
            'domain' => 'example.ru', 'provider' => 'news', 'method' => 'create',
            'params' => '{"slug":"x","title":"X"}', 'force' => '1',
        ], $this->ctx());

        $this->assertTrue($r->ok);
        $this->assertNotEmpty($r->warnings);
        $this->assertStringContainsString('audit log failed', $r->warnings[0]);
    }
}
