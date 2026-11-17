<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Domain;

use Damoony\Core\Domain\SiteDomainResolver;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Tests\Support\SiteConfigFixture;
use PHPUnit\Framework\TestCase;

final class SiteDomainResolverTest extends TestCase
{
    public function testExactDomainUsesFolderAndDefaultAlias(): void
    {
        $ctx = $this->resolver()->resolve('example.ru');

        self::assertSame('example.ru', $ctx->domain);
        self::assertSame('example.ru', $ctx->real);
        self::assertSame('www', $ctx->allias);
        self::assertTrue($ctx->available);
        self::assertNull($ctx->stub);
        self::assertSame('example_ru', $ctx->namespace);
        self::assertSame('www', $ctx->scheme);
        self::assertSame('ru', $ctx->language);
        self::assertSame(['ru'], $ctx->supportedLanguages);
    }

    public function testAlliasPrefix(): void
    {
        $ctx = $this->resolver()->resolve('admin.example.ru');

        self::assertSame('example.ru', $ctx->real);
        self::assertSame('admin', $ctx->allias);
        self::assertTrue($ctx->available);
    }

    public function testUndeclaredSubdomainIsNotAnAllias(): void
    {
        $ctx = $this->resolver()->resolve('ghost.example.ru');

        self::assertFalse($ctx->available);
        self::assertSame('', $ctx->allias);
    }

    public function testUnknownDomainIsUnavailable(): void
    {
        $ctx = $this->resolver()->resolve('unknown.test');

        self::assertFalse($ctx->available);
        self::assertSame('unknown.test', $ctx->real);
        self::assertSame('', $ctx->allias);
    }

    public function testUnavailableMirrorHasProjectStub(): void
    {
        $site = SiteConfigFixture::single('example.ru', available: false, stub: 'offline');
        $resolver = new SiteDomainResolver($site, new Paths(sys_get_temp_dir()));

        $ctx = $resolver->resolve('example.ru');

        self::assertFalse($ctx->available);
        self::assertSame('offline', $ctx->stub);
    }

    public function testAlliasStubOverridesProject(): void
    {
        $site = SiteConfig::fromArray([
            'domains_app' => [
                'example.ru' => [
                    'folder' => 'example.ru',
                    'namespace' => 'example_ru',
                    'stub' => 'maintenance',
                    'mirrors' => ['example.ru' => ['available' => false]],
                ],
            ],
            'alliases' => [
                'example.ru' => [
                    'www' => ['available' => true, 'langs' => ['ru'], 'stub' => 'policy'],
                ],
            ],
        ]);
        $ctx = new SiteDomainResolver($site, new Paths(sys_get_temp_dir()))->resolve('example.ru');

        self::assertFalse($ctx->available);
        self::assertSame('policy', $ctx->stub);
    }

    public function testAliasLangsDriveLanguage(): void
    {
        $site = SiteConfigFixture::single('example.ru', langs: ['en', 'de']);
        $ctx = new SiteDomainResolver($site, new Paths(sys_get_temp_dir()))->resolve('example.ru');

        self::assertSame('en', $ctx->language);
        self::assertSame(['en', 'de'], $ctx->supportedLanguages);
    }

    public function testProjectLangsFallback(): void
    {
        $site = SiteConfig::fromArray([
            'domains_app' => [
                'example.ru' => [
                    'folder' => 'example.ru',
                    'namespace' => 'example_ru',
                    'langs' => ['de'],
                    'mirrors' => ['example.ru' => ['available' => true]],
                ],
            ],
            'alliases' => ['example.ru' => ['www' => ['available' => true]]],
        ]);

        $ctx = new SiteDomainResolver($site, new Paths(sys_get_temp_dir()))->resolve('example.ru');

        self::assertSame('de', $ctx->language);
        self::assertSame(['de'], $ctx->supportedLanguages);
    }

    public function testSchemeFromProject(): void
    {
        $site = SiteConfigFixture::single('example.ru', scheme: 'landing');
        $ctx = new SiteDomainResolver($site, new Paths(sys_get_temp_dir()))->resolve('example.ru');

        self::assertSame('landing', $ctx->scheme);
    }

    public function testHostIsNormalized(): void
    {
        $resolver = $this->resolver();

        self::assertSame('example.ru', $resolver->resolve('example.ru:8080')->domain);
        self::assertSame('example.ru', $resolver->resolve('example.ru.')->domain);
        self::assertSame('example.ru', $resolver->resolve('EXAMPLE.RU')->domain);
        self::assertSame('example.ru', $resolver->resolve('EXAMPLE.RU.:8080')->domain);
    }

    public function testExistsAndRealAndNamespace(): void
    {
        $resolver = $this->resolver();

        self::assertTrue($resolver->exists('example.ru'));
        self::assertTrue($resolver->exists('admin.example.ru'));
        self::assertFalse($resolver->exists('unknown.test'));

        self::assertSame('example.ru', $resolver->real('admin.example.ru'));
        self::assertSame('example_ru', $resolver->namespace('example.ru'));
        self::assertSame('unknown_test', $resolver->namespace('unknown.test'));
    }

    private function resolver(): SiteDomainResolver
    {
        $site = SiteConfigFixture::single('example.ru', ['www', 'admin']);

        return new SiteDomainResolver($site, new Paths(sys_get_temp_dir()));
    }
}
