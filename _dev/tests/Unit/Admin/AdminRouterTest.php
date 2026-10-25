<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Admin;

use Damoony\Admin\AdminRouter;
use Damoony\Admin\Controller\BaseController;
use Damoony\Admin\AuthInterface;
use Damoony\Admin\View;
use Damoony\Http\Request;
use Damoony\Http\Response;
use Damoony\Security\Csrf;
use Damoony\Session\SessionInterface;
use PHPUnit\Framework\TestCase;

final class AdminRouterTest extends TestCase
{
    private function stubController(string $responseBody): BaseController
    {
        $session = new class implements SessionInterface {
            public array $data = [];
            public function start(): void {}
            public function isStarted(): bool { return true; }
            public function get(string $key, mixed $default = null): mixed { return $this->data[$key] ?? $default; }
            public function set(string $key, mixed $value): void { $this->data[$key] = $value; }
            public function remove(string $key): void { unset($this->data[$key]); }
            public function regenerate(): void {}
            public function destroy(): void { $this->data = []; }
        };
        $auth = new class implements AuthInterface {
            public function attempt(string $login, string $password): bool { return false; }
            public function isLoggedIn(): bool { return true; }
            public function login(): string { return 'admin'; }
            public function logout(): void {}
        };
        $csrf = new Csrf($session);
        $view = new View();

        return new class($auth, $csrf, $view, $responseBody) extends BaseController {
            public function __construct(AuthInterface $a, Csrf $c, View $v, private string $body)
            {
                parent::__construct($a, $c, $v);
            }

            public function index(Request $request): Response
            {
                return Response::text($this->body);
            }

            public function custom(Request $request): Response
            {
                return Response::text('custom:' . $this->body);
            }
        };
    }

    public function testDefaultRouteIsDashboard(): void
    {
        $router = new AdminRouter();
        $router->register('dashboard', $this->stubController('dash'));

        $r = $router->dispatch(new Request('GET', '/admin/'));
        self::assertSame(200, $r->status);
        self::assertSame('dash', $r->body);
    }

    public function testExplicitAction(): void
    {
        $router = new AdminRouter();
        $router->register('foo', $this->stubController('x'));

        $r = $router->dispatch(new Request('GET', '/admin/', query: ['p' => 'foo:custom']));
        self::assertSame(200, $r->status);
        self::assertSame('custom:x', $r->body);
    }

    public function testUnknownResourceIs404(): void
    {
        $r = (new AdminRouter())->dispatch(new Request('GET', '/admin/', query: ['p' => 'ghost']));
        self::assertSame(404, $r->status);
    }

    public function testUnknownActionIs404(): void
    {
        $router = new AdminRouter();
        $router->register('foo', $this->stubController('x'));

        $r = $router->dispatch(new Request('GET', '/admin/', query: ['p' => 'foo:nope']));
        self::assertSame(404, $r->status);
    }
}