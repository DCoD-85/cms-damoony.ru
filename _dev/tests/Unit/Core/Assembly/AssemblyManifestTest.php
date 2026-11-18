<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Core\Assembly;

use Damoony\Core\Assembly\AssemblyManifest;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AssemblyManifestTest extends TestCase
{
    public function testWithoutLangsFallsBackToRuEn(): void
    {
        $manifest = AssemblyManifest::fromJson('{"name": "Demo"}');

        self::assertFalse($manifest->hasLangs());
        self::assertSame(['ru', 'en'], $manifest->projectLangs());
        self::assertSame('ru', $manifest->defaultLang());
        self::assertSame(['ru', 'en'], $manifest->aliasLangs('docs'));
    }

    public function testReadsProjectDefaultAndAliases(): void
    {
        $manifest = AssemblyManifest::fromJson(<<<'JSON'
            {
              "name": "Minimal",
              "aliases": ["www", "docs"],
              "langs": {
                "project": ["en", "ru"],
                "default": "ru",
                "aliases": { "www": ["ru", "en"], "docs": ["en"] }
              }
            }
            JSON);

        self::assertTrue($manifest->hasLangs());
        self::assertSame(['ru', 'en'], $manifest->projectLangs());
        self::assertSame('ru', $manifest->defaultLang());
        self::assertSame(['ru', 'en'], $manifest->aliasLangs('www'));
        self::assertSame(['en'], $manifest->aliasLangs('docs'));
        self::assertSame(['ru', 'en'], $manifest->aliasLangs('other'));
    }

    public function testDefaultMustBeInProject(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('langs.default');
        AssemblyManifest::fromJson('{"name":"X","langs":{"project":["ru"],"default":"en"}}');
    }

    public function testAliasLangsMustBeSubsetOfProject(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('не входит в языки проекта');
        AssemblyManifest::fromJson('{"name":"X","langs":{"project":["ru"],"aliases":{"www":["en"]}}}');
    }

    public function testRejectsEmptyProject(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('langs.project');
        AssemblyManifest::fromJson('{"name":"X","langs":{"project":[]}}');
    }

    public function testRejectsBadLangCode(): void
    {
        $this->expectException(RuntimeException::class);
        AssemblyManifest::fromJson('{"name":"X","langs":{"project":["russian"]}}');
    }
}
