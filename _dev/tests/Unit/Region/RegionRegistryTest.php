<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Region;

use Damoony\Domain\DomainContext;
use Damoony\Http\Request;
use Damoony\Region\RegionContext;
use Damoony\Region\RegionInterface;
use Damoony\Region\RegionRegistry;
use Damoony\Ui\Element;
use Damoony\Ui\Node;
use PHPUnit\Framework\TestCase;

final class RegionRegistryTest extends TestCase
{
    private function region(string $name, bool $enabled = true): RegionInterface
    {
        return new class($name, $enabled) implements RegionInterface {
            public function __construct(private string $n, private bool $e)
            {
            }

            public function name(): string
            {
                return $this->n;
            }

            public function enabled(): bool
            {
                return $this->e;
            }

            public function render(RegionContext $context): Node
            {
                $el = new Element('div');
                $el->attrs()->classAdd('region-' . $this->n);
                $el->content($context->domain->allias);
                return $el;
            }
        };
    }

    public function testAddGetHas(): void
    {
        $r = new RegionRegistry();
        $top = $this->region('top');

        $r->add($top);

        self::assertTrue($r->has('top'));
        self::assertSame($top, $r->get('top'));
        self::assertFalse($r->has('missing'));
        self::assertNull($r->get('missing'));
    }

    public function testNames(): void
    {
        $r = new RegionRegistry();
        $r->add($this->region('top'))->add($this->region('left'))->add($this->region('center'));

        self::assertSame(['top', 'left', 'center'], $r->names());
    }

    public function testRender(): void
    {
        $ctx = new RegionContext(
            new Request('GET', '/'),
            new DomainContext('example.ru', 'example.ru', 'www', 'alpha.0.0', 'default', 'ru-ru', true),
        );

        $out = $this->region('top')->render($ctx);
        self::assertSame('<div class="region-top">www</div>', $out->release());
    }
}