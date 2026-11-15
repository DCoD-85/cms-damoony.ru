<?php

declare(strict_types=1);

namespace damoony_ru;

use Damoony\UI\Region\RegionContext;
use Damoony\UI\Region\RegionInterface;
use Damoony\UI\Builder;
use Damoony\UI\Node;
use Damoony\UI\Components\Button;
use Damoony\UI\Components\Img;
use Damoony\UI\Components\Link;
use Damoony\UI\Components\Input;
use Damoony\UI\Components\Textarea;
use Damoony\UI\Components\Select;
use Damoony\UI\Components\Option;
use Damoony\UI\Components\Label;
use Damoony\UI\Components\Form;
use Damoony\UI\Components\Table;
use Damoony\UI\Components\Thead;
use Damoony\UI\Components\Tbody;
use Damoony\UI\Components\Tr;
use Damoony\UI\Components\Th;
use Damoony\UI\Components\Td;
use Damoony\UI\Text;
use Damoony\UI\UrlSanitizer;

final class blocksglobal_right implements RegionInterface
{
    public function name(): string
    {
        return 'right';
    }

    public function enabled(): bool
    {
        return true;
    }

    public function render(RegionContext $context): Node
    {
        $cssUrl = '/media/' . $context->domain->real . '/' . $context->domain->allias . '/skin/default/css/right.css';
        $context->meta->addCss($cssUrl);

        $content = Builder::div('dt_' . 'right');

        $el1 = Builder::p()->append(Text::raw(strip_tags('Right sidebar', '<a><strong>')));
        $content->append($el1);

        return $content;
    }
}
