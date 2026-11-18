<?php
declare(strict_types=1);
namespace {{namespace}}\api;
use Damoony\Core\Http\Response;
use Damoony\UI\Page\Page;
use Damoony\UI\Builder;
use Damoony\UI\Node;

final class algoritm extends Page
{
    protected function content(): Node|Response|null
    {
        return Response::json([
            'name' => 'Damoony API',
            'version' => '1.0',
            'status' => 'ok',
            'time' => gmdate('c'),
        ]);    }
}
