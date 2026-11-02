<?php

declare(strict_types=1);

namespace damoony3\docs;

use Damoony\Algoritm\Page;
use Damoony\Ui\Builder;
use Damoony\Ui\Node;
use Damoony\Http\Response;
use Damoony\Files\Paths;
use Damoony\Scheme\SchemeLoader;
use Damoony\Scheme\SchemeRenderer;

final class algoritm extends Page
{
    public function default(): Node|Response|null
    {
        $content = Builder::div('page-content');

        $el = Builder::h(1)->content('Документация');
        $content->append($el);

        $el = Builder::p()->content('Выберите раздел в меню слева.');
        $content->append($el);

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
