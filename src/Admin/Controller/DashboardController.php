<?php

declare(strict_types=1);

namespace Damoony\Admin\Controller;

use Damoony\Admin\View;
use Damoony\Http\Request;
use Damoony\Http\Response;

final class DashboardController extends BaseController
{
    public function index(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) {
            return $auth;
        }

        $html = '<div class="admin-card"><h1>Панель управления</h1>';
        $html .= '<p>Вы вошли как <b>' . View::esc($this->auth->login()) . '</b>.</p>';
        $html .= '<p><a href="/admin/?p=logout">Выйти</a></p>';
        $html .= '</div>';

        return Response::html($this->view->render($html, ['title' => 'Панель']));
    }
}