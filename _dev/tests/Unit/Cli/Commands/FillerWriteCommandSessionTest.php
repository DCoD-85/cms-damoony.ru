<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli\Commands;

use Damoony\Cli\Commands\FillerWriteCommand;
use Damoony\Cli\Context;
use Damoony\Core\Files\Paths;
use PHPUnit\Framework\TestCase;

final class FillerWriteCommandSessionTest extends TestCase
{
    private string $root;
    private Paths $paths;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_fillerwrite_' . bin2hex(random_bytes(6));
        mkdir($this->root . '/config', 0o775, true);
        $this->paths = new Paths($this->root);

        file_put_contents(
            $this->root . '/config/site.config',
            json_encode([
                'domains_real' => ['example.ru' => 'example.ru'],
                'domains_namespace' => ['example.ru' => 'example_ru'],
            ], JSON_THROW_ON_ERROR),
        );
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    public function testWithoutSessionWritesToProduction(): void
    {
        $target = $this->seedFiller($this->root . '/app/example.ru', 'header', 'www', '<?php // prod stub');
        $before = (string) file_get_contents($target);

        new FillerWriteCommand($this->paths)->run(
            [
                'domain' => 'example.ru',
                'allias' => 'www',
                'block' => 'header',
                'json' => '[{"type":"text","content":"x"}]',
            ],
            $this->ctx(),
        );

        $this->assertNotSame($before, (string) file_get_contents($target), 'production должен обновиться');
    }

    public function testWithSessionWritesToSessionNotProduction(): void
    {
        $prodFile = $this->seedFiller($this->root . '/app/example.ru', 'header', 'www', '<?php // prod stub');
        $prodBefore = (string) file_get_contents($prodFile);

        $sessRoot = $this->root . '/app/.sess_abcdef01.example.ru';
        $sessFile = $this->seedFiller($sessRoot, 'header', 'www', '<?php // session stub');
        file_put_contents(
            $sessRoot . '/.session.json',
            json_encode(['permissions' => ['filler:write' => true]], JSON_THROW_ON_ERROR),
        );

        new FillerWriteCommand($this->paths)->run(
            [
                'domain' => 'example.ru',
                'allias' => 'www',
                'block' => 'header',
                'session' => 'abcdef01',
                'json' => '[{"type":"text","content":"y"}]',
            ],
            $this->ctx(),
        );

        $this->assertSame($prodBefore, (string) file_get_contents($prodFile), 'production не тронут');
        $this->assertStringNotContainsString('// session stub', (string) file_get_contents($sessFile), 'session обновлён');
    }

    public function testWithSessionForbiddenByPermission(): void
    {
        $this->seedFiller($this->root . '/app/example.ru', 'header', 'www', '<?php // prod stub');

        $sessRoot = $this->root . '/app/.sess_abcdef01.example.ru';
        $sessFile = $this->seedFiller($sessRoot, 'header', 'www', '<?php // session stub');
        file_put_contents(
            $sessRoot . '/.session.json',
            json_encode(['permissions' => ['filler:write' => false]], JSON_THROW_ON_ERROR),
        );

        new FillerWriteCommand($this->paths)->run(
            [
                'domain' => 'example.ru',
                'allias' => 'www',
                'block' => 'header',
                'session' => 'abcdef01',
                'json' => '[]',
            ],
            $this->ctx(),
        );

        $this->assertSame('<?php // session stub', (string) file_get_contents($sessFile));
    }

    public function testInvalidSessionIdRejected(): void
    {
        $prodFile = $this->seedFiller($this->root . '/app/example.ru', 'header', 'www', '<?php // prod stub');

        new FillerWriteCommand($this->paths)->run(
            [
                'domain' => 'example.ru',
                'allias' => 'www',
                'block' => 'header',
                'session' => 'bad!',
                'json' => '[]',
            ],
            $this->ctx(),
        );

        $this->assertSame('<?php // prod stub', (string) file_get_contents($prodFile));
    }

    public function testMissingSessionDirRejected(): void
    {
        $prodFile = $this->seedFiller($this->root . '/app/example.ru', 'header', 'www', '<?php // prod stub');

        new FillerWriteCommand($this->paths)->run(
            [
                'domain' => 'example.ru',
                'allias' => 'www',
                'block' => 'header',
                'session' => 'abcdef01',
                'json' => '[]',
            ],
            $this->ctx(),
        );

        $this->assertSame('<?php // prod stub', (string) file_get_contents($prodFile));
    }

    private function ctx(): Context
    {
        return new Context(static fn (): null => null);
    }

    private function seedFiller(string $appBase, string $block, string $allias, string $content): string
    {
        $dir = $appBase . '/.blocksglobal/' . $block;
        if (!is_dir($dir)) {
            mkdir($dir, 0o775, true);
        }
        $file = $dir . '/' . $block . '_' . $allias . '.php';
        file_put_contents($file, $content);

        return $file;
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
