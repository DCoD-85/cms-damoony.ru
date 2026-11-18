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

final class algoritm_lang extends Page
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
        $wrap = Builder::div('admin-box');
        $wrap->append(Builder::h(1)->content($this->context->lang('admin.title')));
        $wrap->append(Builder::p()->content((string) $user['email']));

        $counts = Builder::ul('admin-counts');
        $counts->append(Builder::li()->content(
            $this->context->lang('admin.requests') . ': ' . count($repo->pendingRequests()),
        ));
        $counts->append(Builder::li()->content(
            $this->context->lang('admin.installations') . ': ' . count($repo->pendingInstallations()),
        ));
        $counts->append(Builder::li()->content(
            $this->context->lang('admin.accounts') . ': ' . $repo->accountsCount(),
        ));
        $wrap->append($counts);

        $menu = Builder::ul('admin-menu');
        $menu->append(Builder::li()->append(
            Builder::a('/' . $lang . '/requests/')->content($this->context->lang('admin.requests')),
        ));
        $menu->append(Builder::li()->append(
            Builder::a('/' . $lang . '/installations/')->content($this->context->lang('admin.installations')),
        ));
        $menu->append(Builder::li()->append(
            Builder::a($personaBase . '/' . $lang . '/')->content($this->context->lang('admin.persona')),
        ));
        $wrap->append($menu);

        return $wrap;    }
}
