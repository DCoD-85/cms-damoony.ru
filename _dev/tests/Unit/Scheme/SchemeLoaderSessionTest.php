<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Scheme;

use Damoony\Domain\DomainContext;
use Damoony\Files\Paths;
use Damoony\Scheme\SchemeLoader;
use PHPUnit\Framework\TestCase;

final class SchemeLoaderSessionTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/sl_' . bin2hex(random_bytes(4));

        mkdir($this->root . '/app/example.ru/.alliasscheme', 0o775, true);
        file_put_contents(
            $this->root . '/app/example.ru/.alliasscheme/www.php',
            '<?php return ["tag" => "div", "class" => "prod-scheme"];',
        );

        mkdir($this->root . '/app/.sess_abc.example.ru/.alliasscheme', 0o775, true);
        file_put_contents(
            $this->root . '/app/.sess_abc.example.ru/.alliasscheme/www.php',
            '<?php return ["tag" => "div", "class" => "session-scheme"];',
        );
    }

    protected function tearDown(): void
    {
        $this->rmrf($this->root);
    }

    public function testSessionReadsFromSessionDir(): void
    {
        $domain = new DomainContext(
            domain: 'example.ru',
            real: 'example.ru',
            allias: 'www',
            skin: 'default',
            language: 'ru',
            available: true,
        );

        $loader = new SchemeLoader(new Paths($this->root), 'abc');
        $scheme = $loader->load($domain, 'www');

        self::assertSame('session-scheme', $scheme['class']);
    }

    public function testNoSessionReadsFromProd(): void
    {
        $domain = new DomainContext(
            domain: 'example.ru',
            real: 'example.ru',
            allias: 'www',
            skin: 'default',
            language: 'ru',
            available: true,
        );

        $loader = new SchemeLoader(new Paths($this->root));
        $scheme = $loader->load($domain, 'www');

        self::assertSame('prod-scheme', $scheme['class']);
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
