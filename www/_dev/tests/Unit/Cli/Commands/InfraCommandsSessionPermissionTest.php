<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli\Commands;

use Damoony\Cli\Commands\SchemeSaveCommand;
use Damoony\Cli\Commands\SkinEditCommand;
use Damoony\Cli\Context;
use Damoony\Core\Files\Paths;
use PHPUnit\Framework\TestCase;

final class InfraCommandsSessionPermissionTest extends TestCase
{
    private string $root;
    private Paths $paths;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_infraperm_' . bin2hex(random_bytes(6));
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

    // --- skin:edit ---

    public function testSkinEditWithoutSessionWorks(): void
    {
        $file = $this->seedSkin('example.ru', 'www', 'default');
        $before = (string) file_get_contents($file);

        new SkinEditCommand($this->paths)->run(
            [
                'domain' => 'example.ru',
                'allias' => 'www',
                'name' => 'default',
                'file' => 'css/main.css',
                'content' => 'body { background: red; }',
            ],
            $this->ctx(),
        );

        $this->assertNotSame($before, (string) file_get_contents($file));
    }

    public function testSkinEditWithSessionRejectedByPermission(): void
    {
        $file = $this->seedSkin('example.ru', 'www', 'default');
        $before = (string) file_get_contents($file);

        $this->seedSession('abcdef01', 'example.ru', ['skin:edit' => false]);

        new SkinEditCommand($this->paths)->run(
            [
                'domain' => 'example.ru',
                'allias' => 'www',
                'name' => 'default',
                'file' => 'css/main.css',
                'content' => 'body { background: red; }',
                'session' => 'abcdef01',
            ],
            $this->ctx(),
        );

        $this->assertSame($before, (string) file_get_contents($file), 'production skin не тронут');
    }

    // --- scheme:save ---

    public function testSchemeSaveWithoutSessionWorks(): void
    {
        $editor = $this->seedScheme('example.ru', 'default');
        $before = (string) file_get_contents($editor);

        new SchemeSaveCommand($this->paths)->run(
            [
                'domain' => 'example.ru',
                'name' => 'default',
                'scheme_json' => '{"children":[],"meta":{},"scripts":[]}',
            ],
            $this->ctx(),
        );

        $this->assertNotSame($before, (string) file_get_contents($editor));
    }

    public function testSchemeSaveWithSessionRejectedByPermission(): void
    {
        $editor = $this->seedScheme('example.ru', 'default');
        $before = (string) file_get_contents($editor);

        $this->seedSession('abcdef01', 'example.ru', ['scheme:save' => false]);

        new SchemeSaveCommand($this->paths)->run(
            [
                'domain' => 'example.ru',
                'name' => 'default',
                'scheme_json' => '{"children":[],"meta":{},"scripts":[]}',
                'session' => 'abcdef01',
            ],
            $this->ctx(),
        );

        $this->assertSame($before, (string) file_get_contents($editor), 'production scheme не тронут');
    }

    // --- helpers ---

    private function ctx(): Context
    {
        return new Context(static fn (): null => null);
    }

    private function seedSkin(string $real, string $allias, string $name): string
    {
        $dir = $this->root . '/www/media/' . $real . '/' . $allias . '/skin/' . $name . '/css';
        mkdir($dir, 0o775, true);
        $file = $dir . '/main.css';
        file_put_contents($file, "/* initial */\n");
        return $file;
    }

    private function seedScheme(string $domain, string $name): string
    {
        $dir = $this->root . '/app/' . $domain . '/.schemesglobal';
        mkdir($dir, 0o775, true);
        $file = $dir . '/' . $name . '.editor.json';
        file_put_contents($file, "{\"scheme\":{}}\n");
        return $file;
    }

    /** @param array<string, bool> $perms */
    private function seedSession(string $tab, string $real, array $perms): void
    {
        $dir = $this->root . '/app/.sess_' . $tab . '.' . $real;
        mkdir($dir, 0o775, true);
        file_put_contents(
            $dir . '/.session.json',
            json_encode(['permissions' => $perms], JSON_THROW_ON_ERROR),
        );
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
