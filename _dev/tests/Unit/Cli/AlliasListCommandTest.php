<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\AlliasListCommand;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Files\Paths;
use PHPUnit\Framework\TestCase;

final class AlliasListCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_allias_list_' . bin2hex(random_bytes(4));
        mkdir($this->root . '/config', 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->rmrf($this->root);
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

    private function writeConfig(array $data): void
    {
        file_put_contents($this->root . '/config/site.config', json_encode($data));
    }

    private function execute(array $args): Result
    {
        return (new AlliasListCommand(new Paths($this->root)))
            ->run($args, new Context(static fn () => null));
    }

    public function testRejectsInvalidDomain(): void
    {
        $res = $this->execute(['domain' => 'a..ru']);
        self::assertFalse($res->ok);
    }

    public function testRejectsMissingDomain(): void
    {
        $this->writeConfig([
            'domains_real' => ['example.ru' => 'example.ru'],
            'alliases_real' => ['example.ru' => ['www' => 'www']],
        ]);
        $res = $this->execute(['domain' => 'ghost.ru']);
        self::assertFalse($res->ok);
    }

    public function testEmptyAliases(): void
    {
        $this->writeConfig([
            'domains_real' => ['example.ru' => 'example.ru'],
            'alliases_real' => ['example.ru' => []],
        ]);
        $res = $this->execute(['domain' => 'example.ru']);
        self::assertTrue($res->ok);
        self::assertSame([], $res->data['rows']);
    }

    public function testListsAliases(): void
    {
        $this->writeConfig([
            'domains_real' => ['example.ru' => 'example.ru'],
            'alliases_real' => ['example.ru' => ['www' => 'www', 'doc' => 'doc']],
            'alliases_available' => ['example.ru' => ['www' => true, 'doc' => false]],
        ]);
        mkdir($this->root . '/app/example.ru/www/algoritm/about', 0o777, true);
        file_put_contents($this->root . '/app/example.ru/www/algoritm/algoritm.php', '<?php');
        file_put_contents($this->root . '/app/example.ru/www/algoritm/about/algoritm_about.php', '<?php');

        $res = $this->execute(['domain' => 'example.ru']);
        self::assertTrue($res->ok);
        self::assertCount(2, $res->data['rows']);

        $byName = [];
        foreach ($res->data['rows'] as $row) {
            $byName[$row['name']] = $row;
        }
        self::assertSame('www', $byName['www']['real']);
        self::assertTrue($byName['www']['dir_exists']);
        self::assertTrue($byName['www']['available']);
        self::assertGreaterThanOrEqual(1, $byName['www']['page_count']);

        self::assertSame('doc', $byName['doc']['real']);
        self::assertFalse($byName['doc']['dir_exists']);
        self::assertFalse($byName['doc']['available']);
    }

    public function testSkipsEmptyKeys(): void
    {
        $this->writeConfig([
            'domains_real' => ['example.ru' => 'example.ru'],
            'alliases_real' => ['example.ru' => ['' => 'www', 'www' => 'www']],
        ]);
        $res = $this->execute(['domain' => 'example.ru']);
        self::assertTrue($res->ok);
        self::assertCount(1, $res->data['rows']);
        self::assertSame('www', $res->data['rows'][0]['name']);
    }
}