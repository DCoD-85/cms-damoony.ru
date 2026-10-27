<?php

declare(strict_types=1);

namespace Damoony\Admin\Controller;

use Damoony\Admin\View;
use Damoony\Http\Request;
use Damoony\Http\Response;
use Damoony\Ui\Builder;
use Damoony\Ui\Message;

final class StubController extends BaseController
{
    public function index(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) return $auth;

        $card = Builder::div('admin-card');
        $card->append(Builder::h(1)->content('Библиотека заглушек'));
        $card->append(Message::info('Здесь будет список HTML-шаблонов для выключенных сайтов (maintenance, coming soon и т.д.). Раздел в разработке.')->build());
        
        $card->append(Builder::a('/admin/?p=domain', 'btn btn-outline')->content('← Назад к доменам'));

        return Response::html($this->view->render($card, ['title' => 'Заглушки']));
    }
}