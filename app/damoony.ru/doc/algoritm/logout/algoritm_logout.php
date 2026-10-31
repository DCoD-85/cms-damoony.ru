<?php

declare(strict_types=1);

namespace damoony_ru\doc;

use Damoony\Algoritm\Page;
use Damoony\Auth\AuthInterface;
use Damoony\Http\Response;
use Damoony\Ui\Node;

class algoritm_logout extends Page
{
    public function default(): Node|Response|null
    {
        $auth = $this->context->service('auth');
        assert($auth instanceof AuthInterface);
        $auth->logout();

        return Response::redirect('/login', 302);
    }
}