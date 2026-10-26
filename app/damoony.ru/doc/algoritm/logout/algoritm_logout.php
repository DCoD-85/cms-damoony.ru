<?php

declare(strict_types=1);

namespace damoony_ru\doc;

use Damoony\Algoritm\Algoritm;
use Damoony\Auth\AuthInterface;
use Damoony\Http\Response;
use Damoony\Ui\Node;

class algoritm_logout extends Algoritm
{
    public function default(): Node|Response|null
    {
        $auth = $this->context->service('auth');
        assert($auth instanceof AuthInterface);
        $auth->logout();

        return Response::redirect('/login', 302);
    }
}