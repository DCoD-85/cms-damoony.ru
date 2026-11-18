<?php

declare(strict_types=1);

namespace {{namespace}}\persona;

use Damoony\Core\Http\Response;
use Damoony\Core\Security\Csrf;
use Damoony\Data\Persona\PersonaAuth;
use Damoony\Data\Persona\PersonaRepository;
use Damoony\UI\Page\Page;
use Damoony\UI\Builder;
use Damoony\UI\Node;
use Damoony\UI\Components\Button;
use Damoony\UI\Components\Form;
use Damoony\UI\Components\Input;

final class algoritm_lang_login extends Page
{
    protected function content(): Node|Response|null
    {
        $lang = $this->context->domain->language;
        $auth = $this->context->service('persona_auth');
        if (!$auth instanceof PersonaAuth) {
            return Response::notFound();
        }

        if ($auth->user() !== null) {
            return Response::redirect('/' . $lang . '/profile/');
        }

        if ($this->isPost()) {
            $email = (string) $this->context->request->post('email');
            $password = (string) $this->context->request->post('password');

            if ($auth->login($email, $password)) {
                return Response::redirect('/' . $lang . '/profile/');
            }

            $flash = $this->context->service('flash');
            if ($flash instanceof \Damoony\Core\Flash) {
                $flash->add('error', $this->context->lang('persona.error_login'));
            }

            return Response::redirect('/' . $lang . '/');
        }

        $wrap = Builder::div('persona-box');
        $wrap->append(Builder::h(1)->content($this->context->lang('persona.login')));
        $wrap->append(Builder::p()->append(
            Builder::a('/' . $lang . '/')->content($this->context->lang('persona.back')),
        ));

        return $wrap;    }
}
