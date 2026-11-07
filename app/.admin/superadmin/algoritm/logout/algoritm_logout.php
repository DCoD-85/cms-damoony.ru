<?php

declare(strict_types=1);

namespace Damoony\Admin\Pages\superadmin;

use Damoony\Algoritm\Page;
use Damoony\Http\Response;
use Damoony\Ui\Node;

final class algoritm_logout extends Page
{
    public function default(): Node|Response|null
    {
        $auth = $this->context->service('auth');
        if ($auth instanceof \Damoony\Admin\AuthInterface) {
            $auth->logout();
        }
        return Response::redirect('/admin/login');
    }
}
