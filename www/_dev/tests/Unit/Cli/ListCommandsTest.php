<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\BlockListCommand;
use Damoony\Cli\Commands\FillerListCommand;
use Damoony\Cli\Commands\SchemeListCommand;
use Damoony\Cli\Context;
use Damoony\Core\Files\Paths;
use PHPUnit\Framework\TestCase;

final class ListCommandsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_list_' . bin2hex(random_bytes(4));
        mkdir($this->root . '/config', 0o777, true);
        file_put_contents($this->root . '/config/site.config', json_encode([
            'domains_real' => ['example.ru' => 'example.ru'],
            'alliases_real' => ['example.ru' => ['www' => 'www']],
        ]));
        mkdir($this->root . '/app/example.ru', 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->rmrf($this->root);
    }

    private function rmrf(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $e) {
            if ($e === '.' || $e === '..') {
                continue;
            }
            $p = $dir . '/' . $e;
            is_dir($p) ? $this->rmrf($p) : @unlink($p);
        }
        @rmdir($dir);
    }

    // === scheme:list ===

    public function testSchemeListEmpty(): void
    {
        $res = new SchemeListCommand(new Paths($this->root))
            ->run(['domain' => 'example.ru'], new Context(static fn () => null));
        self::assertTrue($res->ok);
        self::assertSame([], $res->data['rows']);
    }

    public function testSchemeListWithSchemes(): void
    {
        $dir = $this->root . '/app/example.ru/.schemesglobal';
        mkdir($dir, 0o777, true);
        file_put_contents($dir . '/www.php', '<?php');
        file_put_contents($dir . '/www.rules.json', json_encode(['enabled' => true]));
        file_put_contents($dir . '/www.editor.json', '[]');
        file_put_contents($dir . '/old.php', '<?php');
        file_put_contents($dir . '/old.rules.json', json_encode(['enabled' => false]));

        $res = new SchemeListCommand(new Paths($this->root))
            ->run(['domain' => 'example.ru'], new Context(static fn () => null));
        self::assertTrue($res->ok);
        self::assertCount(2, $res->data['rows']);
        $byName = [];
        foreach ($res->data['rows'] as $row) {
            $byName[$row['name']] = $row;
        }
        self::assertTrue($byName['www']['enabled']);
        self::assertTrue($byName['www']['has_editor']);
        self::assertFalse($byName['old']['enabled']);
        self::assertFalse($byName['old']['has_editor']);
    }

    public function testSchemeListRejectsInvalidDomain(): void
    {
        $res = new SchemeListCommand(new Paths($this->root))
            ->run(['domain' => 'a..ru'], new Context(static fn () => null));
        self::assertFalse($res->ok);
    }

    // === block:list ===

    public function testBlockListEmpty(): void
    {
        $res = new BlockListCommand(new Paths($this->root))
            ->run(['domain' => 'example.ru'], new Context(static fn () => null));
        self::assertTrue($res->ok);
        self::assertSame([], $res->data['rows']);
    }

    public function testBlockListWithBlocks(): void
    {
        $dir = $this->root . '/app/example.ru/.blocksglobal';
        mkdir($dir . '/top', 0o777, true);
        mkdir($dir . '/center', 0o777, true);
        file_put_contents($dir . '/top/top.php', '<?php');
        file_put_contents($dir . '/top/top.rules.json', json_encode(['enabled' => true, 'type' => 'visual']));
        file_put_contents($dir . '/center/center.php', '<?php');
        file_put_contents($dir . '/center/center.rules.json', json_encode(['enabled' => false, 'type' => 'mixed']));

        $res = new BlockListCommand(new Paths($this->root))
            ->run(['domain' => 'example.ru'], new Context(static fn () => null));
        self::assertTrue($res->ok);
        self::assertCount(2, $res->data['rows']);
        $byName = [];
        foreach ($res->data['rows'] as $row) {
            $byName[$row['name']] = $row;
        }
        self::assertSame('visual', $byName['top']['type']);
        self::assertTrue($byName['top']['enabled']);
        self::assertSame('mixed', $byName['center']['type']);
        self::assertFalse($byName['center']['enabled']);
    }

    // === filler:list ===

    public function testFillerListEmpty(): void
    {
        $res = new FillerListCommand(new Paths($this->root))
            ->run(['domain' => 'example.ru', 'allias' => 'www'], new Context(static fn () => null));
        self::assertTrue($res->ok);
        self::assertSame([], $res->data['rows']);
    }

    public function testFillerListWithFillers(): void
    {
        $dir = $this->root . '/app/example.ru/.blocksglobal';
        mkdir($dir . '/top', 0o777, true);
        mkdir($dir . '/footer', 0o777, true);
        file_put_contents($dir . '/top/top.php', '<?php');
        file_put_contents($dir . '/top/top_www.php', '<?php');
        file_put_contents($dir . '/footer/footer.php', '<?php');
        file_put_contents($dir . '/footer/footer_www.php', '<?php');
        file_put_contents($dir . '/footer/footer_doc.php', '<?php');

        // Включён только top для www
        $site = json_decode((string) file_get_contents($this->root . '/config/site.config'), true);
        $site['fillers'] = ['example.ru' => ['www' => ['top' => true, 'footer' => false]]];
        file_put_contents($this->root . '/config/site.config', json_encode($site));

        $res = new FillerListCommand(new Paths($this->root))
            ->run(['domain' => 'example.ru', 'allias' => 'www'], new Context(static fn () => null));
        self::assertTrue($res->ok);
        self::assertCount(2, $res->data['rows']);
        $byBlock = [];
        foreach ($res->data['rows'] as $row) {
            $byBlock[$row['block']] = $row;
        }
        self::assertTrue($byBlock['top']['enabled']);
        self::assertFalse($byBlock['footer']['enabled']);
        // doc-филлер не показывается (другой алиас)
        self::assertArrayNotHasKey('doc', $byBlock);
    }

    public function testFillerListRejectsInvalidAllias(): void
    {
        $res = new FillerListCommand(new Paths($this->root))
            ->run(['domain' => 'example.ru', 'allias' => 'Bad-Name'], new Context(static fn () => null));
        self::assertFalse($res->ok);
    }
}
