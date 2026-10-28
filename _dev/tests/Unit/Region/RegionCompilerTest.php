<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Region;

use Damoony\Region\RegionCompiler;
use PHPUnit\Framework\TestCase;

final class RegionCompilerTest extends TestCase
{
    public function testCompileSimpleBlock(): void
    {
        $compiler = new RegionCompiler();
        $php = $compiler->compile('example_ru', 'top', [
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
        $compiler = new RegionCompiler();
        $content = "It's a \$test\nwith (parens) and \\ backslash";
        $php = $compiler->compile('example_ru', 'top', [
            ['type' => 'text', 'content' => $content],
        ]);

        token_get_all($php, TOKEN_PARSE);
        self::assertStringContainsString("It\\'s a \$test", $php);
        self::assertStringNotContainsString('addslashes', $php);
        $this->addToAssertionCount(1);
    }

    public function testContentWithParens(): void
    {
        $compiler = new RegionCompiler();
        $php = $compiler->compile('example_ru', 'top', [
            ['type' => 'h1', 'content' => 'array (x) and (y)'],
        ]);

        token_get_all($php, TOKEN_PARSE);
        self::assertStringContainsString('array (x) and (y)', $php);
        $this->addToAssertionCount(1);
    }
}
