<?php
declare(strict_types=1);
namespace {{namespace}}\news;
use Damoony\Core\Http\Response;
use Damoony\UI\Page\Page;
use Damoony\UI\Builder;
use Damoony\UI\Node;

final class algoritm extends Page
{
    protected function content(): Node|Response|null
    {
        return Response::redirect('/' . $this->context->domain->language . '/', 301);    }
}
