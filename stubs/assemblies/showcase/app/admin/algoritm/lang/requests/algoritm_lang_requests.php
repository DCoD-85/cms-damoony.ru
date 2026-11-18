<?php

declare(strict_types=1);

namespace {{namespace}}\admin;

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

final class algoritm_lang_requests extends Page
{
    protected function content(): Node|Response|null
    {
        $lang = $this->context->domain->language;
        $domain = $this->context->domain->real;
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $personaBase = $scheme . '://persona.' . $domain;

        $auth = $this->context->service('persona_auth');
        $repo = $this->context->service('persona');
        if (!$auth instanceof PersonaAuth || !$repo instanceof PersonaRepository) {
            return Response::notFound();
        }

        $user = $auth->user();
        if ($user === null) {
            return Response::redirect($personaBase . '/' . $lang . '/');
        }
        if (!$auth->isAdmin((int) $user['id'], 'persona')) {
            return Response::notFound();
        }
        $inviteUrl = null;

        if ($this->isPost()) {
            $requestId = (int) $this->context->request->post('request_id');

            try {
                $approved = (new \Damoony\Data\Persona\PersonaService($repo))->approveRequest(
                    $requestId,
                    $personaBase,
                    'admin',
                );
                $inviteUrl = $approved['invite_url'];
            } catch (\RuntimeException) {
                $inviteUrl = null;
            }
        }

        $wrap = Builder::div('admin-box');
        $wrap->append(Builder::h(1)->content($this->context->lang('admin.requests')));
        $wrap->append(Builder::p()->append(
            Builder::a('/' . $lang . '/')->content($this->context->lang('admin.back')),
        ));

        if ((string) $inviteUrl !== '') {
            $wrap->append(Builder::p('admin-success')->content('Invite: ' . (string) $inviteUrl));
        }

        $requests = $repo->pendingRequests();
        if ($requests === []) {
            $wrap->append(Builder::p()->content($this->context->lang('admin.no_requests')));

            return $wrap;
        }

        $list = Builder::ul('admin-items');
        foreach ($requests as $request) {
            $item = Builder::li();
            $item->append(Builder::span()->content(
                '#' . (string) $request['id'] . ' ' . (string) $request['email'] . ' ' . (string) $request['name'],
            ));

            $form = new Form('admin-action');
            $form->method('POST');
            $csrf = $this->context->service('csrf');
            if ($csrf instanceof Csrf) {
                $form->csrf($csrf);
            }
            $form->add(new Input('hidden', 'request_id', (string) $request['id']));
            $form->add(new Button($this->context->lang('admin.approve')));
            $item->append($form);
            $list->append($item);
        }
        $wrap->append($list);

        return $wrap;    }
}
