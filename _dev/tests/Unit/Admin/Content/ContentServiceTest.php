<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Admin\Content;

use Damoony\Admin\Content\ContentService;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\AliasSettings;
use Damoony\Core\Settings\ProjectSettings;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ContentServiceTest extends TestCase
{
    private string $root;
    private ProjectSettings $project;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony-content-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/app/example.ru/www/algoritm/lang/about', 0o775, true);
        file_put_contents($this->root . '/app/example.ru/www/algoritm/lang/about/algoritm_lang_about.editor.json', "[]\n");

        $this->project = new ProjectSettings(
            'example.ru',
            'example.ru',
            'example_ru',
            'www',
            'maintenance',
            'www',
            ['ru'],
            [],
            ['example.ru' => true],
            ['www' => new AliasSettings('www', true, ['ru'])],
        );
    }

    protected function tearDown(): void
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($it as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->root);
    }

    public function testRejectsTraversalPath(): void
    {
        $service = new ContentService(new Paths($this->root));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Некорректный путь страницы');
        $service->read($this->project, 'www', '../config');
    }

    public function testAcceptsNestedPagePath(): void
    {
        $service = new ContentService(new Paths($this->root));

        self::assertSame([], $service->read($this->project, 'www', 'lang/about'));
    }
}
