<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Core;

use Damoony\Core\Config;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ConfigTest extends TestCase
{
    public function testFromArrayAndDotGet(): void
    {
        $c = Config::fromArray([
            'a' => ['b' => ['c' => 42]],
            'x' => 'y',
        ]);

        self::assertSame(42, $c->get('a.b.c'));
        self::assertSame('y', $c->get('x'));
    }

    public function testGetMissingReturnsDefault(): void
    {
        $c = Config::fromArray(['a' => 1]);
        self::assertNull($c->get('nope'));
        self::assertSame('d', $c->get('nope.deep', 'd'));
    }

    public function testHas(): void
    {
        $c = Config::fromArray(['a' => ['b' => 0]]);
        self::assertTrue($c->has('a.b'));
        self::assertTrue($c->has('a'));
        self::assertFalse($c->has('a.b.c'));
    }

    public function testGetByKeySupportsDottedKeys(): void
    {
        $c = Config::fromArray([
            'domains_available' => ['example.ru' => true],
            'domains_real' => ['example.ru' => 'example.ru'],
        ]);

        self::assertTrue($c->getByKey(['domains_available', 'example.ru']));
        self::assertSame('example.ru', $c->getByKey(['domains_real', 'example.ru']));

        self::assertNull($c->get('domains_available.example.ru'));
        self::assertTrue($c->hasByKey(['domains_available', 'example.ru']));
        self::assertFalse($c->hasByKey(['domains_available', 'nope.ru']));
    }

    public function testFromFileThrowsOnMissing(): void
    {
        $this->expectException(RuntimeException::class);
        Config::fromFile(__DIR__ . '/does-not-exist.json');
    }

    public function testFromFileThrowsOnInvalidJson(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'cfg');
        file_put_contents($tmp, '{not json');

        try {
            $this->expectException(RuntimeException::class);
            Config::fromFile($tmp);
        } finally {
            @unlink($tmp);
        }
    }

    public function testFromFileReadsJson(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'cfg');
        file_put_contents($tmp, json_encode(['k' => ['v' => 1]]));

        try {
            $c = Config::fromFile($tmp);
            self::assertSame(1, $c->get('k.v'));
        } finally {
            @unlink($tmp);
        }
    }

    public function testAll(): void
    {
        $data = ['a' => 1, 'b' => ['c' => 2]];
        $c = Config::fromArray($data);
        self::assertSame($data, $c->all());
    }
}
