<?php
declare(strict_types=1);
namespace damoony4_ru\www;
use Damoony\Core\Http\Response;
use Damoony\UI\Algoritm\Page;
use Damoony\UI\Node;

final class algoritm extends Page
{
    protected function content(): Node|Response|null
    {
        return Response::redirect('/ru/');
    }
}