<?php

declare(strict_types=1);

namespace Damoony\Admin\Pages\superadmin;

use Damoony\Algoritm\Page;
use Damoony\Http\Response;
use Damoony\Ui\Node;

final class algoritm_lang extends Page
{
    public function default(): Node|Response|null
    {
        return Response::redirect('/admin/' . $this->args['lang'] . '/dashboard');
    }
}
