<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Ui;

use Damoony\Ui\Attrs;
use PHPUnit\Framework\TestCase;

final class AttrsTest extends TestCase
{
    public function testReleaseEmptyReturnsEmptyString(): void
    {
        $attrs = new Attrs();
        self::assertSame('', $attrs->release());
    }

    public function testReleaseWithClassAndStyle(): void
    {
        $attrs = new Attrs();
        $attrs->classAdd('btn', 'btn-primary');
        $attrs->styleAdd('color: red; margin: 0');
        $attrs->add('id', 'submit');

        $html = $attrs->release();

        self::assertStringContainsString('class="btn btn-primary"', $html);
        self::assertStringContainsString('style="color:red;margin:0;"', $html);
        self::assertStringContainsString('id="submit"', $html);
    }

    public function testValueIsEscaped(): void
    {
        $attrs = new Attrs();
        $attrs->add('title', '"quoted" & <tag>');

        $html = $attrs->release();

        self::assertStringContainsString('&quot;quoted&quot;', $html);
        self::assertStringContainsString('&amp;', $html);
        self::assertStringContainsString('&lt;tag&gt;', $html);
        self::assertStringNotContainsString('<tag>', $html);
    }

    public function testDataAttributes(): void
    {
        $attrs = new Attrs();
        $attrs->dataAdd('user-id', 42);
        $attrs->dataAdd('flag', true);

        $html = $attrs->release();

        self::assertStringContainsString('data-user-id="42"', $html);
        self::assertStringContainsString('data-flag="true"', $html);
    }

    public function testInvalidAttributeNameIsIgnored(): void
    {
        $attrs = new Attrs();
        $attrs->add('valid', 'ok');
        $attrs->add('invalid" onmouseover="alert(1)', 'hack');
        $attrs->add('data-good', 'yes');
        $attrs->dataAdd('bad" onclick="x', 'no');

        $html = $attrs->release();

        self::assertStringContainsString('valid="ok"', $html);
        self::assertStringContainsString('data-good="yes"', $html);
        self::assertStringNotContainsString('onmouseover', $html);
        self::assertStringNotContainsString('onclick', $html);
        self::assertStringNotContainsString('alert(1)', $html);
    }

    public function testEmptyValueSkippedUnlessEmptyFlag(): void
    {
        $attrs = new Attrs();
        $attrs->add('a', '');
        $attrs->add('b', '', true);

        $html = $attrs->release();

        self::assertStringNotContainsString('a=""', $html);
        self::assertStringContainsString('b=""', $html);
    }
}
