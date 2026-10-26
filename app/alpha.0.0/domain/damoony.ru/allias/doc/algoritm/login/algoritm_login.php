<?php

declare(strict_types=1);

namespace damoony_ru\doc;

use Damoony\Algoritm\Algoritm;
use Damoony\Auth\AuthInterface;
use Damoony\Http\Response;
use Damoony\Security\Csrf;
use Damoony\Ui\Builder;
use Damoony\Ui\Node;
use Damoony\Ui\Text;

class algoritm_login extends Algoritm
{
    public function default(): Node|Response|null
    {
        $auth = $this->context->service('auth');
        assert($auth instanceof AuthInterface);

        if ($auth->isAuthenticated()) {
            return Response::redirect('/');
        }

        $error = '';

        if ($this->isPost()) {
            $csrf = $this->context->service('csrf');
            assert($csrf instanceof Csrf);

            if (!$csrf->check($this->context->request->post)) {
                $error = 'Ошибка безопасности (CSRF). Обновите страницу.';
            } else {
                $login = (string) ($this->context->request->post['login'] ?? '');
                $password = (string) ($this->context->request->post['password'] ?? '');

                if ($auth->login($login, $password)) {
                    $return = (string) ($this->context->request->query('return', '/'));
                    if (!str_starts_with($return, '/')) {
                        $return = '/';
                    }
                    return Response::redirect($return, 302);
                }
                $error = 'Неверный логин или пароль';
            }
        }

        $csrf = $this->context->service('csrf');
        assert($csrf instanceof Csrf);

        $form = Builder::div('login-wrapper');
        $form->append(Builder::h(1)->content('Вход'));

        if ($error !== '') {
            $form->append(Builder::div('login-error')->content($error));
        }

        $inner = Text::raw(
            '<form method="post" class="login-form">'
            . $csrf->field()
            . '<label>Логин<input type="text" name="login" required autofocus></label>'
            . '<label>Пароль<input type="password" name="password" required></label>'
            . '<button type="submit">Войти</button>'
            . '</form>'
        );
        $form->append($inner);

        $this->context->meta->setTitle('Вход');
        $this->context->meta->addCss('/media/damoony.ru/skin/default/css/login.css');

        return $form;
    }
}