<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Ui;

use Damoony\Ui\Element;
use Damoony\Ui\Fragment;
use Damoony\Ui\Text;
use PHPUnit\Framework\TestCase;

final class TextFragmentGroupTest extends TestCase
{
    public function testEscapedText(): void
    {
        self::assertSame('&lt;b&gt;', Text::escaped('<b>')->release());
    }

    public function testRawText(): void
    {
        self::assertSame('<b>', Text::raw('<b>')->release());
    }

    public function testTextCannotBeMutated(): void
    {
        $this->expectException(\LogicException::class);
        Text::escaped('x')->append('y');
    }

    public function testFragmentReleasesChildrenConcatenated(): void
    {
        $f = new Fragment(new Element('b'), 'plain', new Element('i'));
        self::assertSame('<b></b>plain<i></i>', $f->release());
    }

    public function testFragmentEscapesStrings(): void
    {
        $f = new Fragment('<x>');
        self::assertSame('&lt;x&gt;', $f->release());
    }

    public function testFragmentPrependAppend(): void
    {
        $f = new Fragment();
        $f->append('b');
        $f->prepend('a');
        self::assertSame('ab', $f->release());
    }
}
