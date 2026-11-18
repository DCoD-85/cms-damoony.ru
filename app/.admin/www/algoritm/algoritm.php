<?php

declare(strict_types=1);

namespace admin_server\www;

use Damoony\Core\Http\Response;
use Damoony\UI\Node;
use Damoony\UI\Page\Page;

final class algoritm extends Page
{
    protected function content(): Node|Response|null
    {
        return Response::redirect('/' . $this->context->domain->language . '/', 301);
    }
}
