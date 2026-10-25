<?php

declare(strict_types=1);

namespace Damoony\Admin\Controller;

use Damoony\Admin\View;
use Damoony\Core\Config;
use Damoony\Http\Request;
use Damoony\Http\Response;

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
        if ($auth !== null) return $auth;

        $domains = $this->siteConfig->getByKey(['domains_real'], []);
        $count = is_array($domains) ? count($domains) : 0;

        $html = '<div class="admin-card"><h1>Панель управления</h1>';
        $html .= '<p>Вы вошли как <b>' . View::esc($this->auth->login()) . '</b>.</p>';
        $html .= '<p>Доменов в конфигурации: <b>' . $count . '</b></p>';
        
        $html .= '<div style="margin-top:24px; display:flex; gap:12px;">';
        $html .= '<a href="/admin/?p=domain" class="btn" style="padding:10px 16px; background:#0071e3; color:#fff; text-decoration:none; border-radius:6px;">Управление доменами</a>';
        $html .= '</div>';
        
        $html .= '<p style="margin-top:32px;"><a href="/admin/?p=logout" style="color:#c00;">Выйти</a></p>';
        $html .= '</div>';

        return Response::html($this->view->render($html, ['title' => 'Панель']));
    }
}