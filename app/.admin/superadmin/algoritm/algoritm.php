<?php

declare(strict_types=1);

namespace Damoony\Admin\Pages\superadmin;

use Damoony\Algoritm\Page;
use Damoony\Http\Response;
use Damoony\Ui\Node;

final class algoritm extends Page
{
    public function default(): Node|Response|null
    {
        // Если языка нет в URL, ставим 'ru' по умолчанию и редиректим на дашборд
        $lang = $this->args['lang'] ?? 'ru';
        return Response::redirect("/admin/{$lang}/dashboard");
    }
}
