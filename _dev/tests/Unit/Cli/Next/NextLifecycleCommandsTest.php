<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli\Next;

use Damoony\Cli\Context;
use Damoony\Cli\Next\Commands\AlliasRemoveCommand;
use Damoony\Cli\Next\Commands\DomainBindCommand;
use Damoony\Cli\Next\Commands\DomainRemoveCommand;
use Damoony\Cli\Next\Commands\DomainUnbindCommand;
use Damoony\Cli\Next\Registry;
use Damoony\Cli\Next\Support\Quarantine;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Core\Settings\SiteConfigWriter;
use PHPUnit\Framework\TestCase;

final class NextLifecycleCommandsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_cli_lifecycle_' . bin2hex(random_bytes(6));
        mkdir($this->root . '/config', 0o775, true);
        mkdir($this->root . '/app/damoony.ru/www', 0o775, true);
        mkdir($this->root . '/app/damoony.ru/blog', 0o775, true);
        mkdir($this->root . '/www/media/damoony.ru/www', 0o775, true);
        mkdir($this->root . '/www/media/damoony.ru/blog', 0o775, true);

        $writer = SiteConfigWriter::forRoot($this->root);
        $writer->ensureProject('damoony.ru', 'damoony.ru', 'damoony_ru');
        $writer->ensureMirror('damoony.ru', 'damoony.ru');
        $writer->ensureMirror('damoony.ru', 'damoony3.ru');
        $writer->ensureAlias('damoony.ru', 'www');
        $writer->ensureAlias('damoony.ru', 'blog');
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    public function testQuarantineInPlaceRenames(): void
    {
        $dir = $this->root . '/app/damoony.ru';
        $quarantine = new Quarantine();

        $trash = $quarantine->inPlace($dir);

        self::assertNotNull($trash);
        self::assertDirectoryDoesNotExist($dir);
        self::assertDirectoryExists($trash);
        self::assertStringContainsString('_trash_', $trash);
    }

    public function testQuarantineInPlaceIsUniqueOnCollision(): void
    {
        $dir = $this->root . '/app/damoony.ru';
        $quarantine = new Quarantine();

        $first = $quarantine->inPlace($dir);
        mkdir($dir, 0o775, true);
        $second = $quarantine->inPlace($dir);

        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertNotSame($first, $second);
    }

    public function testQuarantineInPlaceReturnsNullForMissingDir(): void
    {
        self::assertNull(new Quarantine()->inPlace($this->root . '/app/nope.ru'));
    }

    public function testQuarantineToTrashDirMovesIntoCommonTrash(): void
    {
        $trash = new Quarantine()->toTrashDir(
            $this->root . '/www/media/damoony.ru',
            $this->root . '/www/media/.trash',
            'damoony.ru',
        );

        self::assertNotNull($trash);
        self::assertStringStartsWith($this->root . '/www/media/.trash/damoony.ru_trash_', $trash);
        self::assertDirectoryDoesNotExist($this->root . '/www/media/damoony.ru');
    }

    public function testBindAddsMirror(): void
    {
        $result = $this->bind()->run(['domain' => 'extra.ru', 'real' => 'damoony.ru'], $this->context());

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertSame('damoony.ru', $this->site()->folderByHost('extra.ru'));
    }

    public function testBindIsIdempotent(): void
    {
        $result = $this->bind()->run(['domain' => 'damoony3.ru', 'real' => 'damoony.ru'], $this->context());

        self::assertTrue($result->ok);
        self::assertSame([], $result->errors);
    }

    public function testBindRejectsHostOwnedByAnotherProject(): void
    {
        $this->writer()->ensureProject('other.ru', 'other.ru', 'other_ru');
        $this->writer()->ensureMirror('other.ru', 'other.ru');
        $this->writer()->ensureAlias('other.ru', 'www');

        $result = $this->bind()->run(['domain' => 'other.ru', 'real' => 'damoony.ru'], $this->context());

        self::assertFalse($result->ok);
        self::assertStringContainsString('уже принадлежит проекту other.ru', $result->errors[0]);
    }

    public function testBindRejectsSameHostAndProject(): void
    {
        $result = $this->bind()->run(['domain' => 'damoony.ru', 'real' => 'damoony.ru'], $this->context());

        self::assertFalse($result->ok);
        self::assertStringContainsString('совпадают', $result->errors[0]);
    }

    public function testUnbindRemovesMirror(): void
    {
        $result = $this->unbind()->run(['domain' => 'damoony3.ru'], $this->context());

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertNull($this->site()->folderByHost('damoony3.ru'));
        self::assertSame('damoony.ru', $this->site()->folderByHost('damoony.ru'));
    }

    public function testUnbindRejectsPrimaryHost(): void
    {
        $result = $this->unbind()->run(['domain' => 'damoony.ru'], $this->context());

        self::assertFalse($result->ok);
        self::assertStringContainsString('основной хост', $result->errors[0]);
    }

    public function testUnbindUnknownHostIsOk(): void
    {
        $result = $this->unbind()->run(['domain' => 'nope.ru'], $this->context());

        self::assertTrue($result->ok);
        self::assertSame([], $result->steps);
        self::assertSame([], $result->data);
    }

    public function testDomainRemoveRequiresForce(): void
    {
        $result = $this->domainRemove()->run(['domain' => 'damoony.ru'], $this->context());

        self::assertFalse($result->ok);
        self::assertStringContainsString('--force', $result->errors[0]);
    }

    public function testDomainRemoveRequiresCascadeWithMirrors(): void
    {
        $result = $this->domainRemove()->run(['domain' => 'damoony.ru', 'force' => '1'], $this->context());

        self::assertFalse($result->ok);
        self::assertStringContainsString('--cascade', $result->errors[0]);
    }

    public function testDomainRemoveRejectsMirrorInput(): void
    {
        $result = $this->domainRemove()->run(['domain' => 'damoony3.ru', 'force' => '1', 'cascade' => '1'], $this->context());

        self::assertFalse($result->ok);
        self::assertStringContainsString('Укажите --domain=damoony.ru', $result->errors[0]);
    }

    public function testDomainRemoveDryRunKeepsEverything(): void
    {
        $result = $this->domainRemove()->run(
            ['domain' => 'damoony.ru', 'force' => '1', 'cascade' => '1'],
            $this->dryRunContext(),
        );

        self::assertTrue($result->ok);
        self::assertTrue($result->data['dry_run']);
        self::assertDirectoryExists($this->root . '/app/damoony.ru');
        self::assertTrue($this->site()->has('damoony.ru'));
    }

    public function testDomainRemoveQuarantinesAndCleansConfig(): void
    {
        $result = $this->domainRemove()->run(
            ['domain' => 'damoony.ru', 'force' => '1', 'cascade' => '1'],
            $this->context(),
        );

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertDirectoryDoesNotExist($this->root . '/app/damoony.ru');
        self::assertDirectoryDoesNotExist($this->root . '/www/media/damoony.ru');

        $trashApp = glob($this->root . '/app/damoony.ru_trash_*') ?: [];
        $trashMedia = glob($this->root . '/www/media/.trash/damoony.ru_trash_*') ?: [];
        self::assertNotEmpty($trashApp);
        self::assertNotEmpty($trashMedia);

        self::assertFalse($this->site()->has('damoony.ru'));
        self::assertSame([], $this->site()->hostMap());
    }

    public function testAlliasRemoveRequiresForce(): void
    {
        $result = $this->alliasRemove()->run(['domain' => 'damoony.ru', 'name' => 'blog'], $this->context());

        self::assertFalse($result->ok);
        self::assertStringContainsString('--force', $result->errors[0]);
    }

    public function testAlliasRemoveQuarantinesAndCleansConfig(): void
    {
        $result = $this->alliasRemove()->run(
            ['domain' => 'damoony.ru', 'name' => 'blog', 'force' => '1'],
            $this->context(),
        );

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertDirectoryDoesNotExist($this->root . '/app/damoony.ru/blog');
        self::assertDirectoryDoesNotExist($this->root . '/www/media/damoony.ru/blog');
        self::assertFalse($this->site()->requireProject('damoony.ru')->hasAlias('blog'));
        self::assertTrue($this->site()->requireProject('damoony.ru')->hasAlias('www'));
    }

    public function testAlliasRemoveUnknownIsHarmless(): void
    {
        $result = $this->alliasRemove()->run(
            ['domain' => 'damoony.ru', 'name' => 'ghost', 'force' => '1'],
            $this->context(),
        );

        self::assertTrue($result->ok);
        self::assertCount(1, $result->warnings);
    }

    private function bind(): DomainBindCommand
    {
        return new DomainBindCommand($this->site(), $this->writer());
    }

    private function unbind(): DomainUnbindCommand
    {
        return new DomainUnbindCommand($this->site(), $this->writer());
    }

    private function domainRemove(): DomainRemoveCommand
    {
        return new DomainRemoveCommand(
            new Paths($this->root),
            $this->site(),
            $this->writer(),
            new Quarantine(),
        );
    }

    private function alliasRemove(): AlliasRemoveCommand
    {
        return new AlliasRemoveCommand(
            new Paths($this->root),
            $this->site(),
            $this->writer(),
            new Quarantine(),
        );
    }

    private function site(): SiteConfig
    {
        return SiteConfig::fromFile(Registry::configPath($this->root));
    }

    private function writer(): SiteConfigWriter
    {
        return new SiteConfigWriter(Registry::configPath($this->root));
    }

    private function context(): Context
    {
        return new Context(static function (string $m, bool $ok = true, string $d = ''): void {
        });
    }

    private function dryRunContext(): Context
    {
        return new Context(static function (string $m, bool $ok = true, string $d = ''): void {
        }, false, true);
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
