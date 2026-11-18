<?php

declare(strict_types=1);

namespace admin_server\www;

use Damoony\Admin\Page\AdminPage;
use Damoony\Admin\Policy\AdminPolicy;
use Damoony\Core\Files\Paths;
use Damoony\Core\Http\Response;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Core\Settings\ProjectSettings;
use Damoony\Core\Settings\SiteConfigWriter;
use Damoony\UI\Builder;
use Damoony\UI\Components\Button;
use Damoony\UI\Components\Form;
use Damoony\UI\Components\Input;
use Damoony\UI\Node;

final class algoritm_lang_domain extends AdminPage
{
    protected function content(): Node|Response|null
    {
        $session = $this->adminSession();
        if ($session === null) {
            return Response::notFound();
        }

        $scope = (string) $session['scope'];
        $site = $this->context->service('site_config');
        $paths = $this->context->service('paths');
        if (!$site instanceof SiteConfig || !$paths instanceof Paths || $scope === '') {
            return Response::notFound();
        }

        $project = $site->projectByHost($scope);
        if ($project === null || !AdminPolicy::allows($session, AdminPolicy::DOMAIN_VIEW, $project->key)) {
            return Response::notFound();
        }

        $lang = $this->context->domain->language;
        if ($this->isPost() && $this->csrfValid()) {
            $this->toggleAlias($session, $project, $paths);

            return Response::redirect('/' . $lang . '/domain/');
        }

        $csrf = $this->csrfToken();
        $wrap = Builder::div('admin-box');
        $wrap->append(Builder::h(1)->content('Мой домен'));
        $wrap->append(Builder::p()->content($project->key . ' · ' . implode(', ', $project->langs)));

        $aliases = Builder::ul('admin-aliases');
        foreach ($project->aliasNames() as $alias) {
            $active = $project->isAliasAvailable($alias);
            $item = Builder::li()->content($alias . ' — ' . ($active ? 'включён' : 'выключен'));

            $form = new Form('admin-toggle');
            $form->method('POST');
            $form->action('/' . $lang . '/domain/');
            $form->add(new Input('hidden', '_csrf', $csrf));
            $form->add(new Input('hidden', 'op', 'toggle_alias'));
            $form->add(new Input('hidden', 'alias', $alias));

            $switch = Builder::button('submit', 'admin-switch' . ($active ? ' is-on' : ''));
            $switch->attrs()->add('title', $active ? 'Включён' : 'Выключен');
            $switch->attrs()->add('aria-pressed', $active ? 'true' : 'false');
            $switch->append(Builder::span('admin-switch-slider'));
            $form->add($switch);
            $item->append($form);
            $aliases->append($item);
        }
        $wrap->append($aliases);

        return $wrap;
    }

    private function toggleAlias(array $session, ProjectSettings $project, Paths $paths): void
    {
        if (!AdminPolicy::allows($session, AdminPolicy::ALIAS_MANAGE, $project->key)) {
            return;
        }
        $alias = (string) $this->context->request->post('alias');
        if (!$project->hasAlias($alias)) {
            return;
        }

        (new SiteConfigWriter($paths->root() . '/config/site.config'))
            ->setAliasAvailable($project->key, $alias, !$project->isAliasAvailable($alias));
    }
}
