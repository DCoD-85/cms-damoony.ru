<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Region;

use Damoony\Core\Domain\DomainContext;
use Damoony\Core\Http\Request;
use Damoony\UI\Builder;
use Damoony\UI\Region\ContentRegion;
use Damoony\UI\Region\RegionContext;
use PHPUnit\Framework\TestCase;

final class ContentRegionTest extends TestCase
{
    public function testRendersProvidedContent(): void
    {
        $node = Builder::div('test')->content('hello');
        $region = new ContentRegion('center', $node);

        $context = new RegionContext(
            new Request('GET', '/'),
            new DomainContext('example.ru', 'example.ru', 'www', 'default', 'ru-ru', true),
        );

        self::assertSame('center', $region->name());
        self::assertTrue($region->enabled());
        self::assertSame('<div class="test">hello</div>', $region->render($context)->release());
    }
}
