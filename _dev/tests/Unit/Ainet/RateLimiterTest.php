<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Ainet;

use Damoony\Ainet\RateLimiter;
use PHPUnit\Framework\TestCase;

final class RateLimiterTest extends TestCase
{
    private string $dir;
    private string $file;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/damoony_rl_' . bin2hex(random_bytes(4));
        mkdir($this->dir, 0o777, true);
        $this->file = $this->dir . '/ratelimit.json';
    }

    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            @unlink($this->file);
        }
        if (is_dir($this->dir)) {
            @rmdir($this->dir);
        }
    }

    public function testAllowUpToMax(): void
    {
        $rl = new RateLimiter($this->file, 5, 60);
        for ($i = 0; $i < 5; $i++) {
            self::assertTrue($rl->allow(), "Hit #{$i} should be allowed");
        }
        self::assertFalse($rl->allow(), '6th should be blocked');
    }

    public function testRemainingDecreases(): void
    {
        $rl = new RateLimiter($this->file, 10, 60);
        self::assertSame(10, $rl->remaining());
        $rl->allow();
        $rl->allow();
        $rl->allow();
        self::assertSame(7, $rl->remaining());
    }

    public function testStatsShape(): void
    {
        $rl = new RateLimiter($this->file, 10, 60);
        $rl->allow();
        $stats = $rl->stats();
        self::assertSame(1, $stats['used']);
        self::assertSame(10, $stats['max']);
        self::assertSame(60, $stats['window']);
        self::assertSame(9, $stats['remaining']);
        self::assertGreaterThan(0, $stats['reset_in']);
    }

    public function testResetClearsWindow(): void
    {
        $rl = new RateLimiter($this->file, 5, 60);
        for ($i = 0; $i < 5; $i++) {
            $rl->allow();
        }
        self::assertSame(0, $rl->remaining());
        $rl->reset();
        self::assertSame(5, $rl->remaining());
        self::assertTrue($rl->allow());
    }

    public function testWaitSecondsAfterBlock(): void
    {
        $rl = new RateLimiter($this->file, 2, 60);
        $rl->allow();
        $rl->allow();
        self::assertFalse($rl->allow());
        $wait = $rl->waitSeconds();
        self::assertGreaterThan(0, $wait);
        self::assertLessThanOrEqual(60, $wait);
    }

    public function testWindowRollsOver(): void
    {
        $rl = new RateLimiter($this->file, 2, 1);
        $rl->allow();
        $rl->allow();
        self::assertFalse($rl->allow());
        sleep(2);
        self::assertTrue($rl->allow(), 'After window expiry — allowed');
    }

    public function testCorruptFileIsReset(): void
    {
        file_put_contents($this->file, 'not-json-at-all');
        $rl = new RateLimiter($this->file, 3, 60);
        self::assertTrue($rl->allow(), 'Corrupt file → fail-open');
        self::assertSame(1, $rl->stats()['used']);
    }

    public function testTwoInstancesShareState(): void
    {
        $a = new RateLimiter($this->file, 4, 60);
        $b = new RateLimiter($this->file, 4, 60);
        self::assertTrue($a->allow());
        self::assertTrue($b->allow());
        self::assertSame(2, $a->stats()['used']);
        self::assertSame(2, $b->stats()['used']);
    }

    public function testRejectsInvalidMax(): void
    {
        $this->expectException(\RuntimeException::class);
        new RateLimiter($this->file, 0, 60);
    }
}
