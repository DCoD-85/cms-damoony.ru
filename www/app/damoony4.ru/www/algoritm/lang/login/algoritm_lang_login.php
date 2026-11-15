<?php
declare(strict_types=1);
namespace damoony4_ru\www;
use Damoony\Core\Http\Response;
use Damoony\UI\Algoritm\Page;
use Damoony\UI\Builder;
use Damoony\UI\Node;
use Damoony\UI\Components\Form;
use Damoony\UI\Components\Input;
use Damoony\UI\Components\Button;
use Damoony\Core\Security\Csrf;

final class algoritm_lang_login extends Page
{
    protected function content(): Node|Response|null
    {
        $auth = $this->context->service('auth');
        if ($auth instanceof \Damoony\Data\Auth\AuthInterface && $auth->isAuthenticated()) {
            return Response::redirect('/' . $this->context->domain->language . '/profile/');
        }

        $error = '';
        if ($this->isPost()) {
            $login = (string) $this->context->request->post('login');
            $password = (string) $this->context->request->post('password');
            
            if ($auth instanceof \Damoony\Data\Auth\AuthInterface && $auth->login($login, $password)) {
                return Response::redirect('/' . $this->context->domain->language . '/profile/');
            }
            $error = $this->context->lang('auth.error');
        }

        $form = new Form();
        $form->method('POST');
        $csrf = $this->context->service('csrf');
        if ($csrf instanceof Csrf) { $form->csrf($csrf); }
        
        $form->add(new Input('text', 'login', '', $this->context->lang('auth.login')));
        $form->add(new Input('password', 'password', '', $this->context->lang('auth.password')));
        $form->add(new Button($this->context->lang('auth.submit')));

        $wrap = Builder::div('auth-box');
        $wrap->append(Builder::h(1)->content($this->context->lang('auth.title')));
        if ($error) { $wrap->append(Builder::div('error')->content($error)); }
        $wrap->append($form);
        return $wrap;
    }
}