<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Core\Module;

use Damoony\Core\Module\ModuleInterface;
use Damoony\Core\Module\ModuleRegistry;
use Damoony\Core\Module\PageModuleInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ModuleRegistryTest extends TestCase
{
    public function testMatchesMountAndRemainder(): void
    {
        $registry = new ModuleRegistry([$this->module('admin', '/app/admin')]);

        $exact = $registry->match('/app/admin');
        self::assertNotNull($exact);
        self::assertSame('admin', $exact->module->id());
        self::assertSame('/', $exact->remainder);

        $nested = $registry->match('/app/admin/superadmin/');
        self::assertNotNull($nested);
        self::assertSame('/superadmin/', $nested->remainder);

        self::assertNull($registry->match('/app/adminx'));
        self::assertNull($registry->match('/other'));
    }

    public function testLongestMountWins(): void
    {
        $registry = new ModuleRegistry([
            $this->module('root', '/app'),
            $this->module('admin', '/app/admin'),
        ]);

        $match = $registry->match('/app/admin/users/');
        self::assertNotNull($match);
        self::assertSame('admin', $match->module->id());
        self::assertSame('/users/', $match->remainder);
    }

    public function testRejectsUnsupportedApiVersion(): void
    {
        $module = new class () implements ModuleInterface {
            public function id(): string
            {
                return 'old';
            }
            public function apiVersion(): int
            {
                return 999;
            }
            public function mount(): ?string
            {
                return '/old';
            }
        };

        $this->expectException(RuntimeException::class);
        new ModuleRegistry([$module]);
    }

    public function testRejectsDuplicateIdAndMount(): void
    {
        $this->expectException(RuntimeException::class);
        new ModuleRegistry([
            $this->module('same', '/a'),
            $this->module('same', '/b'),
        ]);
    }

    public function testRejectsBadMount(): void
    {
        $this->expectException(RuntimeException::class);
        new ModuleRegistry([$this->module('bad', 'app/admin')]);
    }

    public function testAdminModuleMetadata(): void
    {
        $module = new \Damoony\Modules\Admin\AdminModule(\Damoony\Core\Config::fromArray([]));

        self::assertSame('admin', $module->id());
        self::assertSame(ModuleInterface::API_VERSION, $module->apiVersion());
        self::assertSame('/app/admin', $module->mount());
        self::assertSame('app/.admin', $module->pagesRoot());
        self::assertSame('Damoony\\Admin\\Pages', $module->pagesNamespace());
        self::assertInstanceOf(\Damoony\Core\Security\AccessPolicyInterface::class, $module->accessPolicy());
    }

    private function module(string $id, string $mount): ModuleInterface
    {
        return new class ($id, $mount) implements ModuleInterface, PageModuleInterface {
            public function __construct(
                private readonly string $id,
                private readonly string $mount,
            ) {
            }

            public function id(): string
            {
                return $this->id;
            }
            public function apiVersion(): int
            {
                return ModuleInterface::API_VERSION;
            }
            public function mount(): ?string
            {
                return $this->mount;
            }
            public function pagesRoot(): string
            {
                return 'app/.x';
            }
            public function pagesNamespace(): string
            {
                return 'X';
            }
        };
    }
}
