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
            $error = View::t('login.error');
        }

        $html = '<div class="admin-card"><h1>' . View::esc(View::t('login.title')) . '</h1>';
        if ($error !== '') {
            $html .= '<div class="error">' . View::esc($error) . '</div>';
        }
        $html .= '<form method="post" class="admin-form">';
        $html .= $this->csrf->field();
        $html .= '<label>' . View::esc(View::t('login.login')) . '<input type="text" name="login" autocomplete="username" required></label>';
        $html .= '<label>' . View::esc(View::t('login.password')) . '<input type="password" name="password" autocomplete="current-password" required></label>';
        $html .= '<button type="submit">' . View::esc(View::t('login.submit')) . '</button>';
        $html .= '</form></div>';

        return Response::html($this->view->render($html, ['title' => View::t('login.title')]));
    }
}
