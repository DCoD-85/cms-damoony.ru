<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Ainet\ImageSearch;

use Damoony\Ainet\DomainWhitelist;
use Damoony\Ainet\HttpClient;
use Damoony\Ainet\ImageSearch\OpenverseProvider;
use Damoony\Ainet\ImageSearch\ProviderException;
use Damoony\Ainet\UrlGuard;
use Damoony\Tests\Unit\Ainet\FakeTransport;
use PHPUnit\Framework\TestCase;

final class OpenverseProviderTest extends TestCase
{
    private function http(FakeTransport $t): HttpClient
    {
        $guard = new UrlGuard(new DomainWhitelist([
            'api.openverse.org',
            'api.openverse.engineering',
        ]));

        return new HttpClient($guard, $t);
    }

    public function testNameAndRequiresKey(): void
    {
        $p = new OpenverseProvider($this->http(new FakeTransport()));
        self::assertSame('openverse', $p->name());
        self::assertFalse($p->requiresKey());
    }

    public function testEmptyQueryThrows(): void
    {
        $p = new OpenverseProvider($this->http(new FakeTransport()));
        $this->expectException(ProviderException::class);
        $p->search('', 5);
    }

    public function testTooLongQueryThrows(): void
    {
        $p = new OpenverseProvider($this->http(new FakeTransport()));
        $this->expectException(ProviderException::class);
        $p->search(str_repeat('a', 101), 5);
    }

    public function testSuccessMapsResults(): void
    {
        $t = new FakeTransport();
        $body = json_encode([
            'result_count' => 2,
            'results' => [
                [
                    'id' => 'abc',
                    'url' => 'https://upload.wikimedia.org/coffee1.jpg',
                    'thumbnail' => 'https://upload.wikimedia.org/thumb1.jpg',
                    'width' => 1200,
                    'height' => 800,
                    'license' => 'cc0',
                    'creator' => 'Alice',
                    'title' => 'Coffee cup',
                ],
                [
                    'id' => 'def',
                    'url' => 'https://upload.wikimedia.org/coffee2.jpg',
                    'thumbnail' => 'https://upload.wikimedia.org/thumb2.jpg',
                    'width' => 800,
                    'height' => 600,
                    'license' => 'by',
                    'creator' => 'Bob',
                    'title' => 'Latte',
                ],
            ],
        ]);
        $t->queueResponse([
            'status' => 200,
            'headers' => ['content-type' => 'application/json'],
            'body' => (string) $body,
        ]);

        $p = new OpenverseProvider($this->http($t));
        $results = $p->search('coffee', 5);

        self::assertCount(2, $results);
        self::assertSame('https://upload.wikimedia.org/coffee1.jpg', $results[0]->url);
        self::assertSame('cc0', $results[0]->license);
        self::assertSame('Alice', $results[0]->author);
        self::assertSame('openverse', $results[0]->source);
        self::assertSame(1200, $results[0]->width);
        self::assertSame(800, $results[0]->height);
    }

    public function testCountTruncates(): void
    {
        $t = new FakeTransport();
        $results = [];
        for ($i = 0; $i < 10; $i++) {
            $results[] = [
                'url' => "https://upload.wikimedia.org/img{$i}.jpg",
                'thumbnail' => "https://upload.wikimedia.org/t{$i}.jpg",
                'width' => 500,
                'height' => 500,
                'license' => 'cc0',
                'creator' => 'X',
            ];
        }
        $t->queueResponse([
            'status' => 200,
            'headers' => ['content-type' => 'application/json'],
            'body' => (string) json_encode(['results' => $results]),
        ]);

        $p = new OpenverseProvider($this->http($t));
        self::assertCount(3, $p->search('x', 3));
    }

    public function testHttpErrorThrows(): void
    {
        $t = new FakeTransport();
        $t->queueResponse(['status' => 503, 'headers' => [], 'body' => 'Service Unavailable']);

        $p = new OpenverseProvider($this->http($t));
        $this->expectException(ProviderException::class);
        $this->expectExceptionMessageMatches('/HTTP 503/');
        $p->search('coffee', 5);
    }

    public function testInvalidJsonThrows(): void
    {
        $t = new FakeTransport();
        $t->queueResponse([
            'status' => 200,
            'headers' => ['content-type' => 'application/json'],
            'body' => 'not-json',
        ]);

        $p = new OpenverseProvider($this->http($t));
        $this->expectException(ProviderException::class);
        $this->expectExceptionMessageMatches('/invalid JSON/');
        $p->search('coffee', 5);
    }

    public function testMissingResultsReturnsEmpty(): void
    {
        $t = new FakeTransport();
        $t->queueResponse([
            'status' => 200,
            'headers' => ['content-type' => 'application/json'],
            'body' => '{"result_count":0,"results":[]}',
        ]);

        $p = new OpenverseProvider($this->http($t));
        self::assertSame([], $p->search('zzz', 5));
    }

    public function testSkipsRowsWithoutUrl(): void
    {
        $t = new FakeTransport();
        $t->queueResponse([
            'status' => 200,
            'headers' => ['content-type' => 'application/json'],
            'body' => (string) json_encode([
                'results' => [
                    ['width' => 100, 'height' => 100], // нет url
                    ['url' => 'https://upload.wikimedia.org/ok.jpg', 'width' => 100, 'height' => 100],
                ],
            ]),
        ]);

        $p = new OpenverseProvider($this->http($t));
        $r = $p->search('x', 5);
        self::assertCount(1, $r);
        self::assertSame('https://upload.wikimedia.org/ok.jpg', $r[0]->url);
    }
}
