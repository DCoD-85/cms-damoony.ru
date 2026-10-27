<?php

declare(strict_types=1);

namespace Damoony\Admin\Controller;

use Damoony\Admin\View;
use Damoony\Http\Request;
use Damoony\Http\Response;
use Damoony\Ui\Builder;
use Damoony\Ui\Message;

final class AliasSchemeController extends BaseController
{
    public function index(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) {
            return $auth;
        }

        $domain = (string) $request->query('domain', '');
        $allias = (string) $request->query('allias', '');
        if ($domain === '' || $allias === '') {
            return $this->redirect('/admin/?p=domain');
        }

        $card = Builder::div('admin-card');

        $breadcrumbs = Builder::div('breadcrumbs');
        $breadcrumbs->append(Builder::a('/admin/?p=domain')->content('Домены'));
        $breadcrumbs->append(\Damoony\Ui\Text::raw(' / '));
        $breadcrumbs->append(Builder::a('/admin/?p=domain:edit&domain=' . urlencode($domain))->content($domain));
        $breadcrumbs->append(\Damoony\Ui\Text::raw(' / '));
        $breadcrumbs->append(Builder::a('/admin/?p=allias&domain=' . urlencode($domain))->content('Алиасы'));
        $card->append($breadcrumbs);

        $card->append(Builder::h(1)->content('Схемы алиаса: ' . View::esc($allias)));
        $card->append(View::alliasTabs($domain, $allias, 'schemes'));

        $card->append(Message::info('Здесь будут локальные схемы для этого алиаса. Если локальная схема не выбрана, используется глобальная схема домена.')->build());

        return Response::html($this->view->render($card, ['title' => 'Схемы алиаса']));
    }
}
