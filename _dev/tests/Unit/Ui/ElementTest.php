<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Ui;

use Damoony\Ui\Element;
use PHPUnit\Framework\TestCase;

final class ElementTest extends TestCase
{
    public function testEmptyElement(): void
    {
        $e = new Element('div');
        self::assertSame('<div></div>', $e->release());
    }

    public function testSelfClosing(): void
    {
        $e = new Element('img', true);
        self::assertSame('<img />', $e->release());
    }

    public function testNested(): void
    {
        $root = new Element('div');
        $span = new Element('span');
        $span->content('hello');
        $root->append($span);

        self::assertSame('<div><span>hello</span></div>', $root->release());
        self::assertSame($root, $span->parent());
    }

    public function testAttrsRendered(): void
    {
        $e = new Element('div');
        $e->attrs()->add('id', 'main')->classAdd('a b')->classAdd('a')->styleAdd('color:red; padding:4px');
        self::assertSame(
            '<div class="a b" style="color:red;padding:4px;" id="main"></div>',
            $e->release(),
        );
    }

    public function testEscaping(): void
    {
        $e = new Element('div');
        $e->attrs()->add('title', '<bad>');
        $e->content('<script>');
        self::assertSame(
            '<div title="&lt;bad&gt;">&lt;script&gt;</div>',
            $e->release(),
        );
    }

    public function testPrepend(): void
    {
        $e = new Element('div');
        $e->append('b');
        $e->prepend('a');
        self::assertSame('<div>ab</div>', $e->release());
    }

    public function testClear(): void
    {
        $e = new Element('div');
        $e->append('x')->clear();
        self::assertSame('<div></div>', $e->release());
    }

    public function testDataAttrs(): void
    {
        $e = new Element('div');
        $e->attrs()->dataAdd('role', 'admin');
        $e->attrs()->dataAdd('num', 5);
        self::assertSame(
            '<div data-role="&quot;admin&quot;" data-num="5"></div>',
            $e->release(),
        );
    }
}
