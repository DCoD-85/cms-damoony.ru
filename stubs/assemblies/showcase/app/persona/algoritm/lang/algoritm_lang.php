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

final class algoritm_lang extends Page
{
    protected function content(): Node|Response|null
    {
        $lang = $this->context->domain->language;
        $auth = $this->context->service('persona_auth');
        $user = $auth instanceof PersonaAuth ? $auth->user() : null;

        $wrap = Builder::div('persona-box');

        if ($user !== null) {
            $wrap->append(Builder::h(1)->content($this->context->lang('persona.dashboard')));
            $wrap->append(Builder::p()->content($this->context->lang('persona.logged_as', ['email' => (string) $user['email']])));
            $wrap->append(Builder::p()->append(
                Builder::a('/' . $lang . '/profile/')->content($this->context->lang('persona.profile')),
            ));

            if ($auth instanceof PersonaAuth && $auth->isAdmin((int) $user['id'], 'persona')) {
                $wrap->append(Builder::p()->append(
                    Builder::a('/' . $lang . '/admin/')->content($this->context->lang('persona.admin')),
                ));
            }

            $wrap->append(Builder::p()->append(
                Builder::a('/' . $lang . '/logout/')->content($this->context->lang('persona.logout')),
            ));

            return $wrap;
        }

        $wrap->append(Builder::h(1)->content($this->context->lang('persona.title')));
        $wrap->append(Builder::p('persona-intro')->content($this->context->lang('persona.intro')));

        $form = new Form();
        $form->method('POST');
        $form->action('/' . $lang . '/login/');
        $csrf = $this->context->service('csrf');
        if ($csrf instanceof Csrf) {
            $form->csrf($csrf);
        }
        $form->add(new Input('email', 'email'));
        $form->add(new Input('password', 'password'));
        $form->add(new Button($this->context->lang('persona.login')));

        $wrap->append($form);
        $wrap->append(Builder::p()->append(
            Builder::a('/' . $lang . '/request/')->content($this->context->lang('persona.request_link')),
        ));

        return $wrap;    }
}
