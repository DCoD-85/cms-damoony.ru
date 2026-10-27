<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Region;

use Damoony\Domain\DomainContext;
use Damoony\Http\Request;
use Damoony\Region\ContentRegion;
use Damoony\Region\RegionContext;
use Damoony\Ui\Builder;
use PHPUnit\Framework\TestCase;

final class ContentRegionTest extends TestCase
{
    public function testRendersProvidedContent(): void
    {
        $node = Builder::div('test')->content('hello');
        $region = new ContentRegion('center', $node);

        $context = new RegionContext(
            new Request('GET', '/'),
            new DomainContext('example.ru', 'example.ru', 'www', 'alpha.0.0', 'default', 'ru-ru', true),
        );

        self::assertSame('center', $region->name());
        self::assertTrue($region->enabled());
        self::assertSame('<div class="test">hello</div>', $region->render($context)->release());
    }
}
