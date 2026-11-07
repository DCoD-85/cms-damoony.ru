<?php

declare(strict_types=1);

namespace Damoony\Admin\Pages\superadmin;

use Damoony\Algoritm\Page;
use Damoony\Http\Response;
use Damoony\Ui\Node;
use Damoony\Ui\Builder;
use Damoony\Ui\Components\Card;
use Damoony\Ui\Components\Form;
use Damoony\Ui\Components\Input;
use Damoony\Ui\Components\Button;
use Damoony\Ui\Components\Label;

final class algoritm_login extends Page
{
    public function default(): Node|Response|null
    {
        $this->context->meta->addCss('/media/.admin/superadmin/skin/default/css/main.css', 'admin');

        $auth = $this->context->service('auth');
        $csrf = $this->context->service('csrf');
        
        $error = '';

        if ($this->context->request->isPost()) {
            $login = (string) $this->context->request->post('login');
            $password = (string) $this->context->request->post('password');
            
            if ($auth instanceof \Damoony\Admin\AuthInterface && $auth->attempt($login, $password)) {
                return Response::redirect('/admin/');
            }
            $error = 'Неверный логин или пароль';
        }

        $form = new Form();
        $form->action('/admin/login')->method('POST');
        
        if ($csrf instanceof \Damoony\Security\Csrf) {
            $token = new Input('hidden', '_csrf');
            $token->attrs()->add('value', $csrf->token());
            $form->add($token);
        }

        $form->add(new Label('Логин'));
        $form->add(new Input('text', 'login'));
        
        $form->add(new Label('Пароль'));
        $form->add(new Input('password', 'password'));
        
        $form->add(new Button('Войти'));

        $card = new Card('Вход в админку');
        if ($error !== '') {
            $card->add(Builder::div('error-msg')->content($error));
        }
        $card->add($form);

        $wrapper = Builder::div('login-page-wrapper');
        $wrapper->append($card);

        return $wrapper;
    }
}
