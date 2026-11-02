<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Ai;

use Damoony\Ai\Navigator;
use PHPUnit\Framework\TestCase;

final class NavigatorTest extends TestCase
{
    public function testStartEmptyDomainsSuggestsDomainNew(): void
    {
        $next = Navigator::start(['domains' => []]);
        self::assertCount(1, $next);
        self::assertSame('domain:new', $next[0]['cmd']);
        self::assertArrayHasKey('hint', $next[0]);
    }

    public function testStartWithDomainsSuggestsAliasAndDomain(): void
    {
        $next = Navigator::start(['domains' => ['example.ru']]);
        self::assertCount(2, $next);
        $cmds = array_column($next, 'cmd');
        self::assertContains('allias:new', $cmds);
        self::assertContains('domain:new', $cmds);
    }

    public function testStartUnknownStateKeysIgnored(): void
    {
        $next = Navigator::start(['domains' => ['a.ru'], 'junk' => 'x']);
        self::assertCount(2, $next);
    }

    public function testAfterDomainNew(): void
    {
        $next = Navigator::after('domain:new', ['domain' => 'example.ru']);
        $cmds = array_column($next, 'cmd');
        self::assertContains('allias:new', $cmds);
        self::assertContains('done', $cmds);
        $alias = array_values(array_filter($next, fn ($n) => $n['cmd'] === 'allias:new'))[0];
        self::assertSame('example.ru', $alias['args']['domain']);
    }

    public function testAfterAlliasNew(): void
    {
        $next = Navigator::after('allias:new', ['domain' => 'example.ru', 'name' => 'coffee']);
        $cmds = array_column($next, 'cmd');
        self::assertContains('page:new', $cmds);
        self::assertContains('skin:new', $cmds);
        $page = array_values(array_filter($next, fn ($n) => $n['cmd'] === 'page:new'))[0];
        self::assertSame('example.ru', $page['args']['domain']);
        self::assertSame('coffee', $page['args']['allias']);
    }

    public function testAfterSkinNew(): void
    {
        $next = Navigator::after('skin:new', [
            'domain' => 'example.ru',
            'allias' => 'coffee',
            'name' => 'default',
        ]);
        $cmds = array_column($next, 'cmd');
        self::assertContains('skin:edit', $cmds);
        self::assertContains('page:new', $cmds);
        self::assertContains('done', $cmds);
    }

    public function testAfterPageNew(): void
    {
        $next = Navigator::after('page:new', [
            'domain' => 'example.ru',
            'allias' => 'coffee',
            'name' => 'about',
        ]);
        $cmds = array_column($next, 'cmd');
        self::assertContains('page:write', $cmds);
        $write = array_values(array_filter($next, fn ($n) => $n['cmd'] === 'page:write'))[0];
        self::assertSame('example.ru', $write['args']['domain']);
        self::assertSame('coffee', $write['args']['allias']);
        self::assertSame('about', $write['args']['name']);
        self::assertArrayHasKey('blocks', $write['args']);
    }

    public function testAfterPageWrite(): void
    {
        $next = Navigator::after('page:write', [
            'domain' => 'example.ru',
            'allias' => 'coffee',
            'name' => 'about',
        ]);
        $cmds = array_column($next, 'cmd');
        self::assertContains('page:new', $cmds);
        self::assertContains('page:read', $cmds);
        self::assertContains('done', $cmds);
    }

    public function testAfterPageRead(): void
    {
        $next = Navigator::after('page:read', [
            'domain' => 'example.ru',
            'allias' => 'coffee',
            'name' => 'about',
        ]);
        $cmds = array_column($next, 'cmd');
        self::assertContains('page:write', $cmds);
        self::assertContains('done', $cmds);
    }

    public function testAfterDoneEmpty(): void
    {
        self::assertSame([], Navigator::after('done', []));
    }

    public function testAfterUnknownEmpty(): void
    {
        self::assertSame([], Navigator::after('unknown:cmd', []));
    }

    public function testWhitelistContainsCoreCommands(): void
    {
        $wl = Navigator::whitelist();
        self::assertContains('domain:new', $wl);
        self::assertContains('allias:new', $wl);
        self::assertContains('skin:new', $wl);
        self::assertContains('page:new', $wl);
        self::assertContains('page:write', $wl);
        self::assertContains('page:read', $wl);
        self::assertContains('done', $wl);
    }

    public function testIsOfferable(): void
    {
        self::assertTrue(Navigator::isOfferable('domain:new'));
        self::assertTrue(Navigator::isOfferable('done'));
        self::assertFalse(Navigator::isOfferable('user:new'));
        self::assertFalse(Navigator::isOfferable('unknown'));
    }

    public function testAllNextItemsHaveShape(): void
    {
        $samples = [
            Navigator::start(['domains' => []]),
            Navigator::start(['domains' => ['a.ru']]),
            Navigator::after('domain:new', ['domain' => 'a.ru']),
            Navigator::after('allias:new', ['domain' => 'a.ru', 'name' => 'x']),
            Navigator::after('skin:new', ['domain' => 'a.ru', 'allias' => 'x', 'name' => 'default']),
            Navigator::after('page:new', ['domain' => 'a.ru', 'allias' => 'x', 'name' => 'y']),
            Navigator::after('page:write', ['domain' => 'a.ru', 'allias' => 'x', 'name' => 'y']),
            Navigator::after('page:read', ['domain' => 'a.ru', 'allias' => 'x', 'name' => 'y']),
        ];

        foreach ($samples as $next) {
            foreach ($next as $item) {
                self::assertArrayHasKey('cmd', $item);
                self::assertArrayHasKey('args', $item);
                self::assertArrayHasKey('hint', $item);
                self::assertIsString($item['cmd']);
                self::assertIsArray($item['args']);
                self::assertIsString($item['hint']);
                self::assertNotSame('', $item['cmd']);
                self::assertNotSame('', $item['hint']);
                self::assertTrue(Navigator::isOfferable($item['cmd']), 'cmd must be in whitelist: ' . $item['cmd']);
            }
        }
    }

    /**
     * After image:search, image:download must advertise domain/allias as
     * explicit placeholders — before the fix they were '' and an AI following
     * next[] blindly produced 'Security violation: Domain ""'.
     */
    public function testImageDownloadSuggestsDomainAndAlliasPlaceholders(): void
    {
        $next = Navigator::after('image:search', ['query' => 'dog', 'count' => 1]);
        $download = null;
        foreach ($next as $item) {
            if ($item['cmd'] === 'image:download') {
                $download = $item;
                break;
            }
        }
        $this->assertNotNull($download, 'image:download not offered after image:search');
        $this->assertSame('<domain>', $download['args']['domain']);
        $this->assertSame('<allias>', $download['args']['allias']);
        $this->assertSame('<url_from_results>', $download['args']['url']);
    }

    /**
     * After image:download, page:write must advertise name as <page_name>,
     * not '' — before the fix an AI following next[] blindly overwrote
     * __root__ (empty name = root page).
     */
    public function testPageWriteAfterImageDownloadSuggestsPageNamePlaceholder(): void
    {
        $next = Navigator::after('image:download', [
            'url' => 'https://example.com/x.jpg',
            'domain' => 'example.ru',
            'allias' => 'www',
        ]);
        $write = null;
        foreach ($next as $item) {
            if ($item['cmd'] === 'page:write') {
                $write = $item;
                break;
            }
        }
        $this->assertNotNull($write, 'page:write not offered after image:download');
        $this->assertSame('<page_name>', $write['args']['name']);
        // domain/allias carry over from args — sanity check they don't get lost
        $this->assertSame('example.ru', $write['args']['domain']);
        $this->assertSame('www', $write['args']['allias']);
    }
}
