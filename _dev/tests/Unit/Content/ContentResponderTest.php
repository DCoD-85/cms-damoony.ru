<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Content;

use Damoony\Auth\AuthInterface;
use Damoony\Content\ContentResponder;
use Damoony\Files\Paths;
use PHPUnit\Framework\TestCase;

final class ContentResponderTest extends TestCase
{
    private string $root;
    private Paths $paths;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_content_resp_' . bin2hex(random_bytes(4));
        mkdir($this->root . '/config', 0o777, true);
        file_put_contents($this->root . '/config/site.config', json_encode([
            'domains_real' => ['example.ru' => 'example.ru'],
            'alliases_real' => ['example.ru' => ['www' => 'www']],
        ]));

        // Страницы: about (public), cabinet (auth), __root__ (public)
        $base = $this->root . '/app/example.ru/www/algoritm';
        mkdir($base . '/about', 0o777, true);
        mkdir($base . '/cabinet', 0o777, true);
        file_put_contents($base . '/algoritm.php', '<?php');
        file_put_contents($base . '/algoritm.rules.json', json_encode(['auth' => false]));
        file_put_contents($base . '/about/algoritm_about.php', '<?php');
        file_put_contents($base . '/about/algoritm_about.rules.json', json_encode(['auth' => false]));
        file_put_contents($base . '/cabinet/algoritm_cabinet.php', '<?php');
        file_put_contents($base . '/cabinet/algoritm_cabinet.rules.json', json_encode(['auth' => true]));

        $this->paths = new Paths($this->root);
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
        foreach (scandir($dir) ?: [] as $e) {
            if ($e === '.' || $e === '..') {
                continue;
            }
            $p = $dir . '/' . $e;
            is_dir($p) ? $this->rmrf($p) : @unlink($p);
        }
        @rmdir($dir);
    }

    // === parse ===

    public function testParseValid(): void
    {
        $req = ContentResponder::parse('/content/example.ru/www/coffee/abcdef0123456789.png');
        self::assertNotNull($req);
        self::assertSame('example.ru', $req->domain);
        self::assertSame('www', $req->allias);
        self::assertSame('coffee', $req->category);
        self::assertSame('abcdef0123456789', $req->hash);
        self::assertSame('png', $req->ext);
    }

    public function testParseWithQueryString(): void
    {
        $req = ContentResponder::parse('/content/example.ru/www/c/0123456789abcdef.jpg?v=2');
        self::assertNotNull($req);
        self::assertSame('jpg', $req->ext);
    }

    public function testParseUppercaseExtLowered(): void
    {
        $req = ContentResponder::parse('/content/example.ru/www/c/0123456789abcdef.JPG');
        self::assertNotNull($req);
        self::assertSame('jpg', $req->ext);
    }

    public function testParseRejectsBadPath(): void
    {
        self::assertNull(ContentResponder::parse('/not-content/x'));
        self::assertNull(ContentResponder::parse('/content/'));
        self::assertNull(ContentResponder::parse('/content/example.ru/www/coffee/not-hex.png'));
        self::assertNull(ContentResponder::parse('/content/../www/c/x.png')); // path traversal
    }

    // === hostAllowed ===

    public function testHostMatch(): void
    {
        self::assertTrue(ContentResponder::hostAllowed('example.ru', 'example.ru'));
        self::assertTrue(ContentResponder::hostAllowed('example.ru', 'EXAMPLE.RU'));
        self::assertTrue(ContentResponder::hostAllowed('example.ru', 'example.ru:8080'));
        self::assertTrue(ContentResponder::hostAllowed('example.ru', 'example.ru.'));
    }

    public function testHostMismatch(): void
    {
        self::assertFalse(ContentResponder::hostAllowed('example.ru', 'evil.com'));
        self::assertFalse(ContentResponder::hostAllowed('example.ru', ''));
        self::assertFalse(ContentResponder::hostAllowed('example.ru', 'sub.example.ru'));
    }

    // === resolveMime ===

    public function testResolveMimeFromMeta(): void
    {
        self::assertSame('image/jpeg', ContentResponder::resolveMime('png', ['mime' => 'image/jpeg']));
    }

    public function testResolveMimeFromExt(): void
    {
        self::assertSame('image/png', ContentResponder::resolveMime('png', []));
        self::assertSame('image/jpeg', ContentResponder::resolveMime('jpg', []));
        self::assertSame('image/svg+xml', ContentResponder::resolveMime('svg', []));
        self::assertSame('application/octet-stream', ContentResponder::resolveMime('xyz', []));
    }

    // === requiresAuth ===

    public function testPublicImageNoRefs(): void
    {
        $req = ContentResponder::parse('/content/example.ru/www/coffee/0123456789abcdef.png');
        self::assertNotNull($req);
        self::assertFalse(ContentResponder::requiresAuth($req, ['page_refs' => []], $this->paths));
    }

    public function testPublicImageRefPublicPage(): void
    {
        $req = ContentResponder::parse('/content/example.ru/www/coffee/0123456789abcdef.png');
        self::assertNotNull($req);
        self::assertFalse(ContentResponder::requiresAuth($req, ['page_refs' => ['about']], $this->paths));
    }

    public function testAuthImageRefProtectedPage(): void
    {
        $req = ContentResponder::parse('/content/example.ru/www/coffee/0123456789abcdef.png');
        self::assertNotNull($req);
        self::assertTrue(ContentResponder::requiresAuth($req, ['page_refs' => ['cabinet']], $this->paths));
    }

    public function testAuthIfAnyPageRequiresAuth(): void
    {
        $req = ContentResponder::parse('/content/example.ru/www/coffee/0123456789abcdef.png');
        self::assertNotNull($req);
        // about (public) + cabinet (auth) → auth обязателен
        self::assertTrue(ContentResponder::requiresAuth($req, ['page_refs' => ['about', 'cabinet']], $this->paths));
    }

    public function testRootPageRef(): void
    {
        $req = ContentResponder::parse('/content/example.ru/www/coffee/0123456789abcdef.png');
        self::assertNotNull($req);
        // __root__ → algoritm.rules.json (auth: false)
        self::assertFalse(ContentResponder::requiresAuth($req, ['page_refs' => ['__root__']], $this->paths));
    }

    public function testMissingRulesFileIgnored(): void
    {
        $req = ContentResponder::parse('/content/example.ru/www/coffee/0123456789abcdef.png');
        self::assertNotNull($req);
        // Страница 'ghost' не существует — пропускаем
        self::assertFalse(ContentResponder::requiresAuth($req, ['page_refs' => ['ghost']], $this->paths));
    }

    // === userCanAccess ===

    public function testUserCanAccessPublic(): void
    {
        self::assertTrue(ContentResponder::userCanAccess(false, null));
    }

    public function testUserCannotAccessAuthNoAuth(): void
    {
        self::assertFalse(ContentResponder::userCanAccess(true, null));
    }

    public function testUserCannotAccessAuthGuest(): void
    {
        $auth = $this->mockAuth(false);
        self::assertFalse(ContentResponder::userCanAccess(true, $auth));
    }

    public function testUserCanAccessAuthLoggedIn(): void
    {
        $auth = $this->mockAuth(true);
        self::assertTrue(ContentResponder::userCanAccess(true, $auth));
    }

    // === loginRedirectUrl ===

    public function testLoginRedirectUrl(): void
    {
        $url = ContentResponder::loginRedirectUrl('/content/example.ru/www/c/0123456789abcdef.png');
        self::assertSame(
            '/login?return=%2Fcontent%2Fexample.ru%2Fwww%2Fc%2F0123456789abcdef.png',
            $url,
        );
    }

    private function mockAuth(bool $authenticated): AuthInterface
    {
        return new class ($authenticated) implements AuthInterface {
            public function __construct(private bool $authed)
            {
            }

            public function isAuthenticated(): bool
            {
                return $this->authed;
            }

            public function user(): ?array
            {
                return null;
            }

            public function login(string $login, string $password): bool
            {
                return false;
            }

            public function logout(): void
            {
            }
        };
    }
}