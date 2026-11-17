<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli\Next;

use Damoony\Cli\Context;
use Damoony\Cli\Next\Commands\SessionApplyCommand;
use Damoony\Cli\Next\Commands\SessionDiscardCommand;
use Damoony\Cli\Next\Commands\SessionListCommand;
use Damoony\Cli\Next\Commands\SessionStartCommand;
use Damoony\Cli\Next\Support\SessionWorkspace;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Core\Settings\SiteConfigWriter;
use PHPUnit\Framework\TestCase;

final class NextSessionCommandsTest extends TestCase
{
    private const TAB = 'abcdef1234567890';

    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_cli_sess_' . bin2hex(random_bytes(6));
        mkdir($this->root . '/config', 0o775, true);

        $writer = SiteConfigWriter::forRoot($this->root);
        $writer->ensureProject('damoony.ru', 'damoony.ru', 'damoony_ru');
        $writer->ensureMirror('damoony.ru', 'damoony.ru');
        $writer->ensureMirror('damoony.ru', 'damoony3.ru');
        $writer->ensureAlias('damoony.ru', 'www');

        $alg = $this->root . '/app/damoony.ru/www/algoritm';
        mkdir($alg, 0o775, true);
        file_put_contents($alg . '/algoritm.php', "<?php\n");
        file_put_contents($alg . '/algoritm.editor.json', '[]');

        mkdir($this->root . '/www/media/damoony.ru/www/skin/default/css', 0o775, true);
        file_put_contents($this->root . '/www/media/damoony.ru/www/skin/default/css/main.css', 'body{}');
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    public function testStartCopiesProjectAndMedia(): void
    {
        $result = $this->start()->run(['domain' => 'damoony3.ru', 'tab' => self::TAB], $this->context());

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertSame('damoony.ru', $result->data['project']);

        $sessionDir = $this->root . '/app/.sess_' . self::TAB . '.damoony.ru';
        self::assertFileExists($sessionDir . '/www/algoritm/algoritm.php');
        self::assertFileExists($sessionDir . '/.session.json');
        self::assertFileExists($this->root . '/www/media/.sess_' . self::TAB . '.damoony.ru/www/skin/default/css/main.css');

        $meta = json_decode((string) file_get_contents($sessionDir . '/.session.json'), true);
        self::assertIsArray($meta);
        self::assertSame('damoony.ru', $meta['project']);
        self::assertSame(self::TAB, $meta['tab_id']);
        self::assertTrue($meta['permissions']['page:write']);
        self::assertFalse($meta['permissions']['skin:edit']);
    }

    public function testStartRejectsDuplicate(): void
    {
        $this->start()->run(['domain' => 'damoony.ru', 'tab' => self::TAB], $this->context());

        $result = $this->start()->run(['domain' => 'damoony.ru', 'tab' => self::TAB], $this->context());

        self::assertFalse($result->ok);
        self::assertStringContainsString('Сессия уже существует', $result->errors[0]);
    }

    public function testStartRejectsInvalidTab(): void
    {
        $result = $this->start()->run(['domain' => 'damoony.ru', 'tab' => 'zzz'], $this->context());

        self::assertFalse($result->ok);
        self::assertStringContainsString('--tab', $result->errors[0]);
    }

    public function testApplyRequiresAdminSession(): void
    {
        $this->start()->run(['domain' => 'damoony.ru', 'tab' => self::TAB], $this->context());

        $result = $this->apply()->run(['domain' => 'damoony.ru', 'tab' => self::TAB], $this->context());

        self::assertFalse($result->ok);
        self::assertStringContainsString('административной сессии', $result->errors[0]);
    }

    public function testApplyReplacesProduction(): void
    {
        $this->start()->run(['domain' => 'damoony.ru', 'tab' => self::TAB], $this->context());

        $sessionAlg = $this->root . '/app/.sess_' . self::TAB . '.damoony.ru/www/algoritm/algoritm.php';
        file_put_contents($sessionAlg, "<?php // из сессии\n");

        $result = $this->apply()->run(
            ['domain' => 'damoony.ru', 'tab' => self::TAB, 'admin-sess' => 'abc'],
            $this->context(),
        );

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertStringContainsString('из сессии', (string) file_get_contents($this->root . '/app/damoony.ru/www/algoritm/algoritm.php'));
        self::assertDirectoryDoesNotExist($this->root . '/app/.sess_' . self::TAB . '.damoony.ru');
        self::assertNotEmpty(glob($this->root . '/app/_trash_sess_' . self::TAB . '_*') ?: []);
    }

    public function testApplyRejectsOnHashConflict(): void
    {
        $this->start()->run(['domain' => 'damoony.ru', 'tab' => self::TAB], $this->context());

        // production изменился после старта сессии
        file_put_contents($this->root . '/app/damoony.ru/www/algoritm/algoritm.editor.json', '["changed"]');

        $result = $this->apply()->run(
            ['domain' => 'damoony.ru', 'tab' => self::TAB, 'admin-sess' => 'abc'],
            $this->context(),
        );

        self::assertFalse($result->ok);
        self::assertStringContainsString('Конфликт', $result->errors[0]);
    }

    public function testDiscardQuarantinesSession(): void
    {
        $this->start()->run(['domain' => 'damoony.ru', 'tab' => self::TAB], $this->context());

        $result = $this->discard()->run(['domain' => 'damoony.ru', 'tab' => self::TAB], $this->context());

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertDirectoryDoesNotExist($this->root . '/app/.sess_' . self::TAB . '.damoony.ru');
        self::assertDirectoryDoesNotExist($this->root . '/www/media/.sess_' . self::TAB . '.damoony.ru');
        self::assertNotEmpty(glob($this->root . '/app/_trash_sess_' . self::TAB . '_*') ?: []);
    }

    public function testDiscardMissingSessionIsHarmless(): void
    {
        $result = $this->discard()->run(['domain' => 'damoony.ru', 'tab' => self::TAB], $this->context());

        self::assertTrue($result->ok);
        self::assertCount(1, $result->warnings);
    }

    public function testListReportsSessions(): void
    {
        $this->start()->run(['domain' => 'damoony.ru', 'tab' => self::TAB], $this->context());

        $result = $this->list()->run([], $this->context());

        self::assertTrue($result->ok);
        self::assertCount(1, $result->data['sessions']);
        self::assertSame(self::TAB, $result->data['sessions'][0]['tab_id']);
        self::assertSame('damoony.ru', $result->data['sessions'][0]['project']);
        self::assertGreaterThan(0, $result->data['sessions'][0]['ttl_remaining']);
    }

    private function site(): SiteConfig
    {
        return SiteConfig::fromFile($this->root . '/config/site.config');
    }

    private function workspace(): SessionWorkspace
    {
        return new SessionWorkspace(new Paths($this->root));
    }

    private function start(): SessionStartCommand
    {
        return new SessionStartCommand(new Paths($this->root), $this->site(), $this->workspace());
    }

    private function apply(): SessionApplyCommand
    {
        return new SessionApplyCommand(new Paths($this->root), $this->site(), $this->workspace());
    }

    private function discard(): SessionDiscardCommand
    {
        return new SessionDiscardCommand(new Paths($this->root), $this->site(), $this->workspace());
    }

    private function list(): SessionListCommand
    {
        return new SessionListCommand(new Paths($this->root), $this->workspace());
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
