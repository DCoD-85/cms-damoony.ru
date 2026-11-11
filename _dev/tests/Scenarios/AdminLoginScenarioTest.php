<?php

declare(strict_types=1);

namespace Damoony\Tests\Scenarios;

use Damoony\Admin\Auth;
use Damoony\Core\App\FrontController;
use Damoony\Core\App\PageHandler;
use Damoony\Core\Config;
use Damoony\Core\Container;
use Damoony\Core\Domain\ConfigDomainResolver;
use Damoony\Core\Domain\DomainContext;
use Damoony\Core\Domain\DomainResolverInterface;
use Damoony\Core\Files\Paths;
use Damoony\Core\Http\Request;
use Damoony\Core\Http\Response;
use Damoony\Core\Routing\FileRouter;
use Damoony\Core\Routing\RouterInterface;
use Damoony\Core\Routing\RulesMatcher;
use Damoony\Core\Security\Csrf;
use Damoony\Core\Session\SessionInterface;
use PHPUnit\Framework\TestCase;

final class AdminLoginScenarioTest extends TestCase
{
    private string $root;
    private FakeSession $session;
    private Auth $auth;
    private Config $adminConfig;
    private Config $site;
    private FrontController $fc;
    private DomainContext $domainCtx;

    protected function setUp(): void
    {
        \Damoony\Core\Error\ErrorPage::bootstrap(
            fallback: 'ru',
            langDir: dirname(__DIR__, 3) . '/lang',
        );

        $this->root = sys_get_temp_dir() . '/damoony_admin_' . bin2hex(random_bytes(4));
        mkdir($this->root . '/config', 0o777, true);
        mkdir($this->root . '/lang', 0o777, true);

        file_put_contents($this->root . '/config/admin.config', json_encode([
            'login' => 'admin',
            'password_hash' => password_hash('test123', PASSWORD_BCRYPT),
            'domains_langs' => ['ru', 'en', 'zh'],
            'default_lang' => 'ru',
            'default_role' => 'superadmin',
        ], JSON_PRETTY_PRINT));

        file_put_contents($this->root . '/config/site.config', json_encode([
            'domains_real' => ['example.ru' => 'example.ru'],
            'domains_available' => ['example.ru' => true],
            'alliases_real' => ['example.ru' => ['www' => 'www']],
            'domains_namespace' => ['example.ru' => 'example'],
        ], JSON_PRETTY_PRINT));

        file_put_contents($this->root . '/lang/ru.php', "<?php\nreturn ['admin.start.placeholder' => 'Стартовая страница'];\n");
        file_put_contents($this->root . '/lang/en.php', "<?php\nreturn ['admin.start.placeholder' => 'Start page'];\n");
        file_put_contents($this->root . '/lang/zh.php', "<?php\nreturn ['admin.start.placeholder' => '起始页'];\n");

        $paths = new Paths($this->root);
        $cmd = new \Damoony\Cli\Commands\AdminNewCommand($paths);
        $res = $cmd->run(['role' => 'superadmin'], new \Damoony\Cli\Context(static fn () => null));
        self::assertTrue($res->ok, implode('; ', $res->errors));

        $this->adminConfig = Config::fromFile($this->root . '/config/admin.config');
        $this->site = Config::fromFile($this->root . '/config/site.config');
        $this->session = new FakeSession();
        $this->auth = new Auth(
            $this->adminConfig,
            $this->session,
            $this->root . '/var/admin_fails.json',
            $this->root . '/var/admin_logs.json',
            '127.0.0.1',
        );

        $c = new Container();
        $c->setInstance(Paths::class, $paths);
        $c->setInstance('site', $this->site);
        $c->setInstance('admin_config', $this->adminConfig);
        $c->setInstance(SessionInterface::class, $this->session);
        $c->setInstance(Csrf::class, new Csrf($this->session));
        $c->setInstance(DomainResolverInterface::class, new ConfigDomainResolver($this->site));
        $c->setInstance(RouterInterface::class, new FileRouter($this->root, new RulesMatcher()));

        $resolver = $c->get(DomainResolverInterface::class);
        assert($resolver instanceof DomainResolverInterface);
        $this->domainCtx = $resolver->resolve('example.ru');

        $this->fc = new FrontController($c, new PageHandler($this->root, $resolver));
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
        foreach (scandir($dir) ?: [] as $i) {
            if ($i === '.' || $i === '..') {
                continue;
            }
            $p = $dir . '/' . $i;
            is_dir($p) ? $this->rmrf($p) : @unlink($p);
        }
        @rmdir($dir);
    }

    private function handle(Request $r): Response
    {
        return $this->fc->handle(
            $r,
            sessionId: null,
            aliasOverride: null,
            adminRole: 'superadmin',
            adminDomainContext: $this->domainCtx,
            authOverride: $this->auth,
        );
    }

    public function testUnauthRootRedirectsToLogin(): void
    {
        $r = $this->handle(new Request('GET', '/'));
        self::assertSame(302, $r->status);
        self::assertSame('/admin/ru/login', $r->headers['location'] ?? null);
    }

    public function testUnauthLangRootRedirectsToLogin(): void
    {
        $r = $this->handle(new Request('GET', '/ru/'));
        self::assertSame(302, $r->status);
        self::assertSame('/admin/ru/login', $r->headers['location'] ?? null);
    }

    public function testLoginPageRenders(): void
    {
        $r = $this->handle(new Request('GET', '/ru/login'));
        self::assertSame(200, $r->status);
        self::assertStringContainsString('<form', $r->body);
        self::assertStringContainsString('name="login"', $r->body);
        self::assertStringContainsString('name="password"', $r->body);
    }

    public function testLoginWrongPassword(): void
    {
        $r = $this->handle(new Request(
            method: 'POST',
            path: '/ru/login',
            post: ['login' => 'admin', 'password' => 'wrong'],
        ));
        self::assertSame(200, $r->status);
        self::assertStringContainsString('Неверный логин или пароль', $r->body);
        self::assertFalse($this->auth->isLoggedIn());
    }

    public function testLoginSuccess(): void
    {
        $r = $this->handle(new Request(
            method: 'POST',
            path: '/ru/login',
            post: ['login' => 'admin', 'password' => 'test123'],
        ));
        self::assertSame(302, $r->status);
        self::assertSame('/admin/ru/', $r->headers['location'] ?? null);
        self::assertTrue($this->auth->isLoggedIn());
    }

    public function testAuthenticatedRuRootShowsStartPage(): void
    {
        $this->auth->attempt('admin', 'test123');
        $r = $this->handle(new Request('GET', '/ru/'));
        self::assertSame(200, $r->status);
        self::assertStringContainsString('Стартовая страница', $r->body);
    }

    public function testAuthenticatedEnRootShowsStartPage(): void
    {
        $this->auth->attempt('admin', 'test123');
        $r = $this->handle(new Request('GET', '/en/'));
        self::assertSame(200, $r->status);
        self::assertStringContainsString('Start page', $r->body);
    }

    public function testAuthenticatedZhRootShowsStartPage(): void
    {
        $this->auth->attempt('admin', 'test123');
        $r = $this->handle(new Request('GET', '/zh/'));
        self::assertSame(200, $r->status);
        self::assertStringContainsString('起始页', $r->body);
    }

    public function testLogoutClearsSession(): void
    {
        $this->auth->attempt('admin', 'test123');
        self::assertTrue($this->auth->isLoggedIn());

        $r = $this->handle(new Request('GET', '/ru/logout'));
        self::assertSame(302, $r->status);
        self::assertSame('/admin/ru/login', $r->headers['location'] ?? null);
        self::assertFalse($this->auth->isLoggedIn());
    }

    public function testUnknownAdminPathReturns404(): void
    {
        $r = $this->handle(new Request('GET', '/ru/nothing'));
        self::assertSame(404, $r->status);
    }
}

final class FakeSession implements SessionInterface
{
    /** @var array<string, mixed> */
    private array $data = [];
    private bool $started = false;

    public function start(): void
    {
        $this->started = true;
    }

    public function isStarted(): bool
    {
        return $this->started;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    public function regenerate(): void
    {
    }

    public function destroy(): void
    {
        $this->data = [];
        $this->started = false;
    }
}
