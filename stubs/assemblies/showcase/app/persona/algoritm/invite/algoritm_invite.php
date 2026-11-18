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

final class algoritm_invite extends Page
{
    protected function content(): Node|Response|null
    {
        $lang = $this->context->domain->language;
        $repo = $this->context->service('persona');
        if (!$repo instanceof PersonaRepository) {
            return Response::notFound();
        }

        $token = (string) ($this->args['token'] ?? '');
        $invite = $token === '' ? [] : $repo->inviteByToken(\Damoony\Cli\Support\PersonaTokens::hash($token));
        $valid = $invite !== [] && strtotime((string) $invite['expires_at']) >= time();

        if ($this->isPost()) {
            $password = (string) $this->context->request->post('password');
            $password2 = (string) $this->context->request->post('password2');

            if (!$valid) {
                return Response::notFound();
            }
            if (strlen($password) < 8) {
                return Response::redirect('/invite/' . $token . '?error=short');
            }
            if ($password !== $password2) {
                return Response::redirect('/invite/' . $token . '?error=match');
            }

            $accountId = (int) $invite['account_id'];
            $repo->setAccountPassword($accountId, password_hash($password, PASSWORD_DEFAULT));
            $repo->consumeInvite((int) $invite['id']);

            return Response::redirect('/' . $lang . '/');
        }

        $wrap = Builder::div('persona-box');
        $wrap->append(Builder::h(1)->content($this->context->lang('persona.invite_title')));

        if (!$valid) {
            $wrap->append(Builder::p('persona-error')->content($this->context->lang('persona.invite_invalid')));

            return $wrap;
        }

        $form = new Form();
        $form->method('POST');
        $csrf = $this->context->service('csrf');
        if ($csrf instanceof Csrf) {
            $form->csrf($csrf);
        }
        $form->add(new Input('password', 'password'));
        $form->add(new Input('password', 'password2'));
        $form->add(new Button($this->context->lang('persona.invite_submit')));
        $wrap->append($form);

        return $wrap;    }
}
