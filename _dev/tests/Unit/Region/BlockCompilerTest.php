<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Region;

use Damoony\UI\Region\BlockCompiler;
use PHPUnit\Framework\TestCase;

final class BlockCompilerTest extends TestCase
{
    // === URL sanitization: link/button ===

    public function testLinkWithJavascriptSchemeIsNeutralized(): void
    {
        $php = new BlockCompiler()->compile('example_ru', 'alliasglobal_top', 'top', [
            ['type' => 'link', 'url' => 'javascript:alert(1)', 'content' => 'Click'],
        ]);

        token_get_all($php, TOKEN_PARSE);
        self::assertStringContainsString("Builder::a('#')", $php);
        self::assertStringNotContainsString('javascript:', $php);
    }

    public function testButtonIgnoresUrlAndRendersAsButton(): void
    {
        $php = new BlockCompiler()->compile('example_ru', 'alliasglobal_top', 'top', [
            ['type' => 'button', 'url' => 'javascript:void(0)', 'content' => 'Click'],
        ]);

        token_get_all($php, TOKEN_PARSE);
        self::assertStringContainsString("Builder::button('submit')", $php);
        self::assertStringNotContainsString('javascript:', $php);
    }

    public function testValidHttpsLinkPasses(): void
    {
        $php = new BlockCompiler()->compile('example_ru', 'alliasglobal_top', 'top', [
            ['type' => 'link', 'url' => 'https://example.com/x', 'content' => 'Go'],
        ]);

        self::assertStringContainsString("Builder::a('https://example.com/x')", $php);
    }

    public function testValidRelativePathPasses(): void
    {
        $php = new BlockCompiler()->compile('example_ru', 'alliasglobal_top', 'top', [
            ['type' => 'link', 'url' => '/about', 'content' => 'About'],
        ]);

        self::assertStringContainsString("Builder::a('/about')", $php);
    }

    public function testAnchorPasses(): void
    {
        $php = new BlockCompiler()->compile('example_ru', 'alliasglobal_top', 'top', [
            ['type' => 'link', 'url' => '#section', 'content' => 'Jump'],
        ]);

        self::assertStringContainsString("Builder::a('#section')", $php);
    }

    public function testProtocolRelativeUrlIsNeutralized(): void
    {
        $php = new BlockCompiler()->compile('example_ru', 'alliasglobal_top', 'top', [
            ['type' => 'link', 'url' => '//evil.example/x', 'content' => 'Evil'],
        ]);

        token_get_all($php, TOKEN_PARSE);
        self::assertStringContainsString("Builder::a('#')", $php);
        self::assertStringNotContainsString('evil.example', $php);
    }

    // === URL sanitization: img ===

    public function testImgWithJavascriptSchemeIsNeutralized(): void
    {
        $php = new BlockCompiler()->compile('example_ru', 'alliasglobal_top', 'top', [
            ['type' => 'img', 'content' => 'javascript:alert(1)', 'alt' => 'X'],
        ]);

        token_get_all($php, TOKEN_PARSE);
        self::assertStringContainsString("Builder::img('', 'X')", $php);
        self::assertStringNotContainsString('javascript:', $php);
    }

    public function testImgWithHttpsPasses(): void
    {
        $php = new BlockCompiler()->compile('example_ru', 'alliasglobal_top', 'top', [
            ['type' => 'img', 'content' => 'https://cdn.example/x.png', 'alt' => 'Pic'],
        ]);

        self::assertStringContainsString("Builder::img('https://cdn.example/x.png', 'Pic')", $php);
    }

    public function testImgWithDataImageIsAllowed(): void
    {
        $dataUrl = 'data:image/png;base64,iVBORw0KGgo=';
        $php = new BlockCompiler()->compile('example_ru', 'alliasglobal_top', 'top', [
            ['type' => 'img', 'content' => $dataUrl, 'alt' => 'Inline'],
        ]);

        self::assertStringContainsString('data:image/png;base64', $php);
    }

    public function testDataImageBlockedForLink(): void
    {
        // data:image/... is allowed only for <img src>, not for <a href>.
        $dataUrl = 'data:image/png;base64,iVBORw0KGgo=';
        $php = new BlockCompiler()->compile('example_ru', 'alliasglobal_top', 'top', [
            ['type' => 'link', 'url' => $dataUrl, 'content' => 'X'],
        ]);

        token_get_all($php, TOKEN_PARSE);
        self::assertStringContainsString("Builder::a('#')", $php);
        self::assertStringNotContainsString('data:image', $php);
    }

    // === URL sanitization: nav ===

    public function testNavWithJavascriptUrlIsNeutralized(): void
    {
        $php = new BlockCompiler()->compile('example_ru', 'alliasglobal_top', 'top', [
            [
                'type' => 'nav',
                'items' => [
                    ['title' => 'Home', 'url' => '/'],
                    ['title' => 'Evil', 'url' => 'javascript:alert(1)'],
                ],
            ],
        ]);

        token_get_all($php, TOKEN_PARSE);
        self::assertStringContainsString("Builder::a('/')", $php);
        self::assertStringContainsString("Builder::a('#')", $php);
        self::assertStringNotContainsString('javascript:', $php);
    }

    public function testNavWithValidUrlsPasses(): void
    {
        $php = new BlockCompiler()->compile('example_ru', 'alliasglobal_top', 'top', [
            [
                'type' => 'nav',
                'items' => [
                    ['title' => 'Home', 'url' => '/'],
                    ['title' => 'About', 'url' => '/about'],
                    ['title' => 'Ext', 'url' => 'https://example.com/x'],
                ],
            ],
        ]);

        self::assertStringContainsString("Builder::a('/about')", $php);
        self::assertStringContainsString("Builder::a('https://example.com/x')", $php);
    }
}
