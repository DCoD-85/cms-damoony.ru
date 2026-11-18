<?php

declare(strict_types=1);

namespace admin_server\www;

use Damoony\Admin\Page\AdminPage;
use Damoony\Admin\Policy\AdminPolicy;
use Damoony\Core\Files\Paths;
use Damoony\Core\Http\Response;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Core\Settings\SiteConfigWriter;
use Damoony\UI\Builder;
use Damoony\UI\Components\Button;
use Damoony\UI\Components\Form;
use Damoony\UI\Components\Input;
use Damoony\UI\Node;

final class algoritm_lang_projects extends AdminPage
{
    protected function content(): Node|Response|null
    {
        $session = $this->adminSession();
        if ($session === null || !AdminPolicy::allows($session, AdminPolicy::SERVER_MANAGE)) {
            return Response::notFound();
        }

        $lang = $this->context->domain->language;
        $site = $this->context->service('site_config');
        $paths = $this->context->service('paths');
        if (!$site instanceof SiteConfig || !$paths instanceof Paths) {
            return Response::notFound();
        }

        if ($this->isPost() && $this->csrfValid()) {
            $this->applyAction($site, $paths);

            return Response::redirect('/' . $lang . '/projects/');
        }

        $csrf = $this->csrfToken();
        $currentHost = strtolower(preg_replace('/:\\d+$/', '', (string) ($this->context->request->server['HTTP_HOST'] ?? '')) ?? '');
        $wrap = Builder::div('admin-box');
        $wrap->append(Builder::h(1)->content('Проекты'));

        foreach ($site->projects() as $key => $project) {
            $block = Builder::div('admin-project');
            $block->append(Builder::el('strong')->content((string) $key));
            $block->append(Builder::div()->content(
                'folder: ' . $project->folder
                . ' · default: ' . $project->defaultAlias
                . ' · langs: ' . implode(', ', $project->langs),
            ));

            $mirrors = Builder::ul('admin-mirrors');
            foreach ($project->mirrors() as $host => $available) {
                $item = Builder::li()->content(
                    $host . ' — ' . ($available ? 'включено' : 'выключено'),
                );
                if ($key !== 'admin' && $currentHost !== strtolower($host)) {
                    $item->append($this->toggleForm($csrf, $lang, 'toggle_mirror', (string) $key, (string) $host, $available));
                }
                $mirrors->append($item);
            }
            $block->append($mirrors);

            $aliases = Builder::ul('admin-aliases');
            foreach ($project->aliasNames() as $alias) {
                $item = Builder::li()->content(
                    $alias . ' — ' . ($project->isAliasAvailable($alias) ? 'включён' : 'выключен'),
                );
                if ($key !== 'admin') {
                    $item->append($this->toggleForm($csrf, $lang, 'toggle_alias', (string) $key, $alias, $project->isAliasAvailable($alias)));
                }
                $aliases->append($item);
            }
            $block->append($aliases);
            $wrap->append($block);
        }

        return $wrap;
    }

    private function toggleForm(string $csrf, string $lang, string $action, string $project, string $target, bool $active): Form
    {
        $form = new Form('admin-toggle');
        $form->method('POST');
        $form->action('/' . $lang . '/projects/');
        $form->add(new Input('hidden', '_csrf', $csrf));
        $form->add(new Input('hidden', 'op', $action));
        $form->add(new Input('hidden', 'project', $project));
        $form->add(new Input('hidden', 'target', $target));

        $switch = Builder::button('submit', 'admin-switch' . ($active ? ' is-on' : ''));
        $switch->attrs()->add('title', $active ? 'Включено' : 'Выключено');
        $switch->attrs()->add('aria-pressed', $active ? 'true' : 'false');
        $switch->append(Builder::span('admin-switch-slider'));
        $form->add($switch);

        return $form;
    }

    private function applyAction(SiteConfig $site, Paths $paths): void
    {
        $projectKey = (string) $this->context->request->post('project');
        $target = (string) $this->context->request->post('target');
        $action = (string) $this->context->request->post('op');
        $project = $site->project($projectKey);

        if ($project === null || $projectKey === 'admin') {
            return;
        }

        $currentHost = strtolower(preg_replace('/:\d+$/', '', (string) ($this->context->request->server['HTTP_HOST'] ?? '')) ?? '');
        if ($action === 'toggle_mirror' && strtolower($target) === $currentHost) {
            return;
        }

        $writer = new SiteConfigWriter($paths->root() . '/config/site.config');
        if ($action === 'toggle_alias' && $project->hasAlias($target)) {
            $writer->setAliasAvailable($projectKey, $target, !$project->isAliasAvailable($target));
        } elseif ($action === 'toggle_mirror' && array_key_exists($target, $project->mirrors())) {
            $writer->setMirrorAvailable($projectKey, $target, !$project->isHostAvailable($target));
        }
    }
}
