<?php

declare(strict_types=1);

namespace Damoony\Admin\Controller;

use Damoony\Admin\AuthInterface;
use Damoony\Admin\View;
use Damoony\Http\Request;
use Damoony\Http\Response;
use Damoony\Security\Csrf;

final class LoginController extends BaseController
{
    public function __construct(
        AuthInterface $auth,
        Csrf $csrf,
        View $view,
    ) {
        parent::__construct($auth, $csrf, $view);
    }

    public function index(Request $request): Response
    {
        if ($this->auth->isLoggedIn()) {
            return $this->redirect('/admin/');
        }

        $error = '';

        if ($request->isPost()) {
            $csrfErr = $this->requireCsrf($request);
            if ($csrfErr !== null) {
                return $csrfErr;
            }

            $login = (string) ($request->post['login'] ?? '');
            $password = (string) ($request->post['password'] ?? '');

            if ($this->auth->attempt($login, $password)) {
                return $this->redirect('/admin/');
            }
            $error = 'Неверный логин или пароль';
        }

        $html = '<div class="admin-card"><h1>Вход</h1>';
        if ($error !== '') {
            $html .= '<div class="error">' . View::esc($error) . '</div>';
        }
        $html .= '<form method="post" class="admin-form">';
        $html .= $this->csrf->field();
        $html .= '<label>Логин<input type="text" name="login" autocomplete="username" required></label>';
        $html .= '<label>Пароль<input type="password" name="password" autocomplete="current-password" required></label>';
        $html .= '<button type="submit">Войти</button>';
        $html .= '</form></div>';

        return Response::html($this->view->render($html, ['title' => 'Вход']));
    }
}