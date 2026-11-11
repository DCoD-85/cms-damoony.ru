<?php

declare(strict_types=1);

namespace damoony_ru\visuo;

use Damoony\UI\Algoritm\Page;
use Damoony\Core\Http\Response;
use Damoony\Data\Auth\AuthInterface;

final class algoritm_lang_logout extends Page
{
    protected function content(): Response
    {
        $auth = $this->context->service('auth');
        if ($auth instanceof AuthInterface) {
            $auth->logout();
        }
        return Response::redirect('/ru/');
    }
}
