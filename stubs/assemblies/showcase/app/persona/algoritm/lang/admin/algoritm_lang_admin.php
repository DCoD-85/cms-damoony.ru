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

final class algoritm_lang_admin extends Page
{
    protected function content(): Node|Response|null
    {
        $lang = $this->context->domain->language;
        $auth = $this->context->service('persona_auth');
        $repo = $this->context->service('persona');
        if (!$auth instanceof PersonaAuth || !$repo instanceof PersonaRepository) {
            return Response::notFound();
        }

        $user = $auth->user();
        if ($user === null || !$auth->isAdmin((int) $user['id'], 'persona')) {
            return Response::notFound();
        }

        $inviteUrl = null;

        if ($this->isPost()) {
            $requestId = (int) $this->context->request->post('request_id');
            $request = $repo->requestById($requestId);

            if ($request !== [] && ($request['status'] ?? '') === 'pending') {
                $account = $repo->accountByEmail((string) $request['email']);
                $accountId = $account === []
                    ? $repo->createAccount((string) $request['email'], null, 'pending')
                    : (int) $account['id'];

                $token = \Damoony\Cli\Support\PersonaTokens::generate();
                $repo->createInvite($accountId, \Damoony\Cli\Support\PersonaTokens::hash($token), \Damoony\Cli\Support\PersonaTokens::expiresAt(48));
                $repo->decideRequest($requestId, 'approved', 'persona-admin');
                $inviteUrl = 'https://' . $this->context->domain->domain . '/invite/' . $token;
            }
        }

        $wrap = Builder::div('persona-box');
        $wrap->append(Builder::h(1)->content($this->context->lang('persona.admin')));
        $wrap->append(Builder::p()->append(
            Builder::a('/' . $lang . '/')->content($this->context->lang('persona.back')),
        ));

        if ($inviteUrl !== null) {
            $wrap->append(Builder::p('persona-success')->content('Invite: ' . $inviteUrl));
        }

        $requests = $repo->pendingRequests();
        if ($requests === []) {
            $wrap->append(Builder::p()->content($this->context->lang('persona.no_requests')));

            return $wrap;
        }

        $list = Builder::ul('persona-requests');
        foreach ($requests as $request) {
            $item = Builder::li();
            $item->append(Builder::span()->content('#' . (string) $request['id'] . ' ' . (string) $request['email'] . ' ' . (string) $request['name']));

            $form = new Form('persona-approve-form');
            $form->method('POST');
            $csrf = $this->context->service('csrf');
            if ($csrf instanceof Csrf) {
                $form->csrf($csrf);
            }
            $form->add(new Input('hidden', 'request_id', (string) $request['id']));
            $form->add(new Button($this->context->lang('persona.approve')));
            $item->append($form);
            $list->append($item);
        }
        $wrap->append($list);

        return $wrap;    }
}
