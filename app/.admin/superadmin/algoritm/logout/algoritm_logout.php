<?php

declare(strict_types=1);

namespace Damoony\Admin\Pages\superadmin;

use Damoony\UI\Algoritm\Page;
use Damoony\Core\Http\Response;
use Damoony\UI\Node;

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
