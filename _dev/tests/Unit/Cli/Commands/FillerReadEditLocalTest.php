<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli\Commands;

use Damoony\Cli\Commands\FillerEditCommand;
use Damoony\Cli\Commands\FillerListCommand;
use Damoony\Cli\Commands\FillerNewCommand;
use Damoony\Cli\Commands\FillerToggleCommand;
use Damoony\Cli\Context;
use Damoony\Core\Files\Paths;
use PHPUnit\Framework\TestCase;

final class FillerReadEditLocalTest extends TestCase
{
    private string $root;
    private Paths $paths;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_filleredit_' . bin2hex(random_bytes(6));
        mkdir($this->root . '/config', 0o775, true);
        $this->paths = new Paths($this->root);

        file_put_contents(
            $this->root . '/config/site.config',
            json_encode([
                'domains_real' => ['real.ru' => 'real.ru', 'alias.ru' => 'real.ru'],
                'domains_namespace' => ['real.ru' => 'real_ru'],
                'alliases_real' => [
                    'real.ru' => ['www' => 'www'],
                    'alias.ru' => ['www' => 'www'],
                ],
                'fillers' => ['real.ru' => ['www' => []]],
            ], JSON_THROW_ON_ERROR),
        );

        // Базовый блок + алиас
        mkdir($this->root . '/app/real.ru/.blocksglobal/center', 0o775, true);
        file_put_contents($this->root . '/app/real.ru/.blocksglobal/center/center.php', "<?php // base\n");
        mkdir($this->root . '/app/real.ru/www/algoritm', 0o775, true);
        file_put_contents($this->root . '/app/real.ru/www/algoritm/algoritm.php', "<?php // root\n");
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    public function testToggleWritesUnderRealDomain(): void
    {
        // Сначала создаём filler через FillerNew (пишет local + config[real])
        new FillerNewCommand($this->paths)->run(
            ['domain' => 'alias.ru', 'allias' => 'www', 'block' => 'center'],
            $this->ctx(),
        );

        // Toggle выключаем — должен писать в [real.ru]
        new FillerToggleCommand($this->paths)->run(
            ['domain' => 'alias.ru', 'allias' => 'www', 'block' => 'center', 'value' => '0'],
            $this->ctx(),
        );

        $site = json_decode((string) file_get_contents($this->root . '/config/site.config'), true);
        $this->assertFalse(
            $site['fillers']['real.ru']['www']['center'] ?? null,
            'toggle должен записать под real.ru',
        );
        $this->assertArrayNotHasKey('alias.ru', $site['fillers'] ?? []);
    }

    public function testToggleIdempotentReadsLegacyConfig(): void
    {
        // Legacy: config под alias-ключом, filler локальный
        $site = json_decode((string) file_get_contents($this->root . '/config/site.config'), true);
        $site['fillers']['alias.ru'] = ['www' => ['center' => true]];
        file_put_contents($this->root . '/config/site.config', json_encode($site, JSON_THROW_ON_ERROR));

        mkdir($this->root . '/app/real.ru/www/.blockslocal/center', 0o775, true);
        file_put_contents($this->root . '/app/real.ru/www/.blockslocal/center/center.php', "<?php // local\n");

        // toggle --value=1: current legacy=true, value=true → idempotent skip
        new FillerToggleCommand($this->paths)->run(
            ['domain' => 'alias.ru', 'allias' => 'www', 'block' => 'center', 'value' => '1'],
            $this->ctx(),
        );

        $after = json_decode((string) file_get_contents($this->root . '/config/site.config'), true);
        // Legacy ключ остался как был, канонический не появился
        $this->assertTrue($after['fillers']['alias.ru']['www']['center']);
        $this->assertNull($after['fillers']['real.ru']['www']['center'] ?? null);
    }

    public function testEditPrefersLocalPath(): void
    {
        new FillerNewCommand($this->paths)->run(
            ['domain' => 'real.ru', 'allias' => 'www', 'block' => 'center'],
            $this->ctx(),
        );

        new FillerEditCommand($this->paths)->run(
            [
                'domain' => 'real.ru',
                'allias' => 'www',
                'block' => 'center',
                'file' => 'editor',
                'content' => '{"marker":"edited-local"}',
            ],
            $this->ctx(),
        );

        $editor = json_decode((string) file_get_contents(
            $this->root . '/app/real.ru/www/.blockslocal/center/center.editor.json',
        ), true);
        $this->assertSame('edited-local', $editor['marker'] ?? null);
    }

    public function testEditFallsBackToLegacy(): void
    {
        // Только legacy-файлы, без local
        $legacyBlockDir = $this->root . '/app/real.ru/.blocksglobal/center';
        if (!is_dir($legacyBlockDir)) {
            mkdir($legacyBlockDir, 0o775, true);
        }
        file_put_contents(
            $legacyBlockDir . '/center_www.php',
            "<?php // legacy filler\n",
        );
        file_put_contents(
            $legacyBlockDir . '/center_www.rules.json',
            '{"enabled":true}',
        );

        new FillerEditCommand($this->paths)->run(
            [
                'domain' => 'real.ru',
                'allias' => 'www',
                'block' => 'center',
                'file' => 'rules',
                'content' => '{"enabled":false}',
            ],
            $this->ctx(),
        );

        $rules = json_decode((string) file_get_contents(
            $this->root . '/app/real.ru/.blocksglobal/center/center_www.rules.json',
        ), true);
        $this->assertFalse($rules['enabled']);
    }

    public function testListScansBothLocalAndLegacy(): void
    {
        // local filler для center
        new FillerNewCommand($this->paths)->run(
            ['domain' => 'real.ru', 'allias' => 'www', 'block' => 'center'],
            $this->ctx(),
        );
        // legacy filler для top
        $topDir = $this->root . '/app/real.ru/.blocksglobal/top';
        if (!is_dir($topDir)) {
            mkdir($topDir, 0o775, true);
        }
        file_put_contents($topDir . '/top.php', "<?php // base\n");
        file_put_contents($this->root . '/app/real.ru/.blocksglobal/top/top_www.php', "<?php // legacy\n");

        $r = new \Damoony\Cli\Result();
        $captured = '';
        $ctx = new Context(static function (string $m, bool $ok = true, string $d = '') use (&$captured): void {
            $captured .= $m . "\n";
        });

        $res = new FillerListCommand($this->paths)->run(
            ['domain' => 'real.ru', 'allias' => 'www'],
            $ctx,
        );

        $this->assertStringContainsString('center', $captured, 'local filler должен быть в списке');
        $this->assertStringContainsString('top', $captured, 'legacy filler должен быть в списке');
        $this->assertSame(2, count($res->data['rows'] ?? []));
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
