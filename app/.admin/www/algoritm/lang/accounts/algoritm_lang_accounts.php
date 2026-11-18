<?php

declare(strict_types=1);

namespace admin_server\www;

use Damoony\Admin\Page\AdminPage;
use Damoony\Core\Http\Response;
use Damoony\UI\Builder;
use Damoony\UI\Node;

final class algoritm_lang_accounts extends AdminPage
{
    protected function content(): Node|Response|null
    {
        $session = $this->adminSession();
        if ($session === null) {
            return Response::notFound();
        }

        $lang = $this->context->domain->language;
        $wrap = Builder::div('admin-box');
        $wrap->append(Builder::h(1)->content('Аккаунты'));
        $wrap->append(Builder::p()->content('Раздел в разработке: список админов, роли, сброс пароля.'));
        $wrap->append(Builder::p()->append(
            Builder::a('/' . $lang . '/')->content('← Назад'),
        ));

        return $wrap;
    }
}
