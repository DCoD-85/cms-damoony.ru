<?php

declare(strict_types=1);

namespace Damoony\Admin\Pages\superadmin;

use Damoony\UI\Algoritm\AdminPage;
use Damoony\Core\Http\Response;
use Damoony\UI\Node;

final class algoritm extends AdminPage
{
    protected function content(): Node|Response|null
    {
        // Если языка нет в URL, ставим 'ru' по умолчанию и редиректим на дашборд
        $lang = $this->args['lang'] ?? 'ru';
        return Response::redirect("/admin/{$lang}/dashboard");
    }
}
