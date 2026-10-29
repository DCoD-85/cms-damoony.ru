<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Ui;

use Damoony\Ui\Builder;
use Damoony\Ui\Document;
use PHPUnit\Framework\TestCase;

final class BuilderDocumentTest extends TestCase
{
    public function testDiv(): void
    {
        self::assertSame('<div class="box"></div>', Builder::div('box')->release());
        self::assertSame('<div></div>', Builder::div()->release());
    }

    public function testH(): void
    {
        self::assertSame('<h1></h1>', Builder::h(1)->release());
        self::assertSame('<h6></h6>', Builder::h(6)->release());
        self::assertSame('<h1></h1>', Builder::h(0)->release());
        self::assertSame('<h6></h6>', Builder::h(99)->release());
    }

    public function testA(): void
    {
        $a = Builder::a('/home', 'nav');
        $a->content('Home');
        self::assertSame('<a class="nav" href="/home">Home</a>', $a->release());
    }

    public function testImg(): void
    {
        self::assertSame('<img src="/x.png" alt="pic" />', Builder::img('/x.png', 'pic')->release());
        self::assertSame('<img src="/x.png" />', Builder::img('/x.png')->release());
    }

    public function testInput(): void
    {
        self::assertSame(
            '<input type="text" name="q" value="hi" />',
            Builder::input('text', 'q', 'hi')->release(),
        );
        self::assertSame('<input type="text" />', Builder::input()->release());
    }

    public function testUlLi(): void
    {
        $ul = Builder::ul('menu');
        $ul->append(Builder::li()->content('one'));
        $ul->append(Builder::li()->content('two'));
        self::assertSame(
            '<ul class="menu"><li>one</li><li>two</li></ul>',
            $ul->release(),
        );
    }

    public function testButton(): void
    {
        $b = Builder::button('submit', 'primary');
        $b->content('Send');
        self::assertSame('<button class="primary" type="submit">Send</button>', $b->release());
    }

    public function testDocumentBasics(): void
    {
        $doc = new Document();
        $doc->lang('en-en');
        $doc->title('Hello');

        $html = $doc->release();
        self::assertStringStartsWith("<!DOCTYPE html>\n<html lang=\"en-en\">", $html);
        self::assertStringContainsString('<meta charset="utf-8" />', $html);
        self::assertStringContainsString('<title>Hello</title>', $html);
        self::assertStringContainsString('<body></body>', $html);
    }

    public function testDocumentDefaultLang(): void
    {
        $doc = new Document();
        $doc->lang('ru-ru');
        self::assertStringContainsString('<html lang="ru-ru">', $doc->release());
    }

    public function testDocumentBodyMutation(): void
    {
        $doc = new Document();
        $doc->body()->append(Builder::div('main')->content('x'));
        self::assertStringContainsString('<body><div class="main">x</div></body>', $doc->release());
    }

    public function testReleaseIsIdempotent(): void
    {
        $meta = new \Damoony\Ui\Meta();
        $meta->setTitle('Test');
        $doc = new Document($meta);
        $doc->body()->append(Builder::div()->content('Body'));

        $html1 = $doc->release();
        $html2 = $doc->release();

        self::assertSame($html1, $html2, 'Document::release() must be idempotent');
        self::assertSame(1, substr_count($html2, '<title>'), 'Title must appear once');
        self::assertSame(1, substr_count($html2, 'charset="utf-8"'), 'Charset must appear once');
    }
}
