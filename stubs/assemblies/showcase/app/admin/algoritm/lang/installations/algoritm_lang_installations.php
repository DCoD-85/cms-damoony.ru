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

final class algoritm_lang_installations extends Page
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
        if ($this->isPost()) {
            $installationId = (int) $this->context->request->post('installation_id');
            if ($repo->installationById($installationId) !== []) {
                $repo->setInstallationStatus($installationId, 'active');
            }

            return Response::redirect('/' . $lang . '/installations/');
        }

        $wrap = Builder::div('admin-box');
        $wrap->append(Builder::h(1)->content($this->context->lang('admin.installations')));
        $wrap->append(Builder::p()->append(
            Builder::a('/' . $lang . '/')->content($this->context->lang('admin.back')),
        ));

        $installations = $repo->pendingInstallations();
        if ($installations === []) {
            $wrap->append(Builder::p()->content($this->context->lang('admin.no_installations')));

            return $wrap;
        }

        $list = Builder::ul('admin-items');
        foreach ($installations as $installation) {
            $item = Builder::li();
            $item->append(Builder::span()->content(
                '#' . (string) $installation['id'] . ' ' . (string) $installation['domain']
                . ' — ' . (string) $installation['assembly']
                . ' — ' . (string) $installation['langs'],
            ));

            $form = new Form('admin-action');
            $form->method('POST');
            $csrf = $this->context->service('csrf');
            if ($csrf instanceof Csrf) {
                $form->csrf($csrf);
            }
            $form->add(new Input('hidden', 'installation_id', (string) $installation['id']));
            $form->add(new Button($this->context->lang('admin.approve')));
            $item->append($form);
            $list->append($item);
        }
        $wrap->append($list);

        return $wrap;    }
}
