<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\UI\Algoritm;

use Damoony\Admin\AuthInterface as AdminAuthInterface;
use Damoony\Core\Config;
use Damoony\Core\Domain\DomainContext;
use Damoony\Core\Http\Request;
use Damoony\Core\Http\Response;
use Damoony\UI\Algoritm\AdminPage;
use Damoony\UI\Builder;
use Damoony\UI\Node;
use Damoony\UI\Region\RegionContext;
use Damoony\UI\Region\RegionRegistry;
use PHPUnit\Framework\TestCase;

final class AdminPageTest extends TestCase
{
    public function testUnauthenticatedRedirects(): void
    {
        $page = new class ($this->context(false), new RegionRegistry()) extends AdminPage {
            protected function content(): Node|Response|null
            {
                return Builder::div('x')->content('secret');
            }
        };

        $response = $page->default();

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(302, $response->status);
        self::assertSame('/admin/ru/login', $response->headers['location']);
    }

    public function testAuthenticatedReturnsNode(): void
    {
        $page = new class ($this->context(true), new RegionRegistry()) extends AdminPage {
            protected function content(): Node|Response|null
            {
                return Builder::div('x')->content('secret');
            }
        };

        $result = $page->default();

        self::assertInstanceOf(Node::class, $result);
        self::assertStringContainsString('secret', $result->release());
    }

    public function testNullContentReturnsNull(): void
    {
        $page = new class ($this->context(true), new RegionRegistry()) extends AdminPage {
            protected function content(): Node|Response|null
            {
                return null;
            }
        };

        $result = $page->default();
        self::assertNull($result);
    }

    public function testUnauthenticatedWithoutConfigReturns500(): void
    {
        $page = new class ($this->contextNoConfig(), new RegionRegistry()) extends AdminPage {
            protected function content(): Node|Response|null
            {
                return Builder::div('x')->content('secret');
            }
        };

        $response = $page->default();

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(500, $response->status);
    }

    private function contextNoConfig(): RegionContext
    {
        $auth = new class () implements AdminAuthInterface {
            public function attempt(string $login, string $password): bool
            {
                return false;
            }
            public function isLoggedIn(): bool
            {
                return false;
            }
            public function login(): string
            {
                return '';
            }
            public function logout(): void
            {
            }
        };

        $request = Request::fromGlobals();
        $domain = new DomainContext(
            domain: 'example.ru',
            real: 'example.ru',
            allias: 'www',
            skin: 'default',
            language: 'ru',
            available: true,
        );

        return new RegionContext($request, $domain, null, ['auth' => $auth]);
    }

    private function context(bool $authenticated): RegionContext
    {
        $auth = new class ($authenticated) implements AdminAuthInterface {
            public function __construct(private readonly bool $ok)
            {
            }

            public function attempt(string $login, string $password): bool
            {
                return false;
            }

            public function isLoggedIn(): bool
            {
                return $this->ok;
            }

            public function login(): string
            {
                return '';
            }

            public function logout(): void
            {
            }
        };

        $request = Request::fromGlobals();
        $domain = new DomainContext(
            domain: 'example.ru',
            real: 'example.ru',
            allias: 'www',
            skin: 'default',
            language: 'ru',
            available: true,
        );

        $adminConfig = Config::fromArray([
            'default_lang' => 'ru',
            'default_role' => 'superadmin',
        ]);

        return new RegionContext($request, $domain, null, [
            'auth' => $auth,
            'admin_config' => $adminConfig,
        ]);
    }
}
