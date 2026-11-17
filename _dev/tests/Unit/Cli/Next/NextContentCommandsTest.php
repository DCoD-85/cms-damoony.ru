<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli\Next;

use Damoony\Cli\Context;
use Damoony\Cli\Next\Commands\ContentGcCommand;
use Damoony\Cli\Next\Commands\ContentListCommand;
use Damoony\Cli\Next\Commands\ContentRemoveCommand;
use Damoony\Cli\Next\Commands\ContentStoreCommand;
use Damoony\Cli\Next\Support\ContentLayout;
use Damoony\Cli\Next\Support\TargetResolver;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Core\Settings\SiteConfigWriter;
use PHPUnit\Framework\TestCase;

final class NextContentCommandsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_cli_content_' . bin2hex(random_bytes(6));
        mkdir($this->root . '/config', 0o775, true);

        $writer = SiteConfigWriter::forRoot($this->root);
        $writer->ensureProject('damoony.ru', 'damoony.ru', 'damoony_ru');
        $writer->ensureMirror('damoony.ru', 'damoony.ru');
        $writer->ensureMirror('damoony.ru', 'damoony3.ru');
        $writer->ensureAlias('damoony.ru', 'www');

        file_put_contents(
            $this->root . '/config/agent.config',
            json_encode(['allowed_domains' => ['damoony.ru']]),
        );

        mkdir($this->root . '/app/damoony.ru/www/algoritm', 0o775, true);
        file_put_contents($this->root . '/app/damoony.ru/www/algoritm/algoritm.php', "<?php\n");
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    public function testStoreWritesFileAndMeta(): void
    {
        $bytes = $this->png();

        $result = $this->store()->run(
            [
                'domain' => 'damoony.ru',
                'allias' => 'www',
                'category' => 'hero',
                'data_b64' => base64_encode($bytes),
                'mime' => 'image/png',
                'alt' => 'Герой',
            ],
            $this->context(),
        );

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertSame(200, $result->data['width']);
        self::assertSame(200, $result->data['height']);
        self::assertStringStartsWith('/content/damoony.ru/www/hero/', $result->data['url']);

        $hash = $result->data['hash'];
        $file = $this->root . '/content/damoony.ru/www/hero/' . $hash . '.png';
        $metaFile = $this->root . '/content/damoony.ru/www/hero/' . $hash . '.meta.json';

        self::assertFileExists($file);
        self::assertFileExists($metaFile);

        $meta = json_decode((string) file_get_contents($metaFile), true);
        self::assertIsArray($meta);
        self::assertSame('image/png', $meta['mime']);
        self::assertSame('Герой', $meta['alt']);
        self::assertSame([], $meta['page_refs']);
    }

    public function testStoreDeduplicates(): void
    {
        $bytes = base64_encode($this->png());

        $first = $this->store()->run(
            ['domain' => 'damoony.ru', 'allias' => 'www', 'data_b64' => $bytes, 'mime' => 'image/png'],
            $this->context(),
        );
        $second = $this->store()->run(
            ['domain' => 'damoony3.ru', 'allias' => 'www', 'data_b64' => $bytes, 'mime' => 'image/png'],
            $this->context(),
        );

        self::assertTrue($first->ok, implode('; ', $first->errors));
        self::assertTrue($second->ok, implode('; ', $second->errors));
        self::assertTrue($second->data['deduplicated']);
        self::assertSame($first->data['hash'], $second->data['hash']);
        self::assertCount(1, $second->warnings);
    }

    public function testStoreRejectsBadMime(): void
    {
        $result = $this->store()->run(
            ['domain' => 'damoony.ru', 'allias' => 'www', 'data_b64' => base64_encode('x'), 'mime' => 'text/html'],
            $this->context(),
        );

        self::assertFalse($result->ok);
        self::assertStringContainsString('Недопустимый MIME', $result->errors[0]);
    }

    public function testStoreRejectsSmallImage(): void
    {
        $small = $this->png(50, 50);

        $result = $this->store()->run(
            ['domain' => 'damoony.ru', 'allias' => 'www', 'data_b64' => base64_encode($small), 'mime' => 'image/png'],
            $this->context(),
        );

        self::assertFalse($result->ok);
        self::assertStringContainsString('слишком маленькое', $result->errors[0]);
    }

    public function testStoreRejectsDisallowedDomain(): void
    {
        $result = $this->store()->run(
            ['domain' => 'other.ru', 'allias' => 'www', 'data_b64' => base64_encode($this->png()), 'mime' => 'image/png'],
            $this->context(),
        );

        self::assertFalse($result->ok);
        self::assertStringContainsString('не найден в domains_app', $result->errors[0]);
    }

    public function testStoreAllowsSvgWithoutDimensions(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"></svg>';

        $result = $this->store()->run(
            ['domain' => 'damoony.ru', 'allias' => 'www', 'data_b64' => base64_encode($svg), 'mime' => 'image/svg+xml'],
            $this->context(),
        );

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertSame(0, $result->data['width']);
        self::assertStringEndsWith('.svg', (string) $result->data['url']);
    }

    public function testRemoveRequiresForce(): void
    {
        $result = $this->remove()->run(
            ['domain' => 'damoony.ru', 'allias' => 'www', 'category' => 'hero', 'hash' => 'aaaaaaaaaaaaaaaa'],
            $this->context(),
        );

        self::assertFalse($result->ok);
        self::assertStringContainsString('--force', $result->errors[0]);
    }

    public function testRemoveQuarantinesContent(): void
    {
        $stored = $this->store()->run(
            ['domain' => 'damoony.ru', 'allias' => 'www', 'data_b64' => base64_encode($this->png()), 'mime' => 'image/png'],
            $this->context(),
        );
        self::assertTrue($stored->ok, implode('; ', $stored->errors));

        $result = $this->remove()->run(
            [
                'domain' => 'damoony.ru',
                'allias' => 'www',
                'category' => 'general',
                'hash' => $stored->data['hash'],
                'force' => '1',
            ],
            $this->context(),
        );

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertFileDoesNotExist($this->root . '/content/damoony.ru/www/general/' . $stored->data['hash'] . '.png');
        self::assertNotEmpty(glob($this->root . '/content/.trash/damoony.ru_www_general_*') ?: []);
    }

    public function testListShowsContent(): void
    {
        $stored = $this->store()->run(
            ['domain' => 'damoony.ru', 'allias' => 'www', 'data_b64' => base64_encode($this->png()), 'mime' => 'image/png'],
            $this->context(),
        );
        self::assertTrue($stored->ok, implode('; ', $stored->errors));

        $result = $this->listContent()->run(['domain' => 'damoony.ru', 'allias' => 'www'], $this->context());

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertSame(1, $result->data['count']);
        self::assertSame($stored->data['hash'], $result->data['rows'][0]['hash']);
        self::assertSame([], $result->data['rows'][0]['page_refs']);
    }

    public function testGcRequiresMode(): void
    {
        $result = $this->gc()->run([], $this->context());

        self::assertFalse($result->ok);
        self::assertStringContainsString('--force', $result->errors[0]);
    }

    public function testGcDryReportsOrphans(): void
    {
        $stored = $this->store()->run(
            ['domain' => 'damoony.ru', 'allias' => 'www', 'data_b64' => base64_encode($this->png()), 'mime' => 'image/png'],
            $this->context(),
        );
        self::assertTrue($stored->ok, implode('; ', $stored->errors));

        $result = $this->gc()->run(['dry' => '1'], $this->context());

        self::assertTrue($result->ok);
        self::assertFileExists($this->root . '/content/damoony.ru/www/general/' . $stored->data['hash'] . '.png');
    }

    public function testGcKeepsReferencedContent(): void
    {
        $stored = $this->store()->run(
            ['domain' => 'damoony.ru', 'allias' => 'www', 'data_b64' => base64_encode($this->png()), 'mime' => 'image/png'],
            $this->context(),
        );
        self::assertTrue($stored->ok, implode('; ', $stored->errors));

        $metaFile = $this->root . '/content/damoony.ru/www/general/' . $stored->data['hash'] . '.meta.json';
        $meta = json_decode((string) file_get_contents($metaFile), true);
        $meta['page_refs'] = ['__root__'];
        file_put_contents($metaFile, json_encode($meta));

        $result = $this->gc()->run(['force' => '1'], $this->context());

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertFileExists($this->root . '/content/damoony.ru/www/general/' . $stored->data['hash'] . '.png');
    }

    private function png(int $width = 200, int $height = 200): string
    {
        $image = imagecreatetruecolor($width, $height);
        self::assertNotFalse($image);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 10, 20, 30));

        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();

        return $bytes;
    }

    private function site(): SiteConfig
    {
        return SiteConfig::fromFile($this->root . '/config/site.config');
    }

    private function resolver(): TargetResolver
    {
        return new TargetResolver($this->site());
    }

    private function layout(): ContentLayout
    {
        return new ContentLayout(new Paths($this->root));
    }

    private function store(): ContentStoreCommand
    {
        return new ContentStoreCommand(new Paths($this->root), $this->resolver(), $this->layout());
    }

    private function remove(): ContentRemoveCommand
    {
        return new ContentRemoveCommand(new Paths($this->root), $this->resolver(), $this->layout());
    }

    private function listContent(): ContentListCommand
    {
        return new ContentListCommand(new Paths($this->root), $this->resolver(), $this->layout());
    }

    private function gc(): ContentGcCommand
    {
        return new ContentGcCommand(new Paths($this->root), $this->layout());
    }

    private function context(): Context
    {
        return new Context(static function (string $m, bool $ok = true, string $d = ''): void {
        });
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
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
