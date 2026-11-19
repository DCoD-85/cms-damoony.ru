<?php

declare(strict_types=1);

namespace admin_server\www;

use Damoony\Admin\Page\AdminPage;
use Damoony\Admin\Policy\AdminPolicy;
use Damoony\Core\Files\Paths;
use Damoony\Core\Http\Response;
use Damoony\Core\Settings\ProjectSettings;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Core\Settings\SiteConfigWriter;
use Damoony\UI\Builder;
use Damoony\UI\Components\Form;
use Damoony\UI\Components\Input;
use Damoony\UI\Node;

final class algoritm_lang_domain extends AdminPage
{
    protected function content(): Node|Response|null
    {
        $session = $this->adminSession();
        $site = $this->context->service('site_config');
        $paths = $this->context->service('paths');
        if ($session === null || !$site instanceof SiteConfig || !$paths instanceof Paths || (string) $session['scope'] === '') {
            return Response::notFound();
        }

        $project = $site->projectByHost((string) $session['scope']);
        if ($project === null || !AdminPolicy::allows($session, AdminPolicy::DOMAIN_VIEW, $project->key)) {
            return Response::notFound();
        }

        $lang = $this->context->domain->language;
        $tab = (string) ($this->context->request->query('tab') ?? 'schemes');
        if (!in_array($tab, ['schemes', 'blocks', 'db', 'aliases'], true)) {
            $tab = 'schemes';
        }

        if ($tab === 'aliases' && $this->isPost() && $this->csrfValid()) {
            $this->toggleAlias($session, $project, $paths);

            return Response::redirect('/' . $lang . '/domain/?tab=aliases');
        }

        $wrap = Builder::div('admin-box domain-admin');
        $wrap->append(Builder::h(1)->content('Мой домен'));
        $wrap->append(Builder::p()->content($project->key . ' · folder: ' . $project->folder . ' · scheme: ' . $project->scheme));

        $tabs = Builder::div('content-tabs');
        foreach (['schemes' => 'Схемы', 'blocks' => 'Блоки', 'db' => 'БД', 'aliases' => 'Алиасы'] as $key => $label) {
            $tabs->append(Builder::a(
                '/' . $lang . '/domain/?tab=' . $key,
                'content-tab' . ($tab === $key ? ' is-active' : ''),
            )->content($label));
        }
        $wrap->append($tabs);

        if ($tab === 'schemes') {
            $wrap->append($this->schemes($project, $paths));
        } elseif ($tab === 'blocks') {
            $wrap->append($this->blocks($project, $paths));
        } elseif ($tab === 'db') {
            $wrap->append($this->db($project, $paths));
        } else {
            $wrap->append($this->aliases($project, $paths, $lang));
        }

        return $wrap;
    }

    private function schemes(ProjectSettings $project, Paths $paths): Node
    {
        $box = Builder::div('domain-section');
        $box->append(Builder::h(2)->content('Схемы домена'));
        $box->append($this->fileList($paths->appRoot() . '/' . $project->folder . '/.schemesglobal', '.php'));

        foreach ($project->aliasNames() as $alias) {
            $box->append(Builder::h(3)->content('Алиас ' . $alias));
            $box->append($this->fileList($paths->appRoot() . '/' . $project->folder . '/' . $alias . '/.schemeslocal', '.php'));
        }

        return $box;
    }

    private function blocks(ProjectSettings $project, Paths $paths): Node
    {
        $box = Builder::div('domain-section');
        $box->append(Builder::h(2)->content('Блоки домена'));
        $box->append($this->fileList($paths->appRoot() . '/' . $project->folder . '/.blocksglobal', '.php'));

        foreach ($project->aliasNames() as $alias) {
            $box->append(Builder::h(3)->content('Алиас ' . $alias));
            $box->append($this->fileList($paths->appRoot() . '/' . $project->folder . '/' . $alias . '/.blockslocal', '.php'));
        }

        return $box;
    }

    private function db(ProjectSettings $project, Paths $paths): Node
    {
        $box = Builder::div('domain-section');
        $box->append(Builder::h(2)->content('БД домена'));
        $box->append($this->fileList($paths->appRoot() . '/' . $project->folder . '/.dbglobal', ''));
        $box->append(Builder::h(3)->content('Провайдеры домена'));
        $box->append($this->fileList($paths->appRoot() . '/' . $project->folder . '/.dbprovider', ''));

        foreach ($project->aliasNames() as $alias) {
            $box->append(Builder::h(3)->content('Алиас ' . $alias));
            $box->append($this->fileList($paths->appRoot() . '/' . $project->folder . '/' . $alias . '/.dbprovider', ''));
        }

        return $box;
    }

    private function aliases(ProjectSettings $project, Paths $paths, string $lang): Node
    {
        $box = Builder::div('domain-section');
        $csrf = $this->csrfToken();

        $list = Builder::ul('admin-aliases');
        foreach ($project->aliasNames() as $alias) {
            $active = $project->isAliasAvailable($alias);
            $item = Builder::li()->content($alias . ' — ' . ($active ? 'включён' : 'выключен'));

            $form = new Form('admin-toggle');
            $form->method('POST');
            $form->action('/' . $lang . '/domain/?tab=aliases');
            $form->add(new Input('hidden', '_csrf', $csrf));
            $form->add(new Input('hidden', 'alias', $alias));

            $switch = Builder::button('submit', 'admin-switch' . ($active ? ' is-on' : ''));
            $switch->attrs()->add('title', $active ? 'Включён' : 'Выключен');
            $switch->attrs()->add('aria-pressed', $active ? 'true' : 'false');
            $switch->append(Builder::span('admin-switch-slider'));
            $form->add($switch);
            $item->append($form);
            $list->append($item);
        }
        $box->append($list);

        return $box;
    }

    private function fileList(string $dir, string $ext): Node
    {
        if (!is_dir($dir)) {
            return Builder::p('domain-empty')->content('Нет файлов');
        }

        $files = glob($dir . '/*' . $ext) ?: [];
        if ($files === []) {
            return Builder::p('domain-empty')->content('Нет файлов');
        }

        $list = Builder::ul('domain-files');
        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }
            $name = basename($file);
            $list->append(Builder::li()->content($name));
        }

        return $list;
    }

    /** @param array{role: string, scope: string, account: array<string, mixed>} $session */
    private function toggleAlias(array $session, ProjectSettings $project, Paths $paths): void
    {
        if (!AdminPolicy::allows($session, AdminPolicy::ALIAS_MANAGE, $project->key)) {
            return;
        }
        $alias = (string) $this->context->request->post('alias');
        if (!$project->hasAlias($alias)) {
            return;
        }

        $next = !$project->isAliasAvailable($alias);
        (new SiteConfigWriter($paths->root() . '/config/site.config'))
            ->setAliasAvailable($project->key, $alias, $next);
        $this->auditAction($session, 'domain.alias', $project->key . ':' . $alias . '=' . ($next ? 'on' : 'off'));
    }
}
