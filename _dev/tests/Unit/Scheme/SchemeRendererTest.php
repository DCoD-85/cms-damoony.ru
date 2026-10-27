<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Scheme;

use Damoony\Domain\DomainContext;
use Damoony\Http\Request;
use Damoony\Region\RegionContext;
use Damoony\Region\RegionInterface;
use Damoony\Region\RegionRegistry;
use Damoony\Scheme\SchemeRenderer;
use Damoony\Ui\Element;
use Damoony\Ui\Node;
use PHPUnit\Framework\TestCase;

final class SchemeRendererTest extends TestCase
{
    private function region(string $name): RegionInterface
    {
        return new class ($name) implements RegionInterface {
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
                $el->attrs()->classAdd('region-' . $this->n);
                $el->content('content-' . $this->n);
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

    public function testRendersSimpleSkeleton(): void
    {
        $r = new SchemeRenderer();
        $html = $r->render(['tag' => 'div', 'class' => 'wrap'], new RegionRegistry(), $this->ctx())->release();
        self::assertSame('<div class="wrap"></div>', $html);
    }

    public function testRenderChildren(): void
    {
        $r = new SchemeRenderer();
        $skeleton = [
            'tag' => 'div',
            'class' => 'wrap',
            'children' => [
                ['tag' => 'span', 'class' => 'a'],
                ['tag' => 'span', 'class' => 'b'],
            ],
        ];

        $html = $r->render($skeleton, new RegionRegistry(), $this->ctx())->release();
        self::assertStringContainsString('<span class="a"></span>', $html);
        self::assertStringContainsString('<span class="b"></span>', $html);
    }

    public function testReplacesSlotWithRegion(): void
    {
        $regions = new RegionRegistry();
        $regions->add($this->region('center'));

        $r = new SchemeRenderer();
        $skeleton = [
            'tag' => 'div',
            'class' => 'layout',
            'children' => [
                ['tag' => 'main', 'slot' => 'center'],
            ],
        ];

        $html = $r->render($skeleton, $regions, $this->ctx())->release();
        self::assertStringContainsString('<div class="region-center">content-center</div>', $html);
    }

    public function testMissingRegionRemovesSlot(): void
    {
        $r = new SchemeRenderer();
        $skeleton = [
            'tag' => 'div',
            'children' => [
                ['tag' => 'main', 'slot' => 'ghost'],
            ],
        ];

        $html = $r->render($skeleton, new RegionRegistry(), $this->ctx())->release();
        self::assertSame('<div><main></main></div>', $html);
    }
}
