<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\PageListCommand;
use Damoony\Cli\Commands\PageReadCommand;
use Damoony\Cli\Commands\PageToggleCommand;
use Damoony\Cli\Context;
use Damoony\Cli\Registry;
use Damoony\Cli\Support\TargetResolver;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Core\Settings\SiteConfigWriter;
use Damoony\UI\Page\PageRules;
use PHPUnit\Framework\TestCase;

final class PageCommandsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_cli_pages_' . bin2hex(random_bytes(6));
        mkdir($this->root . '/config', 0o775, true);

        $writer = SiteConfigWriter::forRoot($this->root);
        $writer->ensureProject('damoony.ru', 'damoony.ru', 'damoony_ru');
        $writer->ensureMirror('damoony.ru', 'damoony.ru');
        $writer->ensureAlias('damoony.ru', 'www', ['ru', 'en']);

        $alg = $this->root . '/app/damoony.ru/www/algoritm';
        mkdir($alg . '/about', 0o775, true);

        file_put_contents($alg . '/algoritm.php', "<?php\n\nfinal class algoritm\n{\n}\n");
        file_put_contents(
            $alg . '/algoritm.rules.json',
            json_encode(['available' => ['' => '^$', 'lang' => '^([a-z]{2})$', 'about' => '^about$'], 'auth' => false], JSON_PRETTY_PRINT),
        );
        file_put_contents($alg . '/about/algoritm_about.php', "<?php\n\nfinal class algoritm_about\n{\n}\n");
        file_put_contents(
            $alg . '/about/algoritm_about.rules.json',
            json_encode(['available' => ['' => '^$'], 'auth' => false], JSON_PRETTY_PRINT),
        );
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    public function testListReturnsRootAndNested(): void
    {
        $result = $this->pageList()->run(['domain' => 'damoony.ru', 'allias' => 'www'], $this->context());

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertSame(2, $result->data['count']);

        $names = array_column($result->data['rows'], 'name');
        self::assertSame(['__root__', 'about'], $names);
        self::assertSame(['', 'lang', 'about'], $result->data['rows'][0]['available']);
    }

    public function testListByMirrorHost(): void
    {
        $this->writer()->ensureMirror('damoony.ru', 'damoony3.ru');

        $result = $this->pageList()->run(['domain' => 'damoony3.ru', 'allias' => 'www'], $this->context());

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertSame('damoony.ru', $result->data['project']);
    }

    public function testListRejectsUnknownProject(): void
    {
        $result = $this->pageList()->run(['domain' => 'nope.ru', 'allias' => 'www'], $this->context());

        self::assertFalse($result->ok);
        self::assertStringContainsString('не найден в domains_app', $result->errors[0]);
    }

    public function testListRejectsUndeclaredAlias(): void
    {
        $result = $this->pageList()->run(['domain' => 'damoony.ru', 'allias' => 'ghost'], $this->context());

        self::assertFalse($result->ok);
        self::assertStringContainsString('не объявлен у проекта', $result->errors[0]);
    }

    public function testReadReturnsCode(): void
    {
        $result = $this->pageRead()->run(
            ['domain' => 'damoony.ru', 'allias' => 'www', 'name' => '__root__'],
            $this->context(),
        );

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertStringContainsString('final class algoritm', $result->data['code']);
        self::assertSame('__root__', $result->data['name']);
    }

    public function testReadNestedPage(): void
    {
        $result = $this->pageRead()->run(
            ['domain' => 'damoony.ru', 'allias' => 'www', 'name' => 'about'],
            $this->context(),
        );

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertStringContainsString('final class algoritm_about', $result->data['code']);
    }

    public function testReadRequiresName(): void
    {
        $result = $this->pageRead()->run(['domain' => 'damoony.ru', 'allias' => 'www'], $this->context());

        self::assertFalse($result->ok);
        self::assertStringContainsString('Укажите --name', $result->errors[0]);
    }

    public function testReadMissingPage(): void
    {
        $result = $this->pageRead()->run(
            ['domain' => 'damoony.ru', 'allias' => 'www', 'name' => 'ghost'],
            $this->context(),
        );

        self::assertFalse($result->ok);
        self::assertStringContainsString('Страница не найдена', $result->errors[0]);
    }

    public function testToggleAuthWritesRules(): void
    {
        $result = $this->pageToggle()->run(
            ['domain' => 'damoony.ru', 'allias' => 'www', 'name' => 'about', 'key' => 'auth', 'value' => '1'],
            $this->context(),
        );

        self::assertTrue($result->ok, implode('; ', $result->errors));

        $rules = PageRules::load($this->root . '/app/damoony.ru/www/algoritm/about/algoritm_about.rules.json');
        self::assertTrue($rules['auth']);
    }

    public function testToggleRootPage(): void
    {
        $result = $this->pageToggle()->run(
            ['domain' => 'damoony.ru', 'allias' => 'www', 'name' => '__root__', 'key' => 'search', 'value' => '0'],
            $this->context(),
        );

        self::assertTrue($result->ok, implode('; ', $result->errors));

        $rules = PageRules::load($this->root . '/app/damoony.ru/www/algoritm/algoritm.rules.json');
        self::assertFalse($rules['search']);
    }

    public function testToggleDryRunDoesNotWrite(): void
    {
        $before = (string) file_get_contents($this->root . '/app/damoony.ru/www/algoritm/about/algoritm_about.rules.json');

        $result = $this->pageToggle()->run(
            ['domain' => 'damoony.ru', 'allias' => 'www', 'name' => 'about', 'key' => 'auth', 'value' => '1'],
            $this->dryRunContext(),
        );

        self::assertTrue($result->ok);
        self::assertTrue($result->data['dry_run']);
        self::assertSame($before, (string) file_get_contents($this->root . '/app/damoony.ru/www/algoritm/about/algoritm_about.rules.json'));
    }

    public function testToggleRejectsInvalidKey(): void
    {
        $result = $this->pageToggle()->run(
            ['domain' => 'damoony.ru', 'allias' => 'www', 'name' => 'about', 'key' => 'nope'],
            $this->context(),
        );

        self::assertFalse($result->ok);
        self::assertStringContainsString('Недопустимый ключ', $result->errors[0]);
    }

    public function testToggleRejectsRootWithParent(): void
    {
        $result = $this->pageToggle()->run(
            ['domain' => 'damoony.ru', 'allias' => 'www', 'name' => '__root__', 'parent' => 'about', 'key' => 'auth'],
            $this->context(),
        );

        self::assertFalse($result->ok);
        self::assertStringContainsString('--parent несовместим с __root__', $result->errors[0]);
    }

    private function resolver(): TargetResolver
    {
        return new TargetResolver(SiteConfig::fromFile(Registry::configPath($this->root)));
    }

    private function pageList(): PageListCommand
    {
        return new PageListCommand(new Paths($this->root), $this->resolver());
    }

    private function pageRead(): PageReadCommand
    {
        return new PageReadCommand(new Paths($this->root), $this->resolver());
    }

    private function pageToggle(): PageToggleCommand
    {
        return new PageToggleCommand(new Paths($this->root), $this->resolver());
    }

    private function writer(): SiteConfigWriter
    {
        return SiteConfigWriter::forRoot($this->root);
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
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
