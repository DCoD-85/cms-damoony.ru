<?php
declare(strict_types=1);
namespace {{namespace}}\{{alias}};
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

        if ($this->isPost()) {
            $login = (string) $this->context->request->post('login');
            $password = (string) $this->context->request->post('password');
            
            if ($auth instanceof \Damoony\Data\Auth\AuthInterface && $auth->login($login, $password)) {
                return Response::redirect('/' . $this->context->domain->language . '/profile/');
            }
            // Ошибка через flash → в AJAX прилетит как toast
            $flash = $this->context->service('flash');
            if ($flash instanceof \Damoony\Core\Flash) {
                $flash->add('error', $this->context->lang('auth.error'));
            }
            // При нативном POST (без JS) — рендерим форму заново, тост не нужен
            if (!$this->isAjax()) {
                return Response::redirect($this->context->request->path);
            }
        }

        $form = new Form();
        $form->method('POST');
        $csrf = $this->context->service('csrf');
        if ($csrf instanceof Csrf) { $form->csrf($csrf); }
        
        $loginInput = new Input('text', 'login');
        $loginInput->attrs()->add('placeholder', $this->context->lang('auth.login'));
        $form->add($loginInput);

        $passInput = new Input('password', 'password');
        $passInput->attrs()->add('placeholder', $this->context->lang('auth.password'));
        $form->add($passInput);
        $form->add(new Button($this->context->lang('auth.submit')));

        $wrap = Builder::div('auth-box');
        $wrap->append(Builder::h(1)->content($this->context->lang('auth.title')));
        $wrap->append($form);
        return $wrap;
    }
}