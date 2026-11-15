<?php

declare(strict_types=1);

namespace Damoony\Admin\Pages\superadmin;

use Damoony\Core\Http\Response;
use Damoony\UI\Algoritm\AdminPage;
use Damoony\UI\Builder;
use Damoony\UI\Node;

final class algoritm_lang_profile extends AdminPage
{
    protected function content(): Node|Response|null
    {
        return Builder::div('admin-profile')->content('Это страница профиля');
    }
}
