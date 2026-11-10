<?php

declare(strict_types=1);

namespace damoony_ru;

use Damoony\UI\Region\RegionContext;
use Damoony\UI\Region\RegionInterface;
use Damoony\UI\Builder;
use Damoony\UI\Node;
use Damoony\UI\Text;
use Damoony\UI\UrlSanitizer;

final class alliasglobal_footer implements RegionInterface
{
    public function name(): string
    {
        return 'footer';
    }

    public function enabled(): bool
    {
        return true;
    }

    public function render(RegionContext $context): Node
    {
        $cssUrl = '/media/' . $context->domain->real . '/' . $context->domain->allias . '/skin/default/css/footer.css';
        $context->meta->addCss($cssUrl);

        $content = Builder::div('footer');

        $el1 = Builder::p()->append(Text::raw(strip_tags('© 2026 Damoony Engine. Все права защищены.', '<a><strong>')));
        $content->append($el1);

        return $content;
    }
}
