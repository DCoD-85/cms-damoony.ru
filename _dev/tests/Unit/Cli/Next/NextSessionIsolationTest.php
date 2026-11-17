<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli\Next;

use Damoony\Cli\Context;
use Damoony\Cli\Next\Commands\BlockWriteCommand;
use Damoony\Cli\Next\Commands\PageNewCommand;
use Damoony\Cli\Next\Commands\PageWriteCommand;
use Damoony\Cli\Next\Support\ContentRefs;
use Damoony\Cli\Next\Support\SessionGuard;
use Damoony\Cli\Next\Support\TargetResolver;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Core\Settings\SiteConfigWriter;
use PHPUnit\Framework\TestCase;

/**
 * Изоляция AIUI-сессии: запись идёт в app/.sess_{tab}.{folder}/, production не меняется.
 */
final class NextSessionIsolationTest extends TestCase
{
    private const TAB = 'abcdef1234567890';

    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_cli_session_' . bin2hex(random_bytes(6));
        mkdir($this->root . '/config', 0o775, true);

        $writer = SiteConfigWriter::forRoot($this->root);
        $writer->ensureProject('damoony.ru', 'damoony.ru', 'damoony_ru');
        $writer->ensureMirror('damoony.ru', 'damoony.ru');
        $writer->ensureAlias('damoony.ru', 'www');

        $alg = $this->root . '/app/damoony.ru/www/algoritm';
        mkdir($alg, 0o775, true);
        file_put_contents($alg . '/algoritm.php', "<?php\n\nfinal class algoritm\n{\n}\n");
        file_put_contents($alg . '/algoritm.rules.json', json_encode(['available' => ['' => '^$']]));

        $blockDir = $this->root . '/app/damoony.ru/.blocksglobal/top';
        mkdir($blockDir, 0o775, true);
        file_put_contents($blockDir . '/top.php', "<?php\n");
        file_put_contents($blockDir . '/top.rules.json', json_encode(['enabled' => true, 'type' => 'mixed']));

        // Копия домена для сессии (как делает session:start).
        $sessionRoot = $this->root . '/app/.sess_' . self::TAB . '.damoony.ru';
        $this->copyDir($this->root . '/app/damoony.ru', $sessionRoot);
        file_put_contents(
            $sessionRoot . '/.session.json',
            json_encode(['permissions' => ['block:write' => true, 'filler:write' => true]]),
        );
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    public function testPageNewWritesToSessionNotProduction(): void
    {
        $result = $this->pageNew()->run(
            ['domain' => 'damoony.ru', 'allias' => 'www', 'name' => 'about', 'session' => self::TAB],
            $this->context(),
        );

        self::assertTrue($result->ok, implode('; ', $result->errors));

        $sessionFile = $this->root . '/app/.sess_' . self::TAB . '.damoony.ru/www/algoritm/about/algoritm_about.php';
        self::assertFileExists($sessionFile);
        self::assertFileDoesNotExist($this->root . '/app/damoony.ru/www/algoritm/about/algoritm_about.php');
    }

    public function testPageWriteWritesToSessionNotProduction(): void
    {
        $this->pageNew()->run(
            ['domain' => 'damoony.ru', 'allias' => 'www', 'name' => 'about', 'session' => self::TAB],
            $this->context(),
        );

        $json = json_encode([['type' => 'h1', 'content' => 'В сессии']]);

        $result = $this->pageWrite()->run(
            [
                'domain' => 'damoony.ru',
                'allias' => 'www',
                'name' => 'about',
                'json' => (string) $json,
                'session' => self::TAB,
            ],
            $this->context(),
        );

        self::assertTrue($result->ok, implode('; ', $result->errors));

        $sessionFile = $this->root . '/app/.sess_' . self::TAB . '.damoony.ru/www/algoritm/about/algoritm_about.php';
        self::assertStringContainsString('В сессии', (string) file_get_contents($sessionFile));
        self::assertFileDoesNotExist($this->root . '/app/damoony.ru/www/algoritm/about/algoritm_about.php');
    }

    public function testBlockWriteWritesToSession(): void
    {
        $json = json_encode([['type' => 'p', 'content' => 'Блок в сессии']]);

        $result = $this->blockWrite()->run(
            ['domain' => 'damoony.ru', 'name' => 'top', 'json' => (string) $json, 'session' => self::TAB],
            $this->context(),
        );

        self::assertTrue($result->ok, implode('; ', $result->errors));

        $sessionBlock = $this->root . '/app/.sess_' . self::TAB . '.damoony.ru/.blocksglobal/top/top.php';
        self::assertStringContainsString('Блок в сессии', (string) file_get_contents($sessionBlock));
        self::assertStringNotContainsString(
            'Блок в сессии',
            (string) file_get_contents($this->root . '/app/damoony.ru/.blocksglobal/top/top.php'),
        );
    }

    public function testInvalidSessionIdRejected(): void
    {
        $result = $this->blockWrite()->run(
            ['domain' => 'damoony.ru', 'name' => 'top', 'json' => '[]', 'session' => 'zzz'],
            $this->context(),
        );

        self::assertFalse($result->ok);
        self::assertStringContainsString('Некорректный --session', $result->errors[0]);
    }

    public function testMissingSessionDirRejected(): void
    {
        $result = $this->pageWrite()->run(
            ['domain' => 'damoony.ru', 'allias' => 'www', 'name' => '__root__', 'json' => '[]', 'session' => 'deadbeef'],
            $this->context(),
        );

        self::assertFalse($result->ok);
        self::assertStringContainsString('Сессия не найдена', $result->errors[0]);
    }

    public function testSessionPermissionDenied(): void
    {
        $sessionRoot = $this->root . '/app/.sess_' . self::TAB . '.damoony.ru';
        file_put_contents($sessionRoot . '/.session.json', json_encode(['permissions' => ['block:write' => false]]));

        $result = $this->blockWrite()->run(
            ['domain' => 'damoony.ru', 'name' => 'top', 'json' => '[]', 'session' => self::TAB],
            $this->context(),
        );

        self::assertFalse($result->ok);
        self::assertStringContainsString('запрещена в этой сессии', $result->errors[0]);
    }

    private function site(): SiteConfig
    {
        return SiteConfig::fromFile($this->root . '/config/site.config');
    }

    private function resolver(): TargetResolver
    {
        return new TargetResolver($this->site());
    }

    private function pageNew(): PageNewCommand
    {
        $paths = new Paths($this->root);

        return new PageNewCommand($paths, $this->resolver(), new SessionGuard($paths));
    }

    private function pageWrite(): PageWriteCommand
    {
        $paths = new Paths($this->root);

        return new PageWriteCommand($paths, $this->resolver(), new ContentRefs($paths), new SessionGuard($paths));
    }

    private function blockWrite(): BlockWriteCommand
    {
        $paths = new Paths($this->root);

        return new BlockWriteCommand($paths, $this->site(), new SessionGuard($paths));
    }

    private function context(): Context
    {
        return new Context(static function (string $m, bool $ok = true, string $d = ''): void {
        });
    }

    private function copyDir(string $src, string $dst): void
    {
        if (!is_dir($dst)) {
            mkdir($dst, 0o775, true);
        }
        foreach (scandir($src) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $from = $src . '/' . $item;
            $to = $dst . '/' . $item;
            is_dir($from) ? $this->copyDir($from, $to) : copy($from, $to);
        }
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
