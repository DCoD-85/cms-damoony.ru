<?php

declare(strict_types=1);

namespace Damoony\Admin\Pages\superadmin;

use Damoony\UI\Algoritm\AdminPage;
use Damoony\Core\Http\Response;
use Damoony\UI\Node;

final class algoritm_lang extends AdminPage
{
    protected function content(): Node|Response|null
    {
        return Response::redirect('/admin/' . $this->args['lang'] . '/dashboard');
    }
}
