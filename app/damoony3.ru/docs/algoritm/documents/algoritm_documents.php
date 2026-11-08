<?php

declare(strict_types=1);

namespace damoony3\docs;

use Damoony\UI\Algoritm\Page;
use Damoony\UI\Builder;
use Damoony\UI\Node;
use Damoony\Core\Http\Response;
use Damoony\Core\Files\Paths;
use Damoony\UI\Scheme\SchemeLoader;
use Damoony\UI\Scheme\SchemeRenderer;

final class algoritm_documents extends Page
{
    public function default(): Node|Response|null
    {
        $content = Builder::div('page-content');

        $el = Builder::h(1)->content('Список документов');
        $content->append($el);

        $el = Builder::ul();
        $el->append(Builder::li()->content('Введение'));
        $el->append(Builder::li()->content('Установка'));
        $el->append(Builder::li()->content('Архитектура'));
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
