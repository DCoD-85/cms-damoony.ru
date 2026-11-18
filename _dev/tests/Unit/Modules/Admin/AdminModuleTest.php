<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Modules\Admin;

use Damoony\Core\Config;
use Damoony\Modules\Admin\AdminModule;
use PHPUnit\Framework\TestCase;

final class AdminModuleTest extends TestCase
{
    private AdminModule $module;

    protected function setUp(): void
    {
        $this->module = new AdminModule(Config::fromArray([]));
    }

    public function testResolvesSuperadminRoot(): void
    {
        $route = $this->module->resolvePage('/superadmin/');

        self::assertNotNull($route);
        self::assertSame('app/.admin/superadmin', $route->pagesRoot);
        self::assertSame('Damoony\\Admin\\Pages\\superadmin', $route->namespace);
        self::assertSame('/', $route->path);
        self::assertSame('superadmin', $route->role);
    }

    public function testResolvesNestedPath(): void
    {
        $route = $this->module->resolvePage('/domainadmin/users/');

        self::assertNotNull($route);
        self::assertSame('app/.admin/domainadmin', $route->pagesRoot);
        self::assertSame('/users', $route->path);
        self::assertSame('domainadmin', $route->role);
    }

    public function testRejectsUnknownOrEmptyRole(): void
    {
        self::assertNull($this->module->resolvePage('/'));
        self::assertNull($this->module->resolvePage('/nobody/'));
    }

    public function testEntryPointForIpOnly(): void
    {
        self::assertSame('/app/admin/auth/', $this->module->entryPathForHost('203.0.113.10'));
        self::assertNull($this->module->entryPathForHost('damoony.ru'));
    }
}
