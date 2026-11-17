<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\ApiReflectCommand;
use Damoony\Cli\Commands\MigratePageRulesCommand;
use Damoony\Cli\Context;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Core\Settings\SiteConfigWriter;
use PHPUnit\Framework\TestCase;

final class ApiAndMigrationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_cli_api_' . bin2hex(random_bytes(6));
        mkdir($this->root . '/config', 0o775, true);
        mkdir($this->root . '/_dev', 0o775, true);

        $writer = SiteConfigWriter::forRoot($this->root);
        $writer->ensureProject('damoony.ru', 'damoony.ru', 'damoony_ru');
        $writer->ensureMirror('damoony.ru', 'damoony.ru');
        $writer->ensureAlias('damoony.ru', 'www');

        $alg = $this->root . '/app/damoony.ru/www/algoritm';
        mkdir($alg . '/about', 0o775, true);

        file_put_contents($alg . '/.rules.json', json_encode(['auth' => true, 'available' => ['about']]));
        file_put_contents($alg . '/algoritm.php', "<?php\n");
        file_put_contents($alg . '/about/.rules.json', json_encode(['available' => []]));
        file_put_contents($alg . '/about/algoritm_about.php', "<?php\n");
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    public function testReflectGeneratesStub(): void
    {
        $result = $this->reflect()->run([], $this->context());

        self::assertTrue($result->ok, implode('; ', $result->errors));

        $stub = (string) file_get_contents($this->root . '/_dev/api.stub.php');
        self::assertStringContainsString('class BuilderStub', $stub);
        self::assertStringContainsString('class ElementStub', $stub);
        self::assertStringContainsString('namespace Damoony\UI\IDE;', $stub);
    }

    public function testReflectDryRunWritesNothing(): void
    {
        $result = $this->reflect()->run([], $this->dryRunContext());

        self::assertTrue($result->ok);
        self::assertTrue($result->data['dry_run']);
        self::assertFileDoesNotExist($this->root . '/_dev/api.stub.php');
    }

    public function testMigrateMovesLegacyRules(): void
    {
        $result = $this->migrate()->run([], $this->context());

        self::assertTrue($result->ok, implode('; ', $result->errors));

        $alg = $this->root . '/app/damoony.ru/www/algoritm';
        self::assertFileDoesNotExist($alg . '/.rules.json');
        self::assertFileExists($alg . '/algoritm.rules.json');

        self::assertFileDoesNotExist($alg . '/about/.rules.json');
        self::assertFileExists($alg . '/about/algoritm_about.rules.json');

        $rootRules = json_decode((string) file_get_contents($alg . '/algoritm.rules.json'), true);
        self::assertIsArray($rootRules);
        self::assertTrue($rootRules['auth']);
        self::assertContains('about', $rootRules['available']);

        self::assertFileExists($alg . '/algoritm.editor.json');
        self::assertFileExists($alg . '/about/algoritm_about.editor.json');
    }

    public function testMigrateIsIdempotent(): void
    {
        $this->migrate()->run([], $this->context());
        $second = $this->migrate()->run([], $this->context());

        self::assertTrue($second->ok, implode('; ', $second->errors));
        self::assertSame(0, $second->data['migrated']);
    }

    public function testMigrateDryRunKeepsLegacyFiles(): void
    {
        $result = $this->migrate()->run(['dry' => '1'], $this->context());

        self::assertTrue($result->ok);
        self::assertTrue($result->data['dry_run']);
        self::assertFileExists($this->root . '/app/damoony.ru/www/algoritm/.rules.json');
        self::assertFileDoesNotExist($this->root . '/app/damoony.ru/www/algoritm/algoritm.rules.json');
    }

    public function testMigrateRejectsUnknownProject(): void
    {
        $result = $this->migrate()->run(['domain' => 'nope.ru'], $this->context());

        self::assertFalse($result->ok);
        self::assertStringContainsString('не найден в domains_app', $result->errors[0]);
    }

    private function reflect(): ApiReflectCommand
    {
        return new ApiReflectCommand(new Paths($this->root));
    }

    private function migrate(): MigratePageRulesCommand
    {
        return new MigratePageRulesCommand(
            new Paths($this->root),
            SiteConfig::fromFile($this->root . '/config/site.config'),
        );
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
