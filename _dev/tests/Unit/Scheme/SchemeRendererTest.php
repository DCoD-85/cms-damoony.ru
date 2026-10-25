<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Scheme;

use Damoony\Domain\DomainContext;
use Damoony\Http\Request;
use Damoony\Region\RegionContext;
use Damoony\Region\RegionInterface;
use Damoony\Region\RegionRegistry;
use Damoony\Scheme\SchemeNode;
use Damoony\Scheme\SchemeRenderer;
use Damoony\Ui\Element;
use Damoony\Ui\Node;
use PHPUnit\Framework\TestCase;

final class SchemeRendererTest extends TestCase
{
    private function region(string $name): RegionInterface
    {
        return new class($name) implements RegionInterface {
            public function __construct(private string $n)
            {
            }

            public function name(): string
            {
                return $this->n;
            }

            public function enabled(): bool
            {
                return true;
            }

            public function render(RegionContext $context): Node
            {
                $el = new Element('div');
                $el->attrs()->classAdd('region');
                $el->content($this->n);
                return $el;
            }
        };
    }

    private function ctx(): RegionContext
    {
        return new RegionContext(
            new Request('GET', '/'),
            new DomainContext('example.ru', 'example.ru', 'www', 'alpha.0.0', 'default', 'ru-ru', true),
        );
    }

    public function testNodeFromArray(): void
    {
        $n = SchemeNode::fromArray([
            'tag' => 'section',
            'class' => 'main',
            'layout' => 'horizontal',
            'children' => [
                ['tag' => 'div', 'class' => 'left'],
                'ignored',
            ],
        ]);

        self::assertSame('section', $n->tag);
        self::assertSame('main', $n->class);
        self::assertSame('horizontal', $n->layout);
        self::assertCount(1, $n->children);
    }

    public function testRenderSimple(): void
    {
        $r = new SchemeRenderer(new RegionRegistry());
        $node = SchemeNode::fromArray(['tag' => 'div', 'class' => 'wrap']);

        self::assertSame('<div class="wrap" data-layout="vertical"></div>', $r->render($node, $this->ctx())->release());
    }

    public function testRenderChildren(): void
    {
        $r = new SchemeRenderer(new RegionRegistry());
        $node = SchemeNode::fromArray([
            'tag' => 'div',
            'class' => 'wrap',
            'children' => [
                ['tag' => 'span', 'class' => 'a'],
                ['tag' => 'span', 'class' => 'b'],
            ],
        ]);

        self::assertSame(
            '<div class="wrap" data-layout="vertical"><span class="a" data-layout="vertical"></span><span class="b" data-layout="vertical"></span></div>',
            $r->render($node, $this->ctx())->release(),
        );
    }

    public function testDisabledChildSkipped(): void
    {
        $r = new SchemeRenderer(new RegionRegistry());
        $node = SchemeNode::fromArray([
            'children' => [
                ['tag' => 'span', 'enabled' => false],
                ['tag' => 'span', 'class' => 'ok'],
            ],
        ]);

        self::assertStringContainsString('<span class="ok"', $r->render($node, $this->ctx())->release());
        self::assertStringNotContainsString('enabled', $r->render($node, $this->ctx())->release());
    }

    public function testRegionOverride(): void
    {
        $regions = new RegionRegistry();
        $regions->add($this->region('center'));

        $r = new SchemeRenderer($regions);
        $node = SchemeNode::fromArray(['tag' => 'main', 'class' => 'center']);

        self::assertSame(
            '<main class="center" data-layout="vertical"><div class="region">center</div></main>',
            $r->render($node, $this->ctx())->release(),
        );
    }

    public function testReturnChild(): void
    {
        $r = new SchemeRenderer(new RegionRegistry());
        $node = SchemeNode::fromArray([
            'tag' => 'div',
            'children' => [
                ['tag' => 'section', 'class' => 'ret', 'return' => true],
            ],
        ]);

        self::assertSame('<section class="ret" data-layout="vertical"></section>', $r->render($node, $this->ctx())->release());
    }
}