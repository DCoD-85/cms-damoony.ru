<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\StructureCheckCommand;
use Damoony\Cli\Commands\StructureRepairCommand;
use Damoony\Cli\Context;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Core\Settings\SiteConfigWriter;
use Damoony\Diagnostics\StructureChecker;
use Damoony\UI\Region\FillerState;
use PHPUnit\Framework\TestCase;

final class StructureCommandsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_cli_structure_' . bin2hex(random_bytes(6));
        mkdir($this->root . '/config', 0o775, true);

        $writer = SiteConfigWriter::forRoot($this->root);
        $writer->ensureProject('damoony.ru', 'damoony.ru', 'damoony_ru');
        $writer->ensureMirror('damoony.ru', 'damoony.ru');
        $writer->ensureAlias('damoony.ru', 'www');

        $alg = $this->root . '/app/damoony.ru/www/algoritm';
        mkdir($alg . '/about', 0o775, true);
        file_put_contents($alg . '/algoritm.php', "<?php\n");
        file_put_contents($alg . '/algoritm.rules.json', json_encode(['available' => ['']]));

        mkdir($this->root . '/app/damoony.ru/.schemesglobal', 0o775, true);
        file_put_contents(
            $this->root . '/app/damoony.ru/.schemesglobal/www.php',
            "<?php return ['tag' => 'div', 'children' => [['tag' => 'header', 'slot' => 'top']]];",
        );

        mkdir($this->root . '/app/damoony.ru/.blocksglobal/top', 0o775, true);
        file_put_contents($this->root . '/app/damoony.ru/.blocksglobal/top/top.php', "<?php\n");
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    public function testCheckWarnsAboutMissingSchemeAssets(): void
    {
        // Схема ссылается на CSS, которого нет в скине → warning, не error.
        file_put_contents(
            $this->root . '/app/damoony.ru/.schemesglobal/www.php',
            "<?php return ['tag' => 'div', 'children' => [['tag' => 'header', 'slot' => 'top']],"
            . " 'meta' => ['css' => ['/media/{real}/{skin_path}/css/main.css', '/media/{real}/{skin_path}/css/missing.css?v=3']]];",
        );
        mkdir($this->root . '/www/media/damoony.ru/www/skin/default/css', 0o775, true);
        file_put_contents($this->root . '/www/media/damoony.ru/www/skin/default/css/main.css', "/* ok */\n");

        $result = $this->check()->run([], $this->context());

        $warnings = implode("\n", $result->data['warnings']);
        self::assertStringContainsString('несуществующий css', $warnings);
        self::assertStringContainsString('missing.css', $warnings);
        // main.css существует — про него предупреждения быть не должно (query-string не мешает).
        self::assertStringNotContainsString('css/main.css', $warnings);
    }

    public function testCheckReportsMissingFiles(): void
    {
        $result = $this->check()->run([], $this->context());

        self::assertFalse($result->ok, 'отсутствующие файлы должны быть ошибкой');
        self::assertNotEmpty($result->data['errors']);

        $joined = implode("\n", $result->data['errors']);
        self::assertStringContainsString('algoritm_about', $joined);
    }

    public function testCheckWarnsAboutMissingRules(): void
    {
        file_put_contents($this->root . '/app/damoony.ru/www/algoritm/about/algoritm_about.php', "<?php\n");
        file_put_contents($this->root . '/app/damoony.ru/www/algoritm/about/algoritm_about.rules.json', '{}');
        file_put_contents($this->root . '/app/damoony.ru/www/algoritm/about/algoritm_about.editor.json', '[]');
        mkdir($this->root . '/app/damoony.ru/.schemesglobal/www.rules.json.d', 0o775, true);
        rmdir($this->root . '/app/damoony.ru/.schemesglobal/www.rules.json.d');
        file_put_contents($this->root . '/app/damoony.ru/.schemesglobal/www.rules.json', '{"enabled":true}');
        file_put_contents($this->root . '/app/damoony.ru/.blocksglobal/top/top.rules.json', '{"enabled":true}');
        file_put_contents($this->root . '/app/damoony.ru/.blocksglobal/top/top.editor.json', '[]');

        $result = $this->check()->run([], $this->context());

        self::assertTrue($result->ok, implode('; ', $result->data['errors']));
        self::assertSame([], $result->data['errors']);
    }

    public function testCheckRejectsUnknownProject(): void
    {
        $result = $this->check()->run(['domain' => 'nope.ru'], $this->context());

        self::assertFalse($result->ok);
        self::assertStringContainsString('не найден в domains_app', $result->errors[0]);
    }

    public function testRepairRestoresRootFiles(): void
    {
        $result = $this->repair()->run(['domain' => 'damoony.ru'], $this->context());

        self::assertTrue($result->ok, implode('; ', $result->errors));

        $alg = $this->root . '/app/damoony.ru/www/algoritm';
        self::assertFileExists($alg . '/algoritm.php');
        self::assertFileExists($alg . '/algoritm.rules.json');
        self::assertFileExists($alg . '/algoritm.editor.json');
        self::assertFileExists($this->root . '/app/damoony.ru/.schemesglobal/www.rules.json');
        self::assertFileExists($this->root . '/app/damoony.ru/.blocksglobal/top/top.rules.json');
        self::assertFileExists($this->root . '/app/damoony.ru/.blocksglobal/top/top.editor.json');
    }

    public function testRepairIsIdempotent(): void
    {
        $this->repair()->run(['domain' => 'damoony.ru'], $this->context());
        $second = $this->repair()->run(['domain' => 'damoony.ru'], $this->context());

        self::assertTrue($second->ok, implode('; ', $second->errors));
        self::assertSame(0, $second->data['fixed']);
    }

    public function testRepairDryRunWritesNothing(): void
    {
        $result = $this->repair()->run(['domain' => 'damoony.ru'], $this->dryRunContext());

        self::assertTrue($result->ok);
        self::assertTrue($result->data['dry_run']);
        self::assertGreaterThan(0, $result->data['fixed']);
        self::assertFileDoesNotExist($this->root . '/app/damoony.ru/www/algoritm/algoritm.editor.json');
    }

    private function site(): SiteConfig
    {
        return SiteConfig::fromFile($this->root . '/config/site.config');
    }

    private function check(): StructureCheckCommand
    {
        $paths = new Paths($this->root);
        $site = $this->site();

        return new StructureCheckCommand(
            new StructureChecker($paths, $site, new FillerState($paths)),
            $site,
        );
    }

    private function repair(): StructureRepairCommand
    {
        return new StructureRepairCommand(new Paths($this->root), $this->site());
    }

    private function context(): Context
    {
        return new Context(static function (string $m, bool $ok = true, string $d = ''): void {
        });
    }

    private function dryRunContext(): Context
    {
        return new Context(static function (string $m, bool $ok = true, string $d = ''): void {
        }, false, true);
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
