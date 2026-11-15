<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\DbCallCommand;
use Damoony\Cli\Context;
use Damoony\Core\Config;
use Damoony\Core\Files\Paths;
use Damoony\Data\Db\Connection;
use PHPUnit\Framework\TestCase;

final class DbCallCommandTest extends TestCase
{
    private Connection $conn;

    private Config $site;
    private Paths $paths;

    protected function setUp(): void
    {
        $this->conn = new Connection(['driver' => 'sqlite', 'path' => ':memory:']);
        $pdo = $this->conn->pdo();
        $pdo->exec(
            'CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, login TEXT NOT NULL UNIQUE, email TEXT NOT NULL, password_hash TEXT NOT NULL, status TEXT NOT NULL DEFAULT "active", created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)',
        );
        $pdo->exec(
            'CREATE TABLE news (id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT NOT NULL UNIQUE, lang TEXT NOT NULL DEFAULT "ru", title TEXT NOT NULL, content TEXT NOT NULL, status TEXT NOT NULL DEFAULT "draft", published_at TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)',
        );
        $pdo->exec(
            'CREATE TABLE files (id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT NOT NULL UNIQUE, filename TEXT NOT NULL, filesize INTEGER NOT NULL DEFAULT 0, status TEXT NOT NULL DEFAULT "active", downloads INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)',
        );

        $this->site = new Config([]);
        $this->paths = new Paths(sys_get_temp_dir());
    }

    private function cmd(): DbCallCommand
    {
        return new DbCallCommand($this->paths, $this->site, $this->conn);
    }

    private function ctx(): Context
    {
        return new Context(static function (): void {
        });
    }

    public function testName(): void
    {
        $this->assertSame('db:call', $this->cmd()->name());
    }

    public function testMissingDomainFails(): void
    {
        $r = $this->cmd()->run([], $this->ctx());
        $this->assertFalse($r->ok);
    }

    public function testUnknownProviderFails(): void
    {
        $r = $this->cmd()->run([
            'domain' => 'example.ru', 'provider' => 'nope', 'method' => 'by_id',
        ], $this->ctx());
        $this->assertFalse($r->ok);
    }

    public function testWriteMethodRejected(): void
    {
        $r = $this->cmd()->run([
            'domain' => 'example.ru', 'provider' => 'news', 'method' => 'create',
        ], $this->ctx());
        $this->assertFalse($r->ok);
        $this->assertStringContainsString('not in whitelist', $r->errors[0]);
    }

    public function testInvalidParamsJsonFails(): void
    {
        $r = $this->cmd()->run([
            'domain' => 'example.ru', 'provider' => 'news', 'method' => 'recent',
            'params' => 'not-json',
        ], $this->ctx());
        $this->assertFalse($r->ok);
    }

    public function testMissingRequiredParamFails(): void
    {
        $r = $this->cmd()->run([
            'domain' => 'example.ru', 'provider' => 'news', 'method' => 'by_slug',
            'params' => '{}',
        ], $this->ctx());
        $this->assertFalse($r->ok);
        $this->assertStringContainsString("Missing required param 'slug'", $r->errors[0]);
    }

    public function testUnknownParamFails(): void
    {
        $r = $this->cmd()->run([
            'domain' => 'example.ru', 'provider' => 'news', 'method' => 'by_slug',
            'params' => '{"slug":"a","nope":1}',
        ], $this->ctx());
        $this->assertFalse($r->ok);
        $this->assertStringContainsString('Unknown params: nope', $r->errors[0]);
    }

    public function testTypeMismatchFails(): void
    {
        $r = $this->cmd()->run([
            'domain' => 'example.ru', 'provider' => 'news', 'method' => 'by_id',
            'params' => '{"id":[1,2,3]}',
        ], $this->ctx());
        $this->assertFalse($r->ok);
    }

    public function testCallRecentEmpty(): void
    {
        $r = $this->cmd()->run([
            'domain' => 'example.ru', 'provider' => 'news', 'method' => 'recent',
            'params' => '{}',
        ], $this->ctx());
        $this->assertTrue($r->ok);
        $this->assertSame('news', $r->data['provider']);
        $this->assertSame('recent', $r->data['method']);
        $this->assertSame([], $r->data['result']);
        $this->assertSame(0, $r->data['row_count']);
    }

    public function testCallBySlugFindsPublished(): void
    {
        $pdo = $this->conn->pdo();
        $pdo->exec("INSERT INTO news (slug, lang, title, content, status) VALUES ('about', 'ru', 'About', 'Body', 'published')");
        $pdo->exec("INSERT INTO news (slug, lang, title, content, status) VALUES ('about-en', 'en', 'About EN', 'Body', 'published')");

        $r = $this->cmd()->run([
            'domain' => 'example.ru', 'provider' => 'news', 'method' => 'by_slug',
            'params' => '{"slug":"about"}',
        ], $this->ctx());
        $this->assertTrue($r->ok);
        $this->assertSame('about', $r->data['result']['slug']);
        $this->assertSame('ru', $r->data['result']['lang']);
    }

    public function testCallBySlugWithExplicitLang(): void
    {
        $pdo = $this->conn->pdo();
        $pdo->exec("INSERT INTO news (slug, lang, title, content, status) VALUES ('about-en', 'en', 'About EN', 'Body', 'published')");

        $r = $this->cmd()->run([
            'domain' => 'example.ru', 'provider' => 'news', 'method' => 'by_slug',
            'params' => '{"slug":"about-en","lang":"en"}',
        ], $this->ctx());
        $this->assertTrue($r->ok);
        $this->assertSame('en', $r->data['result']['lang']);
    }

    public function testCallUserExists(): void
    {
        $pdo = $this->conn->pdo();
        $pdo->exec("INSERT INTO users (login, email, password_hash) VALUES ('alice', 'a@e.ru', 'h')");

        $r = $this->cmd()->run([
            'domain' => 'example.ru', 'provider' => 'user', 'method' => 'exists',
            'params' => '{"login":"alice"}',
        ], $this->ctx());
        $this->assertTrue($r->ok);
        $this->assertTrue($r->data['result']);

        $r2 = $this->cmd()->run([
            'domain' => 'example.ru', 'provider' => 'user', 'method' => 'exists',
            'params' => '{"login":"nobody"}',
        ], $this->ctx());
        $this->assertTrue($r2->ok);
        $this->assertFalse($r2->data['result']);
    }

    public function testCallFilesAllActive(): void
    {
        $pdo = $this->conn->pdo();
        $pdo->exec("INSERT INTO files (slug, filename) VALUES ('a', 'a.pdf')");
        $pdo->exec("INSERT INTO files (slug, filename) VALUES ('b', 'b.pdf')");
        $pdo->exec("INSERT INTO files (slug, filename, status) VALUES ('h', 'h.pdf', 'hidden')");

        $r = $this->cmd()->run([
            'domain' => 'example.ru', 'provider' => 'files', 'method' => 'all_active',
            'params' => '{}',
        ], $this->ctx());
        $this->assertTrue($r->ok);
        $this->assertCount(2, $r->data['result']);
        $this->assertSame(2, $r->data['row_count']);
    }

    public function testPrefixResolvedFromSiteConfig(): void
    {
        $pdo = $this->conn->pdo();
        $pdo->exec('CREATE TABLE d1_www__news (id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT NOT NULL, lang TEXT NOT NULL, title TEXT NOT NULL, content TEXT NOT NULL, status TEXT NOT NULL, published_at TEXT, created_at TEXT)');
        $pdo->exec("INSERT INTO d1_www__news (slug, lang, title, content, status) VALUES ('prefixed', 'ru', 'T', 'C', 'published')");

        $site = new Config([
            'domains_aliases_storage' => [
                'd1.ru' => ['www' => ['db' => 'main', 'prefix' => 'd1_www__']],
            ],
        ]);
        $cmd = new DbCallCommand($this->paths, $site, $this->conn);

        $r = $cmd->run([
            'domain' => 'd1.ru', 'alias' => 'www', 'provider' => 'news', 'method' => 'recent',
            'params' => '{}',
        ], $this->ctx());
        $this->assertTrue($r->ok);
        $this->assertCount(1, $r->data['result']);
        $this->assertSame('prefixed', $r->data['result'][0]['slug']);
    }

    public function testNumericStringCastToInt(): void
    {
        $pdo = $this->conn->pdo();
        $pdo->exec("INSERT INTO news (slug, lang, title, content, status) VALUES ('n', 'ru', 'T', 'C', 'draft')");

        $r = $this->cmd()->run([
            'domain' => 'example.ru', 'provider' => 'news', 'method' => 'by_id',
            'params' => '{"id":"1"}',
        ], $this->ctx());
        $this->assertTrue($r->ok);
        $this->assertNotEmpty($r->data['result']);
        $this->assertSame('n', $r->data['result']['slug']);
    }

    public function testParamsB64(): void
    {
        $pdo = $this->conn->pdo();
        $pdo->exec("INSERT INTO users (login, email, password_hash) VALUES ('admin', 'a@e.ru', 'h')");

        $b64 = base64_encode((string) json_encode(['login' => 'admin']));

        $r = $this->cmd()->run([
            'domain' => 'example.ru', 'provider' => 'user', 'method' => 'exists',
            'params_b64' => $b64,
        ], $this->ctx());
        $this->assertTrue($r->ok);
        $this->assertTrue($r->data['result']);
    }

    public function testParamsB64InvalidFails(): void
    {
        $r = $this->cmd()->run([
            'domain' => 'example.ru', 'provider' => 'news', 'method' => 'recent',
            'params_b64' => 'not-base64!!!',
        ], $this->ctx());
        $this->assertFalse($r->ok);
    }
}
