<?php

declare(strict_types=1);

namespace damoony_ru\visuo;

use Damoony\UI\Algoritm\Page;
use Damoony\UI\Builder;
use Damoony\UI\Node;
use Damoony\Core\Http\Response;
use Damoony\UI\Response\MinStruct;
use Damoony\UI\Components\Form;
use Damoony\UI\Components\Input;
use Damoony\UI\Components\Label;
use Damoony\UI\Components\Button;
use Damoony\Data\Auth\AuthInterface;
use Damoony\Core\Security\Csrf;

final class algoritm_lang_login extends Page
{
    protected array $ajaxBlocks = ['top', 'center'];

    protected function content(): Node|Response|null
    {
        $auth = $this->context->service('auth');
        if ($auth instanceof AuthInterface && $auth->isAuthenticated()) {
            return Response::redirect('/ru/dashboard');
        }

        $csrf = $this->context->service('csrf');

        if ($this->isPost()) {
            if (!$csrf instanceof Csrf || !$csrf->check($this->context->request->post)) {
                return (new MinStruct())->error(true)->messages('<div>Ошибка CSRF</div>')->toResponse();
            }

            $login = (string) $this->context->request->post('login', '');
            $password = (string) $this->context->request->post('password', '');

            if ($auth instanceof AuthInterface && $auth->login($login, $password)) {
                return (new MinStruct())
                    ->messages('<div>Успешный вход!</div>')
                    ->form(true, '/ru/dashboard', 500)
                    ->toResponse();
            }

            return (new MinStruct())
                ->error(true)
                ->messages('<div>Неверный логин или пароль</div>')
                ->toResponse();
        }

        $form = new Form();
        $form->action('/ru/login')->method('POST');

        if ($csrf instanceof Csrf) {
            $token = new Input('hidden', '_csrf');
            $token->attrs()->add('value', $csrf->token());
            $form->add($token);
        }

        $form->add(new Label('Логин'));
        $form->add(new Input('text', 'login'));
        $form->add(new Label('Пароль'));
        $form->add(new Input('password', 'password'));
        $form->add(new Button('Войти'));

        $wrapper = Builder::div('page-content');
        $wrapper->append(Builder::h(1)->content('Вход в личный кабинет'));
        $wrapper->append($form);

        return $wrapper;
    }
}
