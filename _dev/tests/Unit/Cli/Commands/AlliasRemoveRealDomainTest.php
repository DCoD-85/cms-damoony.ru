<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli\Commands;

use Damoony\Cli\Commands\AlliasRemoveCommand;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Files\Paths;
use PHPUnit\Framework\TestCase;

final class AlliasRemoveRealDomainTest extends TestCase
{
    private string $root;
    private Paths $paths;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_alrm_' . bin2hex(random_bytes(4));
        mkdir($this->root . '/config', 0o777, true);
        $this->paths = new Paths($this->root);

        mkdir($this->paths->allias('real.ru', 'doc') . '/algoritm', 0o777, true);
        mkdir($this->paths->media('real.ru', 'doc'), 0o777, true);
        mkdir($this->paths->allias('real.ru', 'www') . '/algoritm', 0o777, true);

        file_put_contents($this->root . '/config/site.config', json_encode([
            'domains_real' => ['real.ru' => 'real_ru'],
            'domains_mirrors' => ['real.ru' => 'real.ru', 'mirror.ru' => 'real.ru'],
            'alliases' => ['real.ru' => [
                'www' => ['available' => true],
                'doc' => ['available' => true],
            ]],
            'fillers' => ['real.ru' => ['doc' => ['top' => true]]],
        ]));
    }

    protected function tearDown(): void
    {
        $this->rmrf($this->root);
    }

    public function testMirrorRemovalCleansConfigUnderRealHost(): void
    {
        $res = $this->execute(['domain' => 'mirror.ru', 'name' => 'doc', 'force' => '1']);

        self::assertTrue($res->ok, implode('; ', $res->errors));

        $site = $this->site();
        self::assertArrayNotHasKey('doc', $site['alliases']['real.ru'], 'запись алиаса должна быть удалена');
        self::assertArrayNotHasKey('doc', $site['fillers']['real.ru'], 'fillers должны быть очищены');
        self::assertArrayHasKey('www', $site['alliases']['real.ru'], 'соседний алиас не задет');
        self::assertArrayNotHasKey('mirror.ru', $site['alliases'], 'ключ зеркала не создан');
    }

    public function testMirrorRemovalQuarantinesRealFolder(): void
    {
        $res = $this->execute(['domain' => 'mirror.ru', 'name' => 'doc', 'force' => '1']);

        self::assertTrue($res->ok, implode('; ', $res->errors));
        self::assertDirectoryDoesNotExist($this->paths->allias('real.ru', 'doc'));
        self::assertNotSame([], glob($this->paths->allias('real.ru', 'doc') . '_trash_*') ?: []);
        self::assertDirectoryDoesNotExist($this->root . '/app/mirror.ru');
        self::assertDirectoryExists($this->paths->allias('real.ru', 'www'));
        self::assertDirectoryDoesNotExist($this->paths->media('real.ru', 'doc'));
        self::assertNotSame([], glob($this->paths->mediaRoot() . '/.trash/real.ru_doc_trash_*') ?: []);
    }

    public function testSelfMappedRemovalStillWorks(): void
    {
        $res = $this->execute(['domain' => 'real.ru', 'name' => 'doc', 'force' => '1']);

        self::assertTrue($res->ok, implode('; ', $res->errors));
        $site = $this->site();
        self::assertArrayNotHasKey('doc', $site['alliases']['real.ru']);
        self::assertDirectoryDoesNotExist($this->paths->allias('real.ru', 'doc'));
    }

    public function testWithoutForceDoesNothing(): void
    {
        $res = $this->execute(['domain' => 'mirror.ru', 'name' => 'doc']);

        self::assertFalse($res->ok);
        self::assertStringContainsString('--force', $res->errors[0]);
        self::assertArrayHasKey('doc', $this->site()['alliases']['real.ru']);
        self::assertDirectoryExists($this->paths->allias('real.ru', 'doc'));
    }

    private function execute(array $args): Result
    {
        return new AlliasRemoveCommand($this->paths)->run(
            $args,
            new Context(static fn (): null => null),
        );
    }

    /** @return array<string, mixed> */
    private function site(): array
    {
        return json_decode((string) file_get_contents($this->root . '/config/site.config'), true);
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
