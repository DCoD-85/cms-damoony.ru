<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli\Commands;

use Damoony\Cli\Commands\ContentListCommand;
use Damoony\Cli\Commands\ContentRemoveCommand;
use Damoony\Cli\Commands\ContentStoreCommand;
use Damoony\Cli\Context;
use Damoony\Core\Files\Paths;
use PHPUnit\Framework\TestCase;

final class ContentCommandsRealDomainTest extends TestCase
{
    private string $root;
    private Paths $paths;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_content_' . bin2hex(random_bytes(6));
        mkdir($this->root . '/config', 0o775, true);
        $this->paths = new Paths($this->root);

        file_put_contents(
            $this->root . '/config/site.config',
            json_encode([
                'domains_real' => ['real.ru' => 'real.ru', 'alias.ru' => 'real.ru'],
            ], JSON_THROW_ON_ERROR),
        );
        file_put_contents(
            $this->root . '/config/agent.config',
            json_encode(['allowed_domains' => ['alias.ru']], JSON_THROW_ON_ERROR),
        );
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    public function testContentListReadsRealDir(): void
    {
        $this->seedContent('real.ru');

        $captured = '';
        $ctx = new Context(static function (string $m, bool $ok = true, string $d = '') use (&$captured): void {
            $captured .= $m . "\n";
        });

        new ContentListCommand($this->paths)->run(
            ['domain' => 'alias.ru', 'allias' => 'www', 'category' => 'general'],
            $ctx,
        );

        $this->assertStringContainsString('real.ru', $captured, 'должен сканировать content/real.ru/');
        $this->assertStringNotContainsString('alias.ru', $captured);
    }

    public function testContentRemoveMovesFromRealDirToTrash(): void
    {
        $this->seedContent('real.ru');
        $src = $this->root . '/content/real.ru/www/general/abcdef0123456789.png';
        $this->assertFileExists($src);

        new ContentRemoveCommand($this->paths)->run(
            [
                'domain' => 'alias.ru',
                'allias' => 'www',
                'category' => 'general',
                'hash' => 'abcdef0123456789',
                'force' => true,
            ],
            $this->ctx(),
        );

        $this->assertFileDoesNotExist($src, 'файл в real.ru должен быть перемещён');
        $trash = glob($this->root . '/content/.trash/real.ru_www_general_abcdef0123456789_trash_*') ?: [];
        $this->assertNotEmpty($trash, 'trash должен содержать real.ru_www_..., не alias.ru_www_...');
        $this->assertDirectoryDoesNotExist($this->root . '/content/alias.ru');
    }

    public function testContentStoreUnknownDomainRejected(): void
    {
        new ContentStoreCommand($this->paths)->run(
            [
                'domain' => 'unknown.ru',
                'allias' => 'www',
                'category' => 'general',
                'mime' => 'image/png',
                'data_b64' => base64_encode('any'),
            ],
            $this->ctx(),
        );

        $this->assertDirectoryDoesNotExist($this->root . '/content/unknown.ru');
    }

    public function testContentStoreWritesToRealDir(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension is not available');
        }

        $im = imagecreatetruecolor(200, 200);
        $this->assertNotFalse($im);
        ob_start();
        imagepng($im);
        $bytes = (string) ob_get_clean();
        // imagedestroy() deprecated в PHP 8.5 (GD-объекты авто-освобождаются)
        $hash = substr(hash('sha256', $bytes), 0, 16);

        new ContentStoreCommand($this->paths)->run(
            [
                'domain' => 'alias.ru',
                'allias' => 'www',
                'category' => 'general',
                'mime' => 'image/png',
                'data_b64' => base64_encode($bytes),
            ],
            $this->ctx(),
        );

        $this->assertFileExists(
            $this->root . '/content/real.ru/www/general/' . $hash . '.png',
            'файл должен попасть в content/real.ru/www/general/',
        );
        $this->assertDirectoryDoesNotExist(
            $this->root . '/content/alias.ru',
            'content/alias.ru/ не должно появляться',
        );
    }

    private function seedContent(string $realDomain): void
    {
        $dir = $this->root . '/content/' . $realDomain . '/www/general';
        mkdir($dir, 0o775, true);
        file_put_contents($dir . '/abcdef0123456789.png', 'png');
        file_put_contents(
            $dir . '/abcdef0123456789.meta.json',
            json_encode(['mime' => 'image/png', 'page_refs' => []], JSON_THROW_ON_ERROR),
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
