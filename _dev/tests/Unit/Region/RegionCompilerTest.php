<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Region;

use Damoony\Region\RegionCompiler;
use PHPUnit\Framework\TestCase;

final class RegionCompilerTest extends TestCase
{
    // === P0-регрессии из stage-1 (не терять) ===

    public function testCompileSimpleBlock(): void
    {
        $php = (new RegionCompiler())->compile('example_ru', 'top', [
            ['type' => 'h2', 'content' => 'Logo'],
        ]);

        self::assertStringContainsString('final class alliasglobal_top', $php);
        self::assertStringContainsString("Builder::h(2)->content('Logo')", $php);
        self::assertStringContainsString("return 'top'", $php);

        token_get_all($php, TOKEN_PARSE);
        $this->addToAssertionCount(1);
    }

    public function testContentWithQuotesDollarNewline(): void
    {
        $content = "It's a \$test\nwith (parens) and \\ backslash";
        $php = (new RegionCompiler())->compile('example_ru', 'top', [
            ['type' => 'text', 'content' => $content],
        ]);

        token_get_all($php, TOKEN_PARSE);
        self::assertStringContainsString("It\\'s a \$test", $php);
        self::assertStringNotContainsString('addslashes', $php);
        $this->addToAssertionCount(1);
    }

    public function testContentWithParens(): void
    {
        $php = (new RegionCompiler())->compile('example_ru', 'top', [
            ['type' => 'h1', 'content' => 'array (x) and (y)'],
        ]);

        token_get_all($php, TOKEN_PARSE);
        self::assertStringContainsString('array (x) and (y)', $php);
        $this->addToAssertionCount(1);
    }

    // === Новые тесты: RCE guard (allow_php_blocks) ===

    public function testThrowsOnCustomBlockByDefault(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/allow_php_blocks/');

        (new RegionCompiler())->compile('ns', 'top', [
            ['type' => 'custom', 'code' => 'echo 1;'],
        ]);
    }

    public function testThrowsOnIfBlockByDefault(): void
    {
        $this->expectException(\RuntimeException::class);

        (new RegionCompiler())->compile('ns', 'top', [
            ['type' => 'if', 'condition' => '$x > 0'],
            ['type' => 'endif'],
        ]);
    }

    public function testAllowsCustomBlockWhenEnabled(): void
    {
        $code = (new RegionCompiler(true))->compile('ns', 'top', [
            ['type' => 'custom', 'code' => 'echo 1;'],
        ]);

        $this->assertStringContainsString('echo 1;', $code);
    }

    public function testSafeBlocksCompileWithoutFlag(): void
    {
        $code = (new RegionCompiler())->compile('ns', 'top', [
            ['type' => 'h1', 'content' => 'Hi'],
            ['type' => 'text', 'content' => 'Body'],
            ['type' => 'img', 'content' => '/x.png'],
        ]);

        $this->assertStringContainsString('Builder::h(1)', $code);
        $this->assertStringContainsString('Builder::p()', $code);
        $this->assertStringContainsString('Builder::img(', $code);
    }

    public function testEmptyBlocksCompileToValidClass(): void
    {
        $code = (new RegionCompiler())->compile('ns', 'top', []);

        $this->assertStringContainsString('final class alliasglobal_top implements RegionInterface', $code);
        $this->assertStringContainsString('return $wrap;', $code);
    }
}
