<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli\Next;

use Damoony\Cli\Context;
use Damoony\Cli\Next\Commands\PageNewCommand;
use Damoony\Cli\Next\Commands\PageWriteCommand;
use Damoony\Cli\Next\Registry;
use Damoony\Cli\Next\Support\ContentRefs;
use Damoony\Cli\Next\Support\SessionGuard;
use Damoony\Cli\Next\Support\TargetResolver;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Core\Settings\SiteConfigWriter;
use Damoony\UI\Page\PageRules;
use PHPUnit\Framework\TestCase;

final class NextPageEditCommandsTest extends TestCase
{
    private const HASH = '0123456789abcdef';

    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_cli_edit_' . bin2hex(random_bytes(6));
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
        file_put_contents($alg . '/algoritm.editor.json', "[]\n");
        file_put_contents($alg . '/about/algoritm_about.php', "<?php\n\nfinal class algoritm_about\n{\n}\n");
        file_put_contents($alg . '/about/algoritm_about.rules.json', json_encode(['available' => ['' => '^$'], 'auth' => false], JSON_PRETTY_PRINT));
        file_put_contents($alg . '/about/algoritm_about.editor.json', "[]\n");
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    public function testNewCreatesPageFiles(): void
    {
        $result = $this->pageNew()->run(
            ['domain' => 'damoony.ru', 'allias' => 'www', 'name' => 'contacts'],
            $this->context(),
        );

        self::assertTrue($result->ok, implode('; ', $result->errors));

        $dir = $this->root . '/app/damoony.ru/www/algoritm/contacts';
        self::assertFileExists($dir . '/algoritm_contacts.php');
        self::assertFileExists($dir . '/algoritm_contacts.rules.json');
        self::assertFileExists($dir . '/algoritm_contacts.editor.json');

        $php = (string) file_get_contents($dir . '/algoritm_contacts.php');
        self::assertStringContainsString('namespace damoony_ru\www;', $php);
        self::assertStringContainsString('final class algoritm_contacts', $php);

        $parentRules = PageRules::load($this->root . '/app/damoony.ru/www/algoritm/algoritm.rules.json');
        self::assertArrayHasKey('contacts', $parentRules['available']);
    }

    public function testNewNestedPage(): void
    {
        $result = $this->pageNew()->run(
            ['domain' => 'damoony.ru', 'allias' => 'www', 'name' => 'team', 'parent' => 'about'],
            $this->context(),
        );

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertFileExists($this->root . '/app/damoony.ru/www/algoritm/about/team/algoritm_about_team.php');

        $php = (string) file_get_contents($this->root . '/app/damoony.ru/www/algoritm/about/team/algoritm_about_team.php');
        self::assertStringContainsString('final class algoritm_about_team', $php);
    }

    public function testNewRejectsRootPage(): void
    {
        $result = $this->pageNew()->run(
            ['domain' => 'damoony.ru', 'allias' => 'www', 'name' => '__root__'],
            $this->context(),
        );

        self::assertFalse($result->ok);
        self::assertStringContainsString('не создаёт корневую страницу', $result->errors[0]);
    }

    public function testNewRejectsExistingPage(): void
    {
        $result = $this->pageNew()->run(
            ['domain' => 'damoony.ru', 'allias' => 'www', 'name' => 'about'],
            $this->context(),
        );

        self::assertFalse($result->ok);
        self::assertSame(['Страница уже существует'], $result->errors);
    }

    public function testNewRejectsMissingParent(): void
    {
        $result = $this->pageNew()->run(
            ['domain' => 'damoony.ru', 'allias' => 'www', 'name' => 'team', 'parent' => 'ghost'],
            $this->context(),
        );

        self::assertFalse($result->ok);
        self::assertStringContainsString('Родительская', $result->errors[0]);
    }

    public function testNewDryRunCreatesNothing(): void
    {
        $result = $this->pageNew()->run(
            ['domain' => 'damoony.ru', 'allias' => 'www', 'name' => 'contacts'],
            $this->dryRunContext(),
        );

        self::assertTrue($result->ok);
        self::assertTrue($result->data['dry_run']);
        self::assertDirectoryDoesNotExist($this->root . '/app/damoony.ru/www/algoritm/contacts');
    }

    public function testWriteCompilesBlocks(): void
    {
        $json = json_encode([['type' => 'h1', 'content' => 'Привет'], ['type' => 'text', 'content' => 'Абзац']]);

        $result = $this->pageWrite()->run(
            ['domain' => 'damoony.ru', 'allias' => 'www', 'name' => 'about', 'json' => (string) $json],
            $this->context(),
        );

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertSame(2, $result->data['blocks']);

        $php = (string) file_get_contents($this->root . '/app/damoony.ru/www/algoritm/about/algoritm_about.php');
        self::assertStringContainsString('Привет', $php);
        self::assertStringContainsString('Абзац', $php);

        $editor = json_decode((string) file_get_contents($this->root . '/app/damoony.ru/www/algoritm/about/algoritm_about.editor.json'), true);
        self::assertIsArray($editor);
        self::assertCount(2, $editor);
    }

    public function testWriteFromJsonFile(): void
    {
        $file = $this->root . '/blocks.json';
        file_put_contents($file, json_encode([['type' => 'text', 'content' => 'Из файла']]));

        $result = $this->pageWrite()->run(
            ['domain' => 'damoony.ru', 'allias' => 'www', 'name' => 'about', 'json_file' => $file],
            $this->context(),
        );

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertStringContainsString(
            'Из файла',
            (string) file_get_contents($this->root . '/app/damoony.ru/www/algoritm/about/algoritm_about.php'),
        );
    }

    public function testWriteRejectsInvalidJson(): void
    {
        $result = $this->pageWrite()->run(
            ['domain' => 'damoony.ru', 'allias' => 'www', 'name' => 'about', 'json' => '{broken'],
            $this->context(),
        );

        self::assertFalse($result->ok);
        self::assertStringContainsString('Некорректный JSON', $result->errors[0]);
    }

    public function testWriteRejectsMissingPage(): void
    {
        $result = $this->pageWrite()->run(
            ['domain' => 'damoony.ru', 'allias' => 'www', 'name' => 'ghost', 'json' => '[]'],
            $this->context(),
        );

        self::assertFalse($result->ok);
        self::assertStringContainsString('page:new', $result->errors[0]);
    }

    public function testWriteDryRunDoesNotTouchFile(): void
    {
        $before = (string) file_get_contents($this->root . '/app/damoony.ru/www/algoritm/about/algoritm_about.php');

        $result = $this->pageWrite()->run(
            ['domain' => 'damoony.ru', 'allias' => 'www', 'name' => 'about', 'json' => '[{"type":"h1","content":"X"}]'],
            $this->dryRunContext(),
        );

        self::assertTrue($result->ok);
        self::assertSame($before, (string) file_get_contents($this->root . '/app/damoony.ru/www/algoritm/about/algoritm_about.php'));
    }

    public function testWriteUpdatesContentRefs(): void
    {
        $contentDir = $this->root . '/content/damoony.ru/www/general';
        mkdir($contentDir, 0o775, true);
        $metaFile = $contentDir . '/' . self::HASH . '.meta.json';
        file_put_contents($metaFile, json_encode(['mime' => 'image/png', 'page_refs' => []]));

        $url = '/content/damoony.ru/www/general/' . self::HASH . '.png';
        $json = json_encode([['type' => 'img', 'content' => $url, 'alt' => 'Pic']]);

        $result = $this->pageWrite()->run(
            ['domain' => 'damoony.ru', 'allias' => 'www', 'name' => 'about', 'json' => (string) $json],
            $this->context(),
        );

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertSame(1, $result->data['content_refs_updated']);

        $meta = json_decode((string) file_get_contents($metaFile), true);
        self::assertIsArray($meta);
        self::assertSame(['about'], $meta['page_refs']);
    }

    public function testWriteRemovesStaleRefs(): void
    {
        $contentDir = $this->root . '/content/damoony.ru/www/general';
        mkdir($contentDir, 0o775, true);
        $metaFile = $contentDir . '/' . self::HASH . '.meta.json';
        file_put_contents($metaFile, json_encode(['page_refs' => []]));

        $url = '/content/damoony.ru/www/general/' . self::HASH . '.png';
        $withImage = json_encode([['type' => 'img', 'content' => $url, 'alt' => 'Pic']]);

        $first = $this->pageWrite()->run(
            ['domain' => 'damoony.ru', 'allias' => 'www', 'name' => 'about', 'json' => (string) $withImage],
            $this->context(),
        );
        self::assertTrue($first->ok, implode('; ', $first->errors));
        self::assertSame(1, $first->data['content_refs_updated']);

        $second = $this->pageWrite()->run(
            ['domain' => 'damoony.ru', 'allias' => 'www', 'name' => 'about', 'json' => '[{"type":"p","content":"без картинки"}]'],
            $this->context(),
        );

        self::assertTrue($second->ok, implode('; ', $second->errors));
        self::assertSame(1, $second->data['content_refs_updated']);

        $meta = json_decode((string) file_get_contents($metaFile), true);
        self::assertIsArray($meta);
        self::assertSame([], $meta['page_refs']);
    }

    public function testWriteIgnoresForeignDomainRefs(): void
    {
        $foreignMeta = $this->root . '/content/other.ru/www/general/' . self::HASH . '.meta.json';
        mkdir(dirname($foreignMeta), 0o775, true);
        file_put_contents($foreignMeta, json_encode(['page_refs' => []]));

        $url = '/content/other.ru/www/general/' . self::HASH . '.png';
        $json = json_encode([['type' => 'img', 'content' => $url, 'alt' => 'X']]);

        $result = $this->pageWrite()->run(
            ['domain' => 'damoony.ru', 'allias' => 'www', 'name' => 'about', 'json' => (string) $json],
            $this->context(),
        );

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertSame(0, $result->data['content_refs_updated']);

        $meta = json_decode((string) file_get_contents($foreignMeta), true);
        self::assertIsArray($meta);
        self::assertSame([], $meta['page_refs']);
    }

    public function testWriteToleratesMissingMeta(): void
    {
        $url = '/content/damoony.ru/www/general/ffffffffffffffff.png';
        $json = json_encode([['type' => 'img', 'content' => $url, 'alt' => 'X']]);

        $result = $this->pageWrite()->run(
            ['domain' => 'damoony.ru', 'allias' => 'www', 'name' => 'about', 'json' => (string) $json],
            $this->context(),
        );

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertSame(0, $result->data['content_refs_updated']);
    }

    private function resolver(): TargetResolver
    {
        return new TargetResolver(SiteConfig::fromFile(Registry::configPath($this->root)));
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
