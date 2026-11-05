<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\SessionStartCommand;
use Damoony\Cli\Context;
use Damoony\Files\Paths;
use PHPUnit\Framework\TestCase;

final class SessionStartCopyTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/ssc_' . bin2hex(random_bytes(4));
        mkdir($this->root . '/app/example.ru/www/algoritm', 0o775, true);
        mkdir($this->root . '/app/example.ru/.alliasscheme/default', 0o775, true);
        mkdir($this->root . '/www/media/example.ru/www/skin/default/css', 0o775, true);

        file_put_contents($this->root . '/app/example.ru/www/algoritm/algoritm.editor.json', '[]');
        file_put_contents($this->root . '/app/example.ru/.alliasscheme/default/default.editor.json', '{"meta":[]}');
        file_put_contents($this->root . '/www/media/example.ru/www/skin/default/css/main.css', 'body{}');
    }

    protected function tearDown(): void
    {
        $this->rmrf($this->root);
    }

    public function testStartCopiesMediaAndWritesPermissions(): void
    {
        $paths = new Paths($this->root);
        $cmd = new SessionStartCommand($paths);
        $ctx = new Context(static fn () => null);

        $r = $cmd->run(['domain' => 'example.ru', 'tab' => 'a1b2c3d4', 'admin-sess' => 'admin'], $ctx);
        self::assertTrue($r->ok, 'SessionStart failed: ' . implode('; ', $r->errors));

        // app/ side (incl. .alliasscheme via copyTree)
        $sessApp = $this->root . '/app/.sess_a1b2c3d4.example.ru';
        self::assertDirectoryExists($sessApp . '/www/algoritm');
        self::assertFileExists($sessApp . '/www/algoritm/algoritm.editor.json');
        self::assertDirectoryExists($sessApp . '/.alliasscheme/default');
        self::assertFileExists($sessApp . '/.alliasscheme/default/default.editor.json');

        // media side
        $sessMedia = $this->root . '/www/media/.sess_a1b2c3d4.example.ru';
        self::assertDirectoryExists($sessMedia . '/www/skin/default/css');
        self::assertFileExists($sessMedia . '/www/skin/default/css/main.css');

        // permissions
        $meta = json_decode((string) file_get_contents($sessApp . '/.session.json'), true);
        self::assertIsArray($meta);
        self::assertArrayHasKey('permissions', $meta);
        self::assertTrue($meta['permissions']['page:write']);
        self::assertFalse($meta['permissions']['skin:edit']);
        self::assertTrue($meta['permissions']['net:fetch']);
    }

    private function rmrf(string $d): void
    {
        if (!is_dir($d)) {
            return;
        }
        foreach (scandir($d) ?: [] as $i) {
            if ($i === '.' || $i === '..') {
                continue;
            }
            $p = $d . '/' . $i;
            is_dir($p) ? $this->rmrf($p) : @unlink($p);
        }
        @rmdir($d);
    }
}
