<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Data\Admin;

use Damoony\Data\Admin\Totp;
use PHPUnit\Framework\TestCase;

final class TotpTest extends TestCase
{
    public function testRfc6238Vector(): void
    {
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        self::assertSame('287082', Totp::code($secret, 59));
        self::assertTrue(Totp::verify($secret, '287082', 0, 59));
    }

    public function testGeneratedSecretIsBase32(): void
    {
        $secret = Totp::generateSecret();
        self::assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $secret);
    }

    public function testRejectsWrongCode(): void
    {
        $secret = Totp::generateSecret();
        self::assertFalse(Totp::verify($secret, '000000'));
    }
}
