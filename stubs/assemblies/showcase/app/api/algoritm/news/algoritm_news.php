<?php
declare(strict_types=1);
namespace {{namespace}}\api;
use Damoony\Core\Http\Response;
use Damoony\UI\Page\Page;
use Damoony\UI\Builder;
use Damoony\UI\Node;

final class algoritm_news extends Page
{
    protected function content(): Node|Response|null
    {
        $lang = $this->context->domain->language;

        return Response::json([
            'status' => 'ok',
            'lang' => $lang,
            'items' => [
                ['id' => 1, 'slug' => 'first', 'title' => $this->context->lang('news.first_title')],
                ['id' => 2, 'slug' => 'second', 'title' => $this->context->lang('news.second_title')],
                ['id' => 3, 'slug' => 'third', 'title' => $this->context->lang('news.third_title')],
            ],
        ]);    }
}
