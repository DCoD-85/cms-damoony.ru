<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\UI;

use PHPUnit\Framework\TestCase;

/**
 * Контракт элементов: каждый ui-* класс из UI\Components имеет правило в elements.css.
 */
final class ElementsStyleContractTest extends TestCase
{
    private const CLASSES = [
        'ui-btn',
        'ui-input',
        'ui-textarea',
        'ui-select',
        'ui-label',
        'ui-form',
        'ui-form-wrapper',
        'ui-table',
        'ui-thead',
        'ui-tbody',
        'ui-tr',
        'ui-th',
        'ui-td',
        'ui-error',
        'ui-link',
        'ui-img',
    ];

    public function testSharedElementsCssContainsAllContractClasses(): void
    {
        $file = dirname(__DIR__, 4) . '/www/media/shared/css/elements.css';
        self::assertFileExists($file);

        $css = (string) file_get_contents($file);

        foreach (self::CLASSES as $class) {
            self::assertStringContainsString('.' . $class, $css, 'elements.css: нет правила для ' . $class);
        }
    }

    public function testRhythmTokensAreDefined(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__, 4) . '/www/media/shared/css/elements.css');

        foreach (['--ui-line', '--ui-pad-y', '--ui-pad-x', '--ui-gap'] as $token) {
            self::assertStringContainsString($token . ':', $css, 'elements.css: нет токена ' . $token);
        }

        self::assertStringContainsString('vertical-align: middle', $css);
        self::assertStringContainsString('calc(var(--ui-pad-y) - var(--ui-control-border))', $css);
    }

    public function testAssembliesShipSameElementsCss(): void
    {
        $base = dirname(__DIR__, 4) . '/www/media/shared/css/elements.css';
        $expected = (string) file_get_contents($base);

        foreach (glob(dirname(__DIR__, 4) . '/stubs/assemblies/*/shared/css/elements.css') ?: [] as $file) {
            self::assertSame($expected, (string) file_get_contents($file), $file);
        }
    }
}
