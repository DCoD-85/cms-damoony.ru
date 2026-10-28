<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Page;

use Damoony\Page\PageCompiler;
use PHPUnit\Framework\TestCase;

final class PageCompilerTest extends TestCase
{
    public function testCompileSimpleSlot(): void
    {
        $compiler = new PageCompiler();
        $php = $compiler->compile('example_ru', 'www', 'algoritm_about', [
            'center' => [
                'status' => 'local',
                'blocks' => [
                    ['type' => 'h1', 'content' => 'Hello'],
                ],
            ],
        ]);

        self::assertStringContainsString('final class algoritm_about', $php);
        self::assertStringContainsString("Builder::h(1)->content('Hello')", $php);
        self::assertStringContainsString("\$this->setContent('center'", $php);

        token_get_all($php, TOKEN_PARSE);
        $this->addToAssertionCount(1);
    }

    public function testContentWithQuotesDollarNewline(): void
    {
        $compiler = new PageCompiler();
        $content = "It's a \$test\nwith (parens) and \\ backslash";
        $php = $compiler->compile('example_ru', 'www', 'algoritm_x', [
            'center' => [
                'status' => 'local',
                'blocks' => [
                    ['type' => 'text', 'content' => $content],
                ],
            ],
        ]);

        token_get_all($php, TOKEN_PARSE);

        // Проверяем, что var_export не поломал данные
        self::assertStringContainsString("It\\'s a \$test", $php);
        self::assertStringNotContainsString('addslashes', $php);

        $this->addToAssertionCount(1);
    }

    public function testContentWithArrayParen(): void
    {
        $compiler = new PageCompiler();
        $content = 'class sm (mobile) array (x)';
        $php = $compiler->compile('example_ru', 'www', 'algoritm_x', [
            'center' => [
                'status' => 'local',
                'blocks' => [
                    ['type' => 'h2', 'content' => $content],
                ],
            ],
        ]);

        token_get_all($php, TOKEN_PARSE);
        self::assertStringContainsString('sm (mobile) array (x)', $php);
        $this->addToAssertionCount(1);
    }

    public function testDisabledAndGlobalSlots(): void
    {
        $compiler = new PageCompiler();
        $php = $compiler->compile('example_ru', 'www', 'algoritm_x', [
            'top' => ['status' => 'disabled'],
            'left' => ['status' => 'global'],
        ]);

        self::assertStringContainsString("\$this->regions()->disable('top')", $php);
        self::assertStringContainsString("\$this->regions()->useGlobal('left')", $php);
        token_get_all($php, TOKEN_PARSE);
        $this->addToAssertionCount(1);
    }
}
