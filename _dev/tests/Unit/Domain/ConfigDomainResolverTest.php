<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Domain;

use Damoony\Core\Config;
use Damoony\Domain\ConfigDomainResolver;
use PHPUnit\Framework\TestCase;

final class ConfigDomainResolverTest extends TestCase
{
    private function resolver(): ConfigDomainResolver
    {
        $site = Config::fromArray([
            'language' => 'ru-ru',
            'domains_real' => [
                'example.ru' => 'example.ru',
                'other.ru' => 'other.ru',
            ],
            'domains_available' => [
                'example.ru' => true,
                'other.ru' => false,
            ],
            'domains_namespace' => [
                'example.ru' => 'example',
                'other.ru' => 'other',
            ],
            'domains_skins' => [
                'example.ru' => 'dark',
            ],
            'domains_stub' => [
                'other.ru' => ['current' => 'maintenance'],
            ],
            'alliases_real' => [
                'example.ru' => ['www' => 'www', 'admin' => 'admin'],
                'other.ru' => ['www' => 'www'],
            ],
        ]);

        return new ConfigDomainResolver($site);
    }

    public function testExactDomain(): void
    {
        $ctx = $this->resolver()->resolve('example.ru');
        self::assertSame('example.ru', $ctx->real);
        self::assertSame('www', $ctx->allias);
        self::assertTrue($ctx->available);
        self::assertSame('dark', $ctx->skin);
        self::assertNull($ctx->stub);
    }

    public function testAlliasPrefix(): void
    {
        $ctx = $this->resolver()->resolve('admin.example.ru');
        self::assertSame('example.ru', $ctx->real);
        self::assertSame('admin', $ctx->allias);
        self::assertTrue($ctx->available);
    }

    public function testUnknownDomainIsUnavailable(): void
    {
        $ctx = $this->resolver()->resolve('unknown.test');
        self::assertFalse($ctx->available);
        self::assertSame('unknown.test', $ctx->real);
        self::assertSame('', $ctx->allias);
    }

    public function testUnavailableDomainHasStub(): void
    {
        $ctx = $this->resolver()->resolve('other.ru');
        self::assertFalse($ctx->available);
        self::assertSame('maintenance', $ctx->stub);
    }

    public function testExists(): void
    {
        $r = $this->resolver();
        self::assertTrue($r->exists('example.ru'));
        self::assertFalse($r->exists('unknown.test'));
    }

    public function testReal(): void
    {
        $r = $this->resolver();
        self::assertSame('example.ru', $r->real('admin.example.ru'));
        self::assertSame('unknown.test', $r->real('unknown.test'));
    }

    public function testNamespace(): void
    {
        $r = $this->resolver();
        self::assertSame('example', $r->namespace('example.ru'));
        self::assertSame('other', $r->namespace('other.ru'));
    }
    private function mappedResolver(): ConfigDomainResolver
    {
        $site = Config::fromArray([
            'language' => 'ru-ru',
            'domains_real' => [
                'damoony.ru' => 'damoony.ru',
                'damoony.online' => 'damoony.ru',
                'damoony.shop' => 'damoony.ru',
                'damoony3.ru' => 'damoony.ru',
            ],
            'domains_available' => ['damoony.ru' => true],
            'domains_namespace' => ['damoony.ru' => 'damoony_ru'],
            'alliases_real' => ['damoony.ru' => ['www' => 'www', 'admin' => 'admin']],
        ]);

        return new ConfigDomainResolver($site);
    }

    public function testMappedDomainExact(): void
    {
        $ctx = $this->mappedResolver()->resolve('damoony.online');
        self::assertSame('damoony.ru', $ctx->real);
        self::assertSame('www', $ctx->allias);
        self::assertTrue($ctx->available);
    }

    public function testMappedDomainAllias(): void
    {
        $ctx = $this->mappedResolver()->resolve('admin.damoony.shop');
        self::assertSame('damoony.ru', $ctx->real);
        self::assertSame('admin', $ctx->allias);
    }

    public function testMappedDomainNamespace(): void
    {
        self::assertSame('damoony_ru', $this->mappedResolver()->namespace('damoony3.ru'));
    }

    public function testMappedDomainReal(): void
    {
        self::assertSame('damoony.ru', $this->mappedResolver()->real('damoony.online'));
        self::assertSame('damoony.ru', $this->mappedResolver()->real('damoony3.ru'));
    }
}
