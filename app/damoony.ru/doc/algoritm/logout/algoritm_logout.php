<?php

declare(strict_types=1);

namespace damoony_ru\doc;

use Damoony\UI\Algoritm\Page;
use Damoony\Data\Auth\AuthInterface;
use Damoony\Core\Http\Response;
use Damoony\UI\Node;

class algoritm_logout extends Page
{
    protected function content(): Node|Response|null
    {
        $auth = $this->context->service('auth');
        assert($auth instanceof AuthInterface);
        $auth->logout();

        return Response::redirect('/login', 302);
    }
}