<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Ainet\ImageSearch;

use Damoony\Ainet\DomainWhitelist;
use Damoony\Ainet\HttpClient;
use Damoony\Ainet\ImageSearch\ProviderException;
use Damoony\Ainet\ImageSearch\WikimediaProvider;
use Damoony\Ainet\UrlGuard;
use Damoony\Tests\Unit\Ainet\FakeTransport;
use PHPUnit\Framework\TestCase;

final class WikimediaProviderTest extends TestCase
{
    private function http(FakeTransport $t): HttpClient
    {
        $guard = new UrlGuard(new DomainWhitelist([
            'commons.wikimedia.org',
        ]));

        return new HttpClient($guard, $t);
    }

    public function testNameAndRequiresKey(): void
    {
        $p = new WikimediaProvider($this->http(new FakeTransport()));
        self::assertSame('wikimedia', $p->name());
        self::assertFalse($p->requiresKey());
    }

    public function testSuccessMapsResults(): void
    {
        $t = new FakeTransport();
        $body = json_encode([
            'query' => [
                'pages' => [
                    '12345' => [
                        'pageid' => 12345,
                        'title' => 'File:Coffee_cup.jpg',
                        'imageinfo' => [[
                            'url' => 'https://upload.wikimedia.org/coffee.jpg',
                            'thumburl' => 'https://upload.wikimedia.org/coffee_thumb.jpg',
                            'width' => 2000,
                            'height' => 1500,
                            'extmetadata' => [
                                'LicenseShortName' => ['value' => 'CC BY-SA 4.0'],
                                'Artist' => ['value' => '<a href="#">Alice</a>'],
                            ],
                        ]],
                    ],
                    '67890' => [
                        'pageid' => 67890,
                        'title' => 'File:Latte.jpg',
                        'imageinfo' => [[
                            'url' => 'https://upload.wikimedia.org/latte.jpg',
                            'thumburl' => 'https://upload.wikimedia.org/latte_thumb.jpg',
                            'width' => 800,
                            'height' => 600,
                            'extmetadata' => [
                                'LicenseShortName' => ['value' => 'Public domain'],
                            ],
                        ]],
                    ],
                ],
            ],
        ]);
        $t->queueResponse([
            'status' => 200,
            'headers' => ['content-type' => 'application/json'],
            'body' => (string) $body,
        ]);

        $p = new WikimediaProvider($this->http($t));
        $results = $p->search('coffee', 5);

        self::assertCount(2, $results);
        self::assertSame('https://upload.wikimedia.org/coffee.jpg', $results[0]->url);
        self::assertSame('https://upload.wikimedia.org/coffee_thumb.jpg', $results[0]->previewUrl);
        self::assertSame('CC BY-SA 4.0', $results[0]->license);
        self::assertSame('Alice', $results[0]->author, 'HTML-теги из Artist должны быть удалены');
        self::assertSame('wikimedia', $results[0]->source);
        self::assertSame('File:Coffee_cup.jpg', $results[0]->title);
    }

    public function testEmptyQueryThrows(): void
    {
        $p = new WikimediaProvider($this->http(new FakeTransport()));
        $this->expectException(ProviderException::class);
        $p->search('', 5);
    }

    public function testHttpErrorThrows(): void
    {
        $t = new FakeTransport();
        $t->queueResponse(['status' => 500, 'headers' => [], 'body' => 'error']);

        $p = new WikimediaProvider($this->http($t));
        $this->expectException(ProviderException::class);
        $p->search('coffee', 5);
    }

    public function testEmptyQueryResponseReturnsEmpty(): void
    {
        $t = new FakeTransport();
        $t->queueResponse([
            'status' => 200,
            'headers' => ['content-type' => 'application/json'],
            'body' => '{"batchcomplete":""}',
        ]);

        $p = new WikimediaProvider($this->http($t));
        self::assertSame([], $p->search('zzzzz', 5));
    }

    public function testInvalidJsonThrows(): void
    {
        $t = new FakeTransport();
        $t->queueResponse([
            'status' => 200,
            'headers' => ['content-type' => 'application/json'],
            'body' => 'garbage',
        ]);

        $p = new WikimediaProvider($this->http($t));
        $this->expectException(ProviderException::class);
        $p->search('coffee', 5);
    }

    public function testSkipsPageWithoutImageinfo(): void
    {
        $t = new FakeTransport();
        $t->queueResponse([
            'status' => 200,
            'headers' => ['content-type' => 'application/json'],
            'body' => (string) json_encode([
                'query' => [
                    'pages' => [
                        '1' => ['title' => 'File:bad.jpg'], // нет imageinfo
                        '2' => [
                            'title' => 'File:good.jpg',
                            'imageinfo' => [[
                                'url' => 'https://upload.wikimedia.org/good.jpg',
                                'width' => 100,
                                'height' => 100,
                            ]],
                        ],
                    ],
                ],
            ]),
        ]);

        $p = new WikimediaProvider($this->http($t));
        $r = $p->search('x', 5);
        self::assertCount(1, $r);
        self::assertSame('https://upload.wikimedia.org/good.jpg', $r[0]->url);
    }
}
