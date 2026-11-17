<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Core\Settings;

use Damoony\Core\Settings\SiteConfig;
use Damoony\Core\Settings\SiteConfigWriter;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SiteConfigWriterTest extends TestCase
{
    private string $root;
    private SiteConfigWriter $writer;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_site_writer_' . bin2hex(random_bytes(6));
        mkdir($this->root . '/config', 0o775, true);
        $this->writer = SiteConfigWriter::forRoot($this->root);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    public function testCreatesFileFromScratch(): void
    {
        self::assertFalse(is_file($this->writer->path()));

        self::assertTrue($this->writer->ensureProject('damoony.ru', 'damoony.ru', 'damoony_ru'));
        self::assertTrue(is_file($this->writer->path()));

        $site = SiteConfig::fromFile($this->writer->path());
        self::assertSame(['damoony.ru'], $site->projectKeys());
        self::assertSame('damoony_ru', $site->namespace('damoony.ru'));
    }

    public function testEnsureProjectIsIdempotent(): void
    {
        $this->writer->ensureProject('damoony.ru', 'damoony.ru', 'damoony_ru');

        self::assertFalse($this->writer->ensureProject('damoony.ru', 'damoony.ru', 'damoony_ru'));
    }

    public function testEnsureProjectRejectsDifferentFolder(): void
    {
        $this->writer->ensureProject('damoony.ru', 'damoony.ru', 'damoony_ru');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('другими folder/namespace');
        $this->writer->ensureProject('damoony.ru', 'other', 'damoony_ru');
    }

    public function testEnsureMirrorNormalizesHost(): void
    {
        $this->writer->ensureProject('damoony.ru', 'damoony.ru', 'damoony_ru');

        self::assertTrue($this->writer->ensureMirror('damoony.ru', 'Damoony3.RU'));
        self::assertFalse($this->writer->ensureMirror('damoony.ru', 'damoony3.ru'));

        $site = SiteConfig::fromFile($this->writer->path());
        self::assertSame('damoony.ru', $site->folderByHost('damoony3.ru'));
    }

    public function testEnsureMirrorForUnknownProjectThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('не найден в domains_app');
        $this->writer->ensureMirror('nope.ru', 'nope.ru');
    }

    public function testSetMirrorAvailable(): void
    {
        $this->writer->ensureProject('damoony.ru', 'damoony.ru', 'damoony_ru');
        $this->writer->ensureMirror('damoony.ru', 'damoony3.ru');
        $this->writer->setMirrorAvailable('damoony.ru', 'damoony3.ru', false);

        $site = SiteConfig::fromFile($this->writer->path());
        self::assertFalse($site->isHostAvailable('damoony3.ru'));
    }

    public function testEnsureAlias(): void
    {
        $this->writer->ensureProject('damoony.ru', 'damoony.ru', 'damoony_ru');

        self::assertTrue($this->writer->ensureAlias('damoony.ru', 'www', ['ru']));
        self::assertFalse($this->writer->ensureAlias('damoony.ru', 'www', ['ru', 'en']));

        $site = SiteConfig::fromFile($this->writer->path());
        self::assertSame(['ru', 'en'], $site->requireProject('damoony.ru')->aliasLangs('www'));
    }

    public function testEnsureAliasForUnknownProjectThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('не найден в domains_app');
        $this->writer->ensureAlias('nope.ru', 'www');
    }

    public function testSetAliasLangsAndAvailability(): void
    {
        $this->writer->ensureProject('damoony.ru', 'damoony.ru', 'damoony_ru');
        $this->writer->ensureAlias('damoony.ru', 'docs', ['ru']);
        $this->writer->setAliasLangs('damoony.ru', 'docs', ['ru', 'en', 'de']);
        $this->writer->setAliasAvailable('damoony.ru', 'docs', false);

        $site = SiteConfig::fromFile($this->writer->path());
        $project = $site->requireProject('damoony.ru');

        self::assertSame(['ru', 'en', 'de'], $project->aliasLangs('docs'));
        self::assertFalse($project->isAliasAvailable('docs'));
    }

    public function testRemoveAliasAndProject(): void
    {
        $this->writer->ensureProject('damoony.ru', 'damoony.ru', 'damoony_ru');
        $this->writer->ensureAlias('damoony.ru', 'www', ['ru']);

        self::assertTrue($this->writer->removeAlias('damoony.ru', 'www'));
        self::assertFalse($this->writer->removeAlias('damoony.ru', 'www'));

        self::assertTrue($this->writer->removeProject('damoony.ru'));
        self::assertFalse($this->writer->removeProject('damoony.ru'));

        $raw = $this->writer->load();
        self::assertSame([], $raw['domains_app']);
        self::assertArrayNotHasKey('alliases', $raw);
    }

    public function testSaveRejectsInvalidData(): void
    {
        $this->writer->ensureProject('damoony.ru', 'damoony.ru', 'damoony_ru');
        $before = file_get_contents($this->writer->path());

        $data = $this->writer->load();
        $data['domains_app']['damoony.ru']['folder'] = '../etc';

        try {
            $this->writer->save($data);
            self::fail('Ожидалось исключение валидации');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('folder недопустим', $e->getMessage());
        }

        self::assertSame($before, file_get_contents($this->writer->path()));
    }

    public function testUnknownKeysArePreserved(): void
    {
        $data = [
            'domains_app' => [
                'damoony.ru' => [
                    'folder' => 'damoony.ru',
                    'namespace' => 'damoony_ru',
                    'mirrors' => ['damoony.ru' => ['available' => true]],
                    'scheme' => 'www',
                ],
            ],
            '_readme' => 'служебный ключ',
        ];
        $this->writer->save($data);

        $this->writer->ensureAlias('damoony.ru', 'www', ['ru']);

        $raw = $this->writer->load();
        self::assertSame('служебный ключ', $raw['_readme']);
        self::assertSame('www', $raw['domains_app']['damoony.ru']['scheme']);
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
