<?php

declare(strict_types=1);

namespace Damoony\Admin\Pages\superadmin;

use Damoony\UI\Algoritm\Page;
use Damoony\Core\Http\Response;
use Damoony\UI\Node;

final class algoritm_lang extends Page
{
    public function default(): Node|Response|null
    {
        return Response::redirect('/admin/' . $this->args['lang'] . '/dashboard');
    }
}
