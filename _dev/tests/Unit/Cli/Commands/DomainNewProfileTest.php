<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli\Commands;

use Damoony\Cli\Commands\DomainNewCommand;
use Damoony\Cli\Context;
use Damoony\Core\Files\Paths;
use PHPUnit\Framework\TestCase;

final class DomainNewProfileTest extends TestCase
{
    private string $root;
    private Paths $paths;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_dnp_' . bin2hex(random_bytes(6));
        mkdir($this->root . '/config', 0o775, true);
        mkdir($this->root . '/www/media', 0o775, true);
        $this->paths = new Paths($this->root);

        file_put_contents(
            $this->root . '/config/site.config',
            json_encode([], JSON_THROW_ON_ERROR),
        );
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    public function testProfileCleanCreatesMinimalStructure(): void
    {
        new DomainNewCommand($this->paths)->run(
            ['domain' => 'test.ru', 'profile' => 'clean'],
            $this->ctx(),
        );

        // Root-страница создана
        $this->assertFileExists($this->root . '/app/test.ru/www/algoritm/algoritm.php');
        $this->assertFileExists($this->root . '/app/test.ru/www/algoritm/algoritm.rules.json');

        // Схема/блок/скин НЕ созданы
        $this->assertDirectoryDoesNotExist($this->root . '/app/test.ru/.schemesglobal');
        $this->assertDirectoryDoesNotExist($this->root . '/app/test.ru/.blocksglobal');
        $this->assertDirectoryDoesNotExist($this->root . '/www/media/test.ru');
    }

    public function testProfileMinimalCreatesSchemeAndBlock(): void
    {
        new DomainNewCommand($this->paths)->run(
            ['domain' => 'test.ru', 'profile' => 'minimal'],
            $this->ctx(),
        );

        $this->assertFileExists($this->root . '/app/test.ru/www/algoritm/algoritm.php');
        $this->assertFileExists($this->root . '/app/test.ru/.schemesglobal/www.php');
        $this->assertFileExists($this->root . '/app/test.ru/.blocksglobal/center/center.php');
        $this->assertDirectoryExists($this->root . '/www/media/test.ru/www/skin/default');
    }

    public function testDefaultProfileIsMinimal(): void
    {
        new DomainNewCommand($this->paths)->run(
            ['domain' => 'test.ru'],
            $this->ctx(),
        );

        $this->assertFileExists($this->root . '/app/test.ru/.schemesglobal/www.php');
        $this->assertFileExists($this->root . '/app/test.ru/.blocksglobal/center/center.php');
    }

    public function testProfileFullRejectedWithMessage(): void
    {
        $res = new DomainNewCommand($this->paths)->run(
            ['domain' => 'test.ru', 'profile' => 'full'],
            $this->ctx(),
        );

        $this->assertFalse($res->ok);
        $this->assertStringContainsString('full в разработке', $res->errors[0] ?? '');
        $this->assertDirectoryDoesNotExist($this->root . '/app/test.ru');
    }

    public function testInvalidProfileRejected(): void
    {
        $res = new DomainNewCommand($this->paths)->run(
            ['domain' => 'test.ru', 'profile' => 'bogus'],
            $this->ctx(),
        );

        $this->assertFalse($res->ok);
        $this->assertStringContainsString('Некорректный профиль', $res->errors[0] ?? '');
    }

    public function testNewDomainGetsV4Structure(): void
    {
        new DomainNewCommand($this->paths)->run(
            ['domain' => 'test.ru', 'profile' => 'minimal'],
            $this->ctx(),
        );

        $site = json_decode((string) file_get_contents($this->root . '/config/site.config'), true);
        $this->assertSame('v4', $site['domains_structure']['test.ru'] ?? null);
    }

    public function testExistingDomainRejected(): void
    {
        file_put_contents(
            $this->root . '/config/site.config',
            json_encode(['domains_real' => ['existing.ru' => 'existing.ru']], JSON_THROW_ON_ERROR),
        );

        $res = new DomainNewCommand($this->paths)->run(
            ['domain' => 'existing.ru', 'profile' => 'minimal'],
            $this->ctx(),
        );

        $this->assertFalse($res->ok);
        $this->assertStringContainsString('Домен уже существует', $res->errors[0] ?? '');
    }

    public function testRootRulesHaveEmptyPathMapping(): void
    {
        new DomainNewCommand($this->paths)->run(
            ['domain' => 'test.ru', 'profile' => 'minimal'],
            $this->ctx(),
        );

        $rulesFile = $this->root . '/app/test.ru/www/algoritm/algoritm.rules.json';
        $this->assertFileExists($rulesFile);

        $rules = json_decode((string) file_get_contents($rulesFile), true);
        $this->assertSame(
            '^$',
            $rules['available']['']
                ?? null,
            'root rules MUST contain available[""] = "^$" (FileRouter matching)',
        );
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
