<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\UI\Algoritm;

use Damoony\Core\Container;
use Damoony\Core\Domain\DomainContext;
use Damoony\Core\Files\Paths;
use Damoony\Core\Http\Request;
use Damoony\Core\Http\Response;
use Damoony\Data\Auth\AuthInterface;
use Damoony\UI\Algoritm\AdminPage;
use Damoony\UI\Meta;
use Damoony\UI\Node;
use Damoony\UI\Region\RegionContext;
use Damoony\UI\Region\RegionRegistry;
use PHPUnit\Framework\TestCase;

final class AdminPageTest extends TestCase
{
    private function context(bool $authenticated, string $role = 'user'): RegionContext
    {
        $auth = new class($authenticated, $role) implements AuthInterface {
            public function __construct(private bool $auth, private string $role) {}
            public function isAuthenticated(): bool { return $this->auth; }
            public function user(): ?array { return $this->auth ? ['id' => 1, 'role' => $this->role] : null; }
            public function login(string $login, string $password): bool { return false; }
            public function logout(): void {}
        };
        
        $domain = new DomainContext('test.ru', 'test.ru', 'www', 'default', 'ru', true);
        return new RegionContext(
            new Request('GET', '/'),
            $domain,
            new Meta(),
            ['auth' => $auth]
        );
    }

    public function testUnauthenticatedRedirectsToLogin(): void
    {
        $page = new class ($this->context(false), new RegionRegistry()) extends AdminPage {
            protected function content(): Node|Response|null { return null; }
        };

        $result = $page->default();
        self::assertInstanceOf(Response::class, $result);
        self::assertSame(302, $result->status);
        self::assertStringContainsString('/login/', $result->headers['location']);
    }

    public function testAuthenticatedButNotAdminRedirects(): void
    {
        $page = new class ($this->context(true, 'user'), new RegionRegistry()) extends AdminPage {
            protected function content(): Node|Response|null { return null; }
        };

        $result = $page->default();
        self::assertInstanceOf(Response::class, $result);
        self::assertSame(302, $result->status);
    }

    public function testAdminReturnsNode(): void
    {
        $page = new class ($this->context(true, 'admin'), new RegionRegistry()) extends AdminPage {
            protected function content(): Node|Response|null { return new \Damoony\UI\Element('div'); }
        };

        $result = $page->default();
        self::assertInstanceOf(Node::class, $result);
    }

    public function testAdminNullContentReturnsNull(): void
    {
        $page = new class ($this->context(true, 'admin'), new RegionRegistry()) extends AdminPage {
            protected function content(): Node|Response|null { return null; }
        };

        $result = $page->default();
        self::assertNull($result);
    }
}
