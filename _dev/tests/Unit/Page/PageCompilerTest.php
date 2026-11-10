<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Page;

use Damoony\UI\Page\PageCompiler;
use PHPUnit\Framework\TestCase;

final class PageCompilerTest extends TestCase
{
    // === URL sanitization: link/button ===

    public function testLinkWithJavascriptSchemeIsNeutralized(): void
    {
        $php = new PageCompiler()->compile('example_ru\\www', 'algoritm_root', [
            ['type' => 'link', 'url' => 'javascript:alert(1)', 'content' => 'Click'],
        ]);

        token_get_all($php, TOKEN_PARSE);
        self::assertStringContainsString("new Link('#', 'Click')", $php);
        self::assertStringNotContainsString('javascript:', $php);
    }

    public function testButtonIgnoresUrlAndRendersAsButton(): void
    {
        $php = new PageCompiler()->compile('example_ru\\www', 'algoritm_root', [
            ['type' => 'button', 'url' => 'javascript:void(0)', 'content' => 'Click'],
        ]);

        token_get_all($php, TOKEN_PARSE);
        self::assertStringContainsString("new Button('Click', 'submit')", $php);
        self::assertStringNotContainsString('javascript:', $php);
    }

    public function testValidHttpsLinkPasses(): void
    {
        $php = new PageCompiler()->compile('example_ru\\www', 'algoritm_root', [
            ['type' => 'link', 'url' => 'https://example.com/x', 'content' => 'Go'],
        ]);

        self::assertStringContainsString("new Link('https://example.com/x', 'Go')", $php);
    }

    public function testProtocolRelativeUrlIsNeutralized(): void
    {
        $php = new PageCompiler()->compile('example_ru\\www', 'algoritm_root', [
            ['type' => 'link', 'url' => '//evil.example/x', 'content' => 'Evil'],
        ]);

        token_get_all($php, TOKEN_PARSE);
        self::assertStringContainsString("new Link('#', 'Evil')", $php);
        self::assertStringNotContainsString('evil.example', $php);
    }

    // === URL sanitization: img ===

    public function testImgWithJavascriptSchemeIsNeutralized(): void
    {
        $php = new PageCompiler()->compile('example_ru\\www', 'algoritm_root', [
            ['type' => 'img', 'content' => 'javascript:alert(1)', 'alt' => 'X'],
        ]);

        token_get_all($php, TOKEN_PARSE);
        self::assertStringContainsString("new Img('', 'X')", $php);
        self::assertStringNotContainsString('javascript:', $php);
    }

    public function testImgWithDataImageIsAllowed(): void
    {
        $php = new PageCompiler()->compile('example_ru\\www', 'algoritm_root', [
            ['type' => 'img', 'content' => 'data:image/png;base64,iVBORw0KGgo=', 'alt' => 'Inline'],
        ]);

        self::assertStringContainsString('data:image/png;base64', $php);
    }

    // === URL sanitization: nav ===

    public function testNavWithJavascriptUrlIsNeutralized(): void
    {
        $php = new PageCompiler()->compile('example_ru\\www', 'algoritm_root', [
            [
                'type' => 'nav',
                'items' => [
                    ['title' => 'Home', 'url' => '/'],
                    ['title' => 'Evil', 'url' => 'javascript:alert(1)'],
                ],
            ],
        ]);

        token_get_all($php, TOKEN_PARSE);
        self::assertStringContainsString("new Link('#', 'Evil')", $php);
        self::assertStringNotContainsString('javascript:', $php);
    }

    public function testValidBlocksCompileWithoutSanitization(): void
    {
        $php = new PageCompiler()->compile('example_ru\\www', 'algoritm_root', [
            ['type' => 'h1', 'content' => 'Title'],
            ['type' => 'text', 'content' => 'Body'],
            ['type' => 'img', 'content' => '/content/example.ru/www/general/abc123def4567890.png', 'alt' => 'Pic'],
            ['type' => 'link', 'url' => '/about', 'content' => 'About'],
        ]);

        token_get_all($php, TOKEN_PARSE);
        self::assertStringContainsString('Builder::h(1)', $php);
        self::assertStringContainsString('Builder::p()', $php);
        self::assertStringContainsString('/content/example.ru/www/general/abc123def4567890.png', $php);
        self::assertStringContainsString("new Link('/about', 'About')", $php);
    }

    // === withSchema path (smoke) ===

    public function testWithSchemaCompilesWithoutSyntaxErrors(): void
    {
        $php = new PageCompiler()->compile(
            'example_ru\\www',
            'algoritm_root',
            [['type' => 'h1', 'content' => 'Hi']],
            true,
        );

        token_get_all($php, TOKEN_PARSE);
        $this->addToAssertionCount(1);
    }
}
