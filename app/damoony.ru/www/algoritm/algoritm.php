<?php

declare(strict_types=1);

namespace damoony_ru\www;

use Damoony\Algoritm\Page;
use Damoony\Ui\Builder;
use Damoony\Ui\Node;
use Damoony\Ui\Text;
use Damoony\Ui\UrlSanitizer;
use Damoony\Http\Response;
use Damoony\Files\Paths;
use Damoony\Scheme\SchemeLoader;
use Damoony\Scheme\SchemeRenderer;

final class algoritm extends Page
{
    public function default(): Node|Response|null
    {
        $content = Builder::div('page-content');

        $el1 = Builder::h(1)->append(Text::raw(strip_tags('', '<a><strong>')));
        $content->append($el1);

        $el2 = Builder::p()->append(Text::raw(strip_tags('Параграф444', '<a><strong>')));
        $content->append($el2);

        $this->setContent('center', $content);

        $paths = $this->context->service('paths');
        if (!$paths instanceof Paths) {
            return Response::internalError('Paths service missing');
        }

        $loader = new SchemeLoader($paths);
        $manifest = $loader->load($this->context->domain, $this->context->domain->scheme);
        $renderer = new SchemeRenderer();

        return $renderer->render($manifest, $this->regions(), $this->context);
    }
}
