<?php

declare(strict_types=1);

namespace Damoony\Admin\Pages\superadmin;

use Damoony\UI\Algoritm\AdminPage;
use Damoony\Core\Config;
use Damoony\Core\Http\Response;
use Damoony\UI\Node;

final class algoritm extends AdminPage
{
    protected function content(): Node|Response|null
    {
        $adminConfig = $this->context->service('admin_config');
        $defaultLang = $adminConfig instanceof Config
            ? (string) $adminConfig->getByKey(['default_lang'], '')
            : '';
        if ($defaultLang === '') {
            return Response::internalError('admin.config.default_lang not set');
        }

        return Response::redirect('/admin/' . $defaultLang . '/');
    }
}
