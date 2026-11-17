<?php

declare(strict_types=1);

namespace damoony_ru\profile;

use Damoony\Core\Http\Response;
use Damoony\UI\Algoritm\Page;
use Damoony\UI\Node;

final class algoritm extends Page
{
    protected function content(): Node|Response|null
    {
        $lang = $this->args['lang'] ?? '';
        if ($lang === '') {
            return Response::redirect('/ru/');
        }

        return null;
    }
}
