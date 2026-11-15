<?php

declare(strict_types=1);

namespace damoony_ru\visuo;

use Damoony\UI\Algoritm\Page;
use Damoony\UI\Builder;
use Damoony\UI\Node;
use Damoony\Core\Http\Response;

final class algoritm_lang_dashboard extends Page
{
    protected array $ajaxBlocks = ['top', 'center'];

    protected function content(): Node|Response|null
    {
        // Защита страницы (Только для авторизованных)
        if (!$this->isAuthenticated()) {
            return Response::redirect('/ru/login');
        }

        $user = $this->user();
        $userName = is_object($user) && isset($user->login) ? $user->login : 'Пользователь';

        $wrapper = Builder::div('page-content');
        $wrapper->append(Builder::h(1)->content('Личный кабинет'));
        $wrapper->append(Builder::p()->content('Добро пожаловать, ' . $userName . '!'));
        $wrapper->append(Builder::p()->content('Это защищенная страница, доступная только после входа.'));
        $wrapper->append(Builder::a('/ru/logout')->content('Выйти'));

        return $wrapper;
    }
}
