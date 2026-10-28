<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Scheme;

use Damoony\Scheme\SchemeCompiler;
use PHPUnit\Framework\TestCase;

final class SchemeCompilerTest extends TestCase
{
    public function testCompileSimpleArray(): void
    {
        $compiler = new SchemeCompiler();
        $data = [
            'tag' => 'div',
            'class' => 'layout',
            'children' => [
                ['tag' => 'main', 'slot' => 'center'],
            ],
        ];

        $php = $compiler->compile($data);

        self::assertStringContainsString("<?php\n\nreturn ", $php);
        self::assertStringContainsString("'tag' => 'div'", $php);
        self::assertStringContainsString("'slot' => 'center'", $php);

        $evaluated = eval(str_replace('<?php', '', $php));
        self::assertSame($data, $evaluated);
    }

    public function testCompileStringWithParentheses(): void
    {
        $compiler = new SchemeCompiler();
        $data = [
            'tag' => 'div',
            'class' => 'btn (primary)',
            'children' => [
                ['tag' => 'span', 'class' => 'label (sm)'],
            ],
        ];

        $php = $compiler->compile($data);

        self::assertStringContainsString("'btn (primary)'", $php);
        self::assertStringContainsString("'label (sm)'", $php);

        $evaluated = eval(str_replace('<?php', '', $php));
        self::assertSame($data, $evaluated);
    }

    public function testCompileStringWithArrayParenAndBrackets(): void
    {
        $compiler = new SchemeCompiler();
        $data = [
            'tag' => 'div',
            'class' => 'array (sm) [x]',
        ];

        $php = $compiler->compile($data);
        $evaluated = eval(str_replace('<?php', '', $php));

        self::assertSame($data, $evaluated);
    }

    public function testCompileIsSyntacticallyValid(): void
    {
        $compiler = new SchemeCompiler();
        $data = [
            'tag' => 'div',
            'class' => 'a (b) c) (d',
            'children' => [
                ['tag' => 'main', 'slot' => 'center'],
            ],
        ];

        $php = $compiler->compile($data);

        // token_get_all с TOKEN_PARSE бросит ParseError, если синтаксис битый
        token_get_all($php, TOKEN_PARSE);

        $this->addToAssertionCount(1);
    }
}
