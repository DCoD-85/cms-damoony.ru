<?php

declare(strict_types=1);

namespace Damoony\Tests\Scenarios;

use Damoony\Domain\DomainContext;
use Damoony\Http\Middleware\LanguageCookieMiddleware;
use Damoony\Http\Middleware\LanguagePathMiddleware;
use Damoony\Http\Middleware\SkinCookieMiddleware;
use Damoony\Http\Request;
use Damoony\Http\Response;
use Damoony\I18n\LanguageNegotiator;
use Damoony\Session\SessionInterface;
use PHPUnit\Framework\TestCase;

final class I18nSkinScenarioTest extends TestCase
{
    private function session(): SessionInterface
    {
        return new class () implements SessionInterface {
            /** @var array<string, mixed> */
            public array $data = [];

            public function start(): void
            {
            }

            public function isStarted(): bool
            {
                return true;
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
            }
        };
    }

    private function domain(string $lang = 'ru'): DomainContext
    {
        return new DomainContext('example.ru', 'example.ru', 'www', 'default', $lang, true);
    }

    public function testNegotiatorUrlPriority(): void
    {
        $n = new LanguageNegotiator();
        $req = new Request('GET', '/en/about', cookies: ['lang' => 'zh']);
        self::assertSame('en', $n->resolve($req, $this->domain('ru')));
    }

    public function testNegotiatorCookieFallback(): void
    {
        $n = new LanguageNegotiator();
        $req = new Request('GET', '/about', cookies: ['lang' => 'zh']);
        self::assertSame('zh', $n->resolve($req, $this->domain('ru')));
    }

    public function testNegotiatorDomainFallback(): void
    {
        $n = new LanguageNegotiator();
        $req = new Request('GET', '/about');
        self::assertSame('en', $n->resolve($req, $this->domain('en')));
    }

    public function testStripLangPrefix(): void
    {
        $n = new LanguageNegotiator();
        self::assertSame('/about', $n->stripLangPrefix('/en/about'));
        self::assertSame('/', $n->stripLangPrefix('/ru'));
        self::assertSame('/about', $n->stripLangPrefix('/about'));
    }

    public function testS16LanguagePathMiddlewareStripsPrefix(): void
    {
        $mw = new LanguagePathMiddleware(new LanguageNegotiator());
        $captured = null;
        $next = function (Request $r) use (&$captured): Response {
            $captured = $r->path;
            return Response::text('ok');
        };

        $r = $mw->process(new Request('GET', '/ru/about'), $next);
        self::assertSame(200, $r->status);
        self::assertSame('/about', $captured);
    }

    public function testS17LanguageCookie(): void
    {
        $s = $this->session();
        $mw = new LanguageCookieMiddleware($s);

        $req = new Request('GET', '/about', query: ['lang' => 'en']);
        $r = $mw->process($req, fn (): Response => Response::text('should not reach'));

        self::assertSame(307, $r->status);
        self::assertSame('/about', $r->headers['location']);
        self::assertSame('en', $s->get('lang'));
    }

    public function testS17InvalidLangPassesThrough(): void
    {
        $s = $this->session();
        $mw = new LanguageCookieMiddleware($s);

        $req = new Request('GET', '/about', query: ['lang' => 'xx-xx']);
        $r = $mw->process($req, fn (): Response => Response::text('ok'));

        self::assertSame(200, $r->status);
        self::assertNull($s->get('lang'));
    }

    public function testS18SkinCookie(): void
    {
        $mw = new SkinCookieMiddleware();
        $req = new Request('GET', '/', query: ['skin' => 'dark']);
        $r = $mw->process($req, fn (): Response => Response::text('should not reach'));

        self::assertSame(307, $r->status);
        self::assertSame('/', $r->headers['location']);
    }

    public function testS18InvalidSkinPassesThrough(): void
    {
        $mw = new SkinCookieMiddleware();
        $req = new Request('GET', '/', query: ['skin' => 'weird']);
        $r = $mw->process($req, fn (): Response => Response::text('ok'));

        self::assertSame(200, $r->status);
    }
}
