<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Ai\Orchestrator;

use Damoony\Ai\Orchestrator\ContextManager;
use Damoony\Core\Files\Paths;
use PHPUnit\Framework\TestCase;

final class ContextManagerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/orchestrator_' . bin2hex(random_bytes(4));
        mkdir($this->root . '/app', 0o775, true);
        mkdir($this->root . '/app/.sess_a1b2c3d4.damoony.ru', 0o775, true);
    }

    protected function tearDown(): void
    {
        $this->rmrf($this->root);
    }

    public function testInitCreatesContextFile(): void
    {
        $paths = new Paths($this->root);
        $cm = new ContextManager($paths, 'a1b2c3d4', 'damoony.ru');
        $cm->init('Test prompt', 'center');

        $ctx = $cm->read();
        self::assertSame('init', $ctx['meta']['status']);
        self::assertSame('center', $ctx['meta']['target_block']);
        self::assertSame('Test prompt', $ctx['input']['prompt']);
        self::assertNull($ctx['agent_outputs']['architect']);
    }

    public function testUpdateAgentOutput(): void
    {
        $paths = new Paths($this->root);
        $cm = new ContextManager($paths, 'a1b2c3d4', 'damoony.ru');
        $cm->init('Test', 'all');

        $cm->updateAgentOutput('architect', [
            'routing_tree' => [['page_name' => 'home', 'path' => '']],
        ]);

        $ctx = $cm->read();
        self::assertSame('home', $ctx['agent_outputs']['architect']['routing_tree'][0]['page_name']);
        self::assertSame('init', $ctx['meta']['status']);
    }

    public function testInitIdempotent(): void
    {
        $paths = new Paths($this->root);
        $cm = new ContextManager($paths, 'a1b2c3d4', 'damoony.ru');
        $cm->init('First', 'all');
        $cm->init('Second', 'center');

        $ctx = $cm->read();
        self::assertSame('First', $ctx['input']['prompt']);
    }

    public function testReadWithoutInitThrows(): void
    {
        $paths = new Paths($this->root);
        $cm = new ContextManager($paths, 'ffffffff', 'damoony.ru');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Context file not found');
        $cm->read();
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
