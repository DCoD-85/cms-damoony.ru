<?php

declare(strict_types=1);

namespace Damoony\Admin\Pages\superadmin;

use Damoony\UI\Algoritm\Page;
use Damoony\Core\Http\Response;
use Damoony\UI\Node;

final class algoritm_lang_profile_logout extends Page
{
    protected function content(): Node|Response|null
    {
        $auth = $this->context->service('auth');
        if ($auth instanceof \Damoony\Admin\AuthInterface) {
            $auth->logout();
        }
        $lang = (string) ($this->args['lang'] ?? '');

        return Response::redirect('/admin/' . $lang . '/login');
    }
}
