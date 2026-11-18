<?php

declare(strict_types=1);

namespace admin_server\www;

use Damoony\Admin\Page\AdminPage;
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
        if ($session === null || $session['role'] !== 'superadmin') {
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
        $wrap = Builder::div('admin-box');
        $wrap->append(Builder::h(1)->content('Проекты'));
        $wrap->append(Builder::p()->append(
            Builder::a('/' . $lang . '/')->content('← Назад'),
        ));

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
                $item->append($this->toggleForm($csrf, $lang, 'toggle_mirror', (string) $key, (string) $host));
                $mirrors->append($item);
            }
            $block->append($mirrors);

            $aliases = Builder::ul('admin-aliases');
            foreach ($project->aliasNames() as $alias) {
                $item = Builder::li()->content(
                    $alias . ' — ' . ($project->isAliasAvailable($alias) ? 'включён' : 'выключен'),
                );
                $item->append($this->toggleForm($csrf, $lang, 'toggle_alias', (string) $key, $alias));
                $aliases->append($item);
            }
            $block->append($aliases);
            $wrap->append($block);
        }

        return $wrap;
    }

    private function toggleForm(string $csrf, string $lang, string $action, string $project, string $target): Form
    {
        $form = new Form('admin-toggle');
        $form->method('POST');
        $form->action('/' . $lang . '/projects/');
        $form->add(new Input('hidden', '_csrf', $csrf));
        $form->add(new Input('hidden', 'action', $action));
        $form->add(new Input('hidden', 'project', $project));
        $form->add(new Input('hidden', 'target', $target));
        $form->add(new Button('Переключить'));

        return $form;
    }

    private function applyAction(SiteConfig $site, Paths $paths): void
    {
        $projectKey = (string) $this->context->request->post('project');
        $target = (string) $this->context->request->post('target');
        $action = (string) $this->context->request->post('action');
        $project = $site->project($projectKey);

        if ($project === null) {
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
