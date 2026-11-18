<?php
declare(strict_types=1);
namespace {{namespace}}\api;
use Damoony\Core\Http\Response;
use Damoony\UI\Page\Page;
use Damoony\UI\Builder;
use Damoony\UI\Node;

final class algoritm_health extends Page
{
    protected function content(): Node|Response|null
    {
        return Response::json([
            'status' => 'ok',
            'time' => gmdate('c'),
            'php' => PHP_VERSION,
        ]);    }
}
