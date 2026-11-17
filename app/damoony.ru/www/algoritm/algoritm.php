<?php
declare(strict_types=1);
namespace damoony_ru\www;
use Damoony\Core\Http\Response;
use Damoony\UI\Page\Page;
use Damoony\UI\Node;

final class algoritm extends Page
{
    protected function content(): Node|Response|null
    {
        // Корень алиаса ведёт на язык проекта по умолчанию (не хардкод «ru»).
        return Response::redirect('/' . $this->context->domain->language . '/', 301);
    }
}
