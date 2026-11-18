<?php

declare(strict_types=1);

namespace {{namespace}}\docs;

use Damoony\Core\Http\Response;
use Damoony\UI\Page\Page;
use Damoony\UI\Builder;
use Damoony\UI\Node;
use Damoony\UI\Text;

final class algoritm_lang_observability extends Page
{
    protected function content(): Node|Response|null
    {
        $lang = $this->context->domain->language;
        $paths = $this->context->service('paths');

        $file = $paths instanceof \Damoony\Core\Files\Paths
            ? $paths->allias($this->context->domain->real, 'docs') . '/.docs/observability.md'
            : '';

        if ($file === '' || !is_file($file)) {
            return Response::notFound();
        }

        $wrap = Builder::div('docs-article');
        $wrap->append(Text::raw(\Damoony\Core\Support\Markdown::render((string) file_get_contents($file))));
        $wrap->append(Builder::p('docs-back')->append(
            Builder::a('/' . $lang . '/')->content($this->context->lang('docs.back')),
        ));

        return $wrap;    }
}
