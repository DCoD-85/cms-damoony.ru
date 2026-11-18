<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Modules\AuthAdmin;

use Damoony\Core\Config;
use Damoony\Modules\AuthAdmin\AdminAccess;
use PHPUnit\Framework\TestCase;

final class AdminAccessTest extends TestCase
{
    public function testDefaultAllowsOnlyLoopback(): void
    {
        $access = new AdminAccess([]);

        self::assertTrue($access->allows('127.0.0.1'));
        self::assertTrue($access->allows('::1'));
        self::assertFalse($access->allows('203.0.113.10'));
        self::assertFalse($access->allows('not-an-ip'));
    }

    public function testConfiguredIpsReplaceDefault(): void
    {
        $access = new AdminAccess(['203.0.113.10', '198.51.100.7']);

        self::assertTrue($access->allows('203.0.113.10'));
        self::assertTrue($access->allows('198.51.100.7'));
        self::assertFalse($access->allows('127.0.0.1'));
    }

    public function testFromConfig(): void
    {
        $config = Config::fromArray(['security' => ['admin_ips' => ['203.0.113.10']]]);
        $access = AdminAccess::fromConfig($config);

        self::assertSame(['203.0.113.10'], $access->allowedIps());
        self::assertTrue($access->allows('203.0.113.10'));
        self::assertFalse($access->allows('192.0.2.1'));
    }

    public function testInvalidValuesAreIgnored(): void
    {
        $access = new AdminAccess(['bad', '', ' 203.0.113.10 ']);

        self::assertSame(['203.0.113.10'], $access->allowedIps());
    }
}
