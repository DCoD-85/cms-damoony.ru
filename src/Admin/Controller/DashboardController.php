<?php

declare(strict_types=1);

namespace Damoony\Admin\Controller;

use Damoony\Admin\View;
use Damoony\Core\Config;
use Damoony\Http\Request;
use Damoony\Http\Response;
use Damoony\Ui\Builder;

final class DashboardController extends BaseController
{
    public function __construct(
        \Damoony\Admin\AuthInterface $auth,
        \Damoony\Security\Csrf $csrf,
        View $view,
        private readonly Config $siteConfig,
    ) {
        parent::__construct($auth, $csrf, $view);
    }

    public function index(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) {
            return $auth;
        }

        $domains = $this->siteConfig->getByKey(['domains_real'], []);
        $count = is_array($domains) ? count($domains) : 0;

        $card = Builder::div('admin-card');
        $card->append(Builder::h(1)->content('Панель управления'));
        $card->append(Builder::p()->content('Вы вошли как ' . View::esc($this->auth->login())));
        $card->append(Builder::p()->content('Доменов в конфигурации: ' . $count));

        $actions = Builder::div('page-actions');
        $actions->append(Builder::a('/admin/?p=domain', 'btn btn-primary')->content('Управление доменами'));
        $card->append($actions);

        return Response::html($this->view->render($card, ['title' => 'Панель']));
    }
}
