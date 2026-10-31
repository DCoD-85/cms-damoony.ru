<?php

declare(strict_types=1);

namespace damoony_ru\doc;

use Damoony\Algoritm\Page;
use Damoony\Auth\AuthInterface;
use Damoony\Http\Response;
use Damoony\Security\Csrf;
use Damoony\Ui\Builder;
use Damoony\Ui\Field;
use Damoony\Ui\Form;
use Damoony\Ui\Message;
use Damoony\Ui\Node;

class algoritm_login extends Page
{
    public function default(): Node|Response|null
    {
        $auth = $this->context->service('auth');
        assert($auth instanceof AuthInterface);

        if ($auth->isAuthenticated()) {
            return Response::redirect('/');
        }

        $csrf = $this->context->service('csrf');
        assert($csrf instanceof Csrf);

        $loginField = Field::text('login')->label('Логин')->required();
        $passField = Field::password('password')->label('Пароль')->required();

        if ($this->isPost()) {
            if (!$csrf->check($this->context->request->post)) {
                $loginField->error('Ошибка безопасности (CSRF). Обновите страницу.');
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
                $loginField->error('Неверный логин или пароль');
                $passField->error('Неверный логин или пароль');
            }
        }

        $form = Form::create('', 'POST')
            ->csrf($csrf->field())
            ->add($loginField)
            ->add($passField)
            ->submit('Войти');

        $wrapper = Builder::div('login-wrapper');
        $wrapper->append(Builder::h(1)->content('Вход'));
        $wrapper->append($form->build());

        $this->context->meta->setTitle('Вход');
        $this->context->meta->addCss('/media/damoony.ru/skin/default/css/login.css');

        return $wrapper;
    }
}