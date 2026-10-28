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

        self::assertStringContainsString("<?php\n\nreturn [", $php);
        self::assertStringContainsString("'tag' => 'div'", $php);
        self::assertStringContainsString("'slot' => 'center'", $php);

        // Проверяем, что полученный код валиден и возвращает тот же массив
        $evaluated = eval(str_replace('<?php', '', $php));
        self::assertSame($data, $evaluated);
    }
}
