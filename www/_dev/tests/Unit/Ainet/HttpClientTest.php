<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Ainet;

use Damoony\Ainet\DomainWhitelist;
use Damoony\Ainet\HttpClient;
use Damoony\Ainet\HttpClientException;
use Damoony\Ainet\HttpTransportException;
use Damoony\Ainet\UrlGuard;
use Damoony\Ainet\UrlGuardException;
use PHPUnit\Framework\TestCase;

final class HttpClientTest extends TestCase
{
    private function guard(): UrlGuard
    {
        return new UrlGuard(new DomainWhitelist([
            'api.github.com',
            'api.openverse.engineering',
            'ru.wikipedia.org',
        ]));
    }

    private function client(FakeTransport $t, int $maxRedirects = 3): HttpClient
    {
        return new HttpClient($this->guard(), $t, maxRedirects: $maxRedirects);
    }

    public function testSuccessNoRedirect(): void
    {
        $t = new FakeTransport();
        $t->queueResponse([
            'status' => 200,
            'headers' => ['content-type' => 'application/json; charset=utf-8'],
            'body' => '{"ok":true}',
        ]);

        $r = $this->client($t)->get('https://api.github.com/repos');
        self::assertSame(200, $r->status);
        self::assertTrue($r->isSuccess());
        self::assertSame('application/json', $r->mime());
        self::assertSame('{"ok":true}', $r->body);
        self::assertSame(['https://api.github.com/repos'], $t->getRequestedUrls());
    }

    public function testRedirectAbsolute(): void
    {
        $t = new FakeTransport();
        $t->queueResponse([
            'status' => 301,
            'headers' => ['location' => 'https://ru.wikipedia.org/wiki/Кофе'],
        ]);
        $t->queueResponse([
            'status' => 200,
            'headers' => ['content-type' => 'text/html'],
            'body' => '<html>coffee</html>',
        ]);

        $r = $this->client($t)->get('https://api.github.com/redirect');
        self::assertSame(200, $r->status);
        self::assertSame(
            ['https://api.github.com/redirect', 'https://ru.wikipedia.org/wiki/Кофе'],
            $t->getRequestedUrls(),
        );
    }

    public function testRedirectRelativeRoot(): void
    {
        $t = new FakeTransport();
        $t->queueResponse([
            'status' => 302,
            'headers' => ['location' => '/new-path'],
        ]);
        $t->queueResponse(['status' => 200, 'headers' => [], 'body' => 'ok']);

        $this->client($t)->get('https://api.github.com/old?x=1');
        self::assertSame(
            ['https://api.github.com/old?x=1', 'https://api.github.com/new-path'],
            $t->getRequestedUrls(),
        );
    }

    public function testRedirectRelativeDir(): void
    {
        $t = new FakeTransport();
        $t->queueResponse([
            'status' => 302,
            'headers' => ['location' => 'sub/page'],
        ]);
        $t->queueResponse(['status' => 200, 'headers' => [], 'body' => '']);

        $this->client($t)->get('https://api.github.com/a/b/c');
        self::assertSame(
            ['https://api.github.com/a/b/c', 'https://api.github.com/a/b/sub/page'],
            $t->getRequestedUrls(),
        );
    }

    public function testRedirectProtocolRelative(): void
    {
        $t = new FakeTransport();
        $t->queueResponse([
            'status' => 302,
            'headers' => ['location' => '//ru.wikipedia.org/wiki/X'],
        ]);
        $t->queueResponse(['status' => 200, 'headers' => [], 'body' => '']);

        $this->client($t)->get('https://api.github.com/old');
        self::assertSame(
            ['https://api.github.com/old', 'https://ru.wikipedia.org/wiki/X'],
            $t->getRequestedUrls(),
        );
    }

    public function testRedirectToNotWhitelistedThrows(): void
    {
        $t = new FakeTransport();
        $t->queueResponse([
            'status' => 302,
            'headers' => ['location' => 'https://evil.com/x'],
        ]);

        try {
            $this->client($t)->get('https://api.github.com/old');
            self::fail('Ожидалось исключение');
        } catch (UrlGuardException $e) {
            self::assertStringContainsString('whitelist', strtolower($e->getMessage()));
        }
        self::assertSame(['https://api.github.com/old'], $t->getRequestedUrls(), 'Второй запрос не должен уйти');
    }

    public function testRedirectToPrivateIpThrows(): void
    {
        $t = new FakeTransport();
        $t->queueResponse([
            'status' => 302,
            'headers' => ['location' => 'http://127.0.0.1/admin'],
        ]);

        $this->expectException(UrlGuardException::class);
        $this->client($t)->get('https://api.github.com/old');
    }

    public function testTooManyRedirectsThrows(): void
    {
        $t = new FakeTransport();
        for ($i = 0; $i < 5; $i++) {
            $t->queueResponse([
                'status' => 302,
                'headers' => ['location' => 'https://api.github.com/loop' . $i],
            ]);
        }

        $this->expectException(HttpClientException::class);
        $this->expectExceptionMessageMatches('/too many redirects/i');
        $this->client($t, maxRedirects: 3)->get('https://api.github.com/start');
    }

    public function testTruncatedResponse(): void
    {
        $t = new FakeTransport();
        $t->queueResponse([
            'status' => 200,
            'headers' => ['content-type' => 'image/jpeg'],
            'body' => 'partial',
            'truncated' => true,
        ]);

        $r = $this->client($t)->get('https://api.github.com/img');
        self::assertTrue($r->truncated);
        self::assertSame('partial', $r->body);
    }

    public function testTransportError(): void
    {
        $t = new FakeTransport();
        $t->queueError('https://api.github.com/x', new HttpTransportException('timeout', 28));

        $this->expectException(HttpTransportException::class);
        $this->client($t)->get('https://api.github.com/x');
    }

    public function testHeadersLowercased(): void
    {
        $t = new FakeTransport();
        $t->queueResponse([
            'status' => 200,
            'headers' => ['content-type' => 'text/plain', 'x-custom' => 'value'],
            'body' => 'hello',
        ]);

        $r = $this->client($t)->get('https://api.github.com/x');
        self::assertSame('text/plain', $r->mime());
        self::assertSame('value', $r->headers['x-custom']);
    }

    public function testRedirectWithoutLocationStops(): void
    {
        $t = new FakeTransport();
        $t->queueResponse(['status' => 304, 'headers' => []]);

        $r = $this->client($t)->get('https://api.github.com/x');
        self::assertSame(304, $r->status);
        self::assertFalse($r->isSuccess());
        self::assertFalse($r->isRedirect(), '304 без Location — не редирект');
        self::assertCount(1, $t->getRequestedUrls());
    }

    public function testInvalidBaseUrlInRedirect(): void
    {
        $t = new FakeTransport();
        $t->queueResponse([
            'status' => 302,
            'headers' => ['location' => 'relative/path'],
        ]);

        // UrlGuard не пропустит относительный URL в get(), но проверим resolveRedirect напрямую через рефлексию
        // Проще: передадим "сырой" URL, который UrlGuard пропустит, но parse_url даст scheme+host.
        // Здесь тестируем, что относительный Location не падает на нормальном base.
        $t->queueResponse(['status' => 200, 'headers' => [], 'body' => '']);

        $r = $this->client($t)->get('https://api.github.com/x');
        self::assertSame(200, $r->status);
    }
}
