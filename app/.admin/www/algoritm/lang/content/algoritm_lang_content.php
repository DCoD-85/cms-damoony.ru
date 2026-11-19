<?php

declare(strict_types=1);

namespace admin_server\www;

use Damoony\Admin\Content\ContentService;
use Damoony\Admin\Page\AdminPage;
use Damoony\Admin\Policy\AdminPolicy;
use Damoony\Core\Files\Paths;
use Damoony\Core\Http\Response;
use Damoony\Core\Settings\ProjectSettings;
use Damoony\Core\Settings\SiteConfig;
use Damoony\UI\Builder;
use Damoony\UI\Components\Button;
use Damoony\UI\Components\Form;
use Damoony\UI\Components\Input;
use Damoony\UI\Node;
use RuntimeException;

final class algoritm_lang_content extends AdminPage
{
    protected function content(): Node|Response|null
    {
        $session = $this->adminSession();
        $site = $this->context->service('site_config');
        $paths = $this->context->service('paths');
        if ($session === null || !$site instanceof SiteConfig || !$paths instanceof Paths) {
            return Response::notFound();
        }

        $lang = $this->context->domain->language;
        $service = new ContentService($paths);
        $project = $this->resolveProject($site, $session);
        if ($project === null) {
            return Response::notFound();
        }

        $alias = (string) ($this->context->request->query('alias') ?? '');
        if (!$project->hasAlias($alias)) {
            $alias = $project->defaultAlias !== '' ? $project->defaultAlias : 'www';
        }

        $pages = $service->pages($project, $alias, $lang);
        $path = (string) ($this->context->request->query('path') ?? '');
        $path = $this->selectPath($pages, $path, $session['role'] === 'superadmin');

        $message = null;
        $error = null;
        $blocks = [];
        $source = null;

        if ($this->isPost() && $this->csrfValid()) {
            $postProject = $site->project((string) $this->context->request->post('project'));
            $postAlias = (string) $this->context->request->post('alias');
            $postPath = (string) $this->context->request->post('path');
            $mode = (string) $this->context->request->post('mode');

            if ($postProject !== null
                && AdminPolicy::allows($session, AdminPolicy::CONTENT_EDIT, $postProject->key)
                && $postProject->hasAlias($postAlias)
                && $postPath !== ''
            ) {
                try {
                    if ($mode === 'source') {
                        if ($session['role'] !== 'superadmin') {
                            throw new RuntimeException('PHP-редактор доступен только superadmin');
                        }
                        $service->saveSource($postProject, $postAlias, $postPath, (string) $this->context->request->post('code'));
                        $this->auditAction($session, 'content.page.source', $postProject->key . ':' . $postAlias . ':' . $postPath);
                    } else {
                        $decoded = json_decode((string) $this->context->request->post('blocks'), true);
                        if (!is_array($decoded) || !array_is_list($decoded)) {
                            throw new RuntimeException('Ожидается JSON-массив блоков');
                        }
                        $service->save($postProject, $postAlias, $postPath, $decoded);
                        $this->auditAction($session, 'content.page.saved', $postProject->key . ':' . $postAlias . ':' . $postPath);
                    }
                    $message = 'Сохранено';
                    $project = $postProject;
                    $alias = $postAlias;
                    $path = $postPath;
                    $pages = $service->pages($project, $alias, $lang);
                } catch (RuntimeException $e) {
                    $error = $e->getMessage();
                }
            } else {
                $error = 'Недостаточно прав или неверная страница';
            }
        }

        $page = $this->findPage($pages, $path);
        $editable = $page !== null && $page['editable'];
        $sourceMode = $page !== null && !$editable && $session['role'] === 'superadmin';

        if ($page !== null) {
            try {
                if ($editable) {
                    $blocks = $service->read($project, $alias, $path);
                } elseif ($sourceMode) {
                    $source = $service->readSource($project, $alias, $path);
                }
            } catch (RuntimeException $e) {
                $error = $e->getMessage();
            }
        }

        $csrf = $this->csrfToken();
        $wrap = Builder::div('admin-box content-admin');
        $wrap->append(Builder::h(1)->content('Контент'));

        $help = Builder::elA('details', ['class' => 'content-help']);
        $help->append(Builder::el('summary')->content('Как использовать'));
        $help->append(Builder::el('pre')->content(
            '[{"type":"h1","content":"Заголовок"},'
            . '{"type":"text","content":"Текст страницы"},'
            . '{"type":"button","content":"Купить","url":"/ru/contact/","class":"btn"}]',
        ));
        $help->append(Builder::p()->content('Корень / — редирект. Контент главной редактируйте по пути lang.'));
        $wrap->append($help);

        if ($message !== null) {
            $wrap->append(Builder::p('admin-success')->content($message));
        }
        if ($error !== null) {
            $wrap->append(Builder::p('admin-error')->content($error));
        }

        if ($session['role'] === 'superadmin') {
            $tabs = Builder::div('content-tabs');
            foreach ($site->projects() as $key => $item) {
                if ($key === 'admin') {
                    continue;
                }
                $active = $key === $project->key ? ' is-active' : '';
                $tabs->append(Builder::a('/' . $lang . '/content/?project=' . urlencode((string) $key), 'content-tab' . $active)->content((string) $key));
            }
            $wrap->append($tabs);
        }

        $aliases = Builder::div('content-tabs');
        foreach ($project->aliasNames() as $name) {
            $active = $name === $alias ? ' is-active' : '';
            $aliases->append(Builder::a(
                '/' . $lang . '/content/?project=' . urlencode($project->key) . '&alias=' . urlencode($name),
                'content-tab' . $active,
            )->content($name));
        }
        $wrap->append($aliases);

        $layout = Builder::div('content-layout');
        $tree = Builder::div('content-tree');
        foreach ($pages as $item) {
            $target = '/' . $lang . '/content/?project=' . urlencode($project->key)
                . '&alias=' . urlencode($alias) . '&path=' . urlencode($item['path']);
            $tree->append(Builder::a($target, 'content-page' . ($item['path'] === $path ? ' is-active' : ''))->content($item['title']));
        }
        $layout->append($tree);

        $main = Builder::div('content-main');
        if ($page !== null) {
            $main->append(Builder::h(2)->content('/' . $path . '/'));
            $preview = $service->previewUrl($project, $alias, $path, $lang);
            if ($preview !== '') {
                $link = Builder::a($preview, 'content-preview-link')->content('Открыть страницу');
                $link->attrs()->add('target', '_blank');
                $link->attrs()->add('rel', 'noopener');
                $main->append($link);
            }

            if ($sourceMode) {
                $main->append(Builder::p('admin-warning')->content(
                    'PHP-код страницы. Сохранение только superadmin, с бэкапом в var/backups/content.',
                ));
            }

            $main->append($this->editorToolbar($service->hints($project, $alias), $sourceMode));
            if ($editable || $sourceMode) {
                $form = new Form('content-form');
                $form->method('POST');
                $form->action('/' . $lang . '/content/');
                $form->add(new Input('hidden', '_csrf', $csrf));
                $form->add(new Input('hidden', 'project', $project->key));
                $form->add(new Input('hidden', 'alias', $alias));
                $form->add(new Input('hidden', 'path', $path));
                $form->add(new Input('hidden', 'mode', $sourceMode ? 'source' : 'json'));

                $textarea = Builder::el('textarea');
                $textarea->attrs()->add('id', 'content-editor');
                $textarea->attrs()->add('data-mode', $sourceMode ? 'php' : 'json');
                $textarea->attrs()->add('data-hints', (string) json_encode($service->hints($project, $alias), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                $textarea->attrs()->add('name', $sourceMode ? 'code' : 'blocks');
                $textarea->attrs()->classAdd('content-editor-text');
                $value = $sourceMode
                    ? (string) $source
                    : (string) (json_encode($blocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '[]');
                $textarea->content($value);
                $form->add($textarea);
                $form->add(new Button('Сохранить'));
                $main->append($form);

                $media = $service->media($project, $alias);
                $mediaBox = Builder::elA('details', ['class' => 'content-media']);
                $mediaBox->append(Builder::el('summary')->content('Медиа (' . count($media) . ')'));
                if ($media === []) {
                    $mediaBox->append(Builder::p()->content('Файлов пока нет.'));
                } else {
                    $mediaList = Builder::ul('content-media-list');
                    foreach ($media as $item) {
                        $li = Builder::li();
                        $li->append(Builder::span()->content($item['file'] . ' · ' . round($item['size'] / 1024) . ' KB'));
                        $use = Builder::button('button', 'ui-btn');
                        $use->attrs()->add('type', 'button');
                        $use->attrs()->add('data-media-url', $item['url']);
                        $use->content('Вставить URL');
                        $li->append($use);
                        $mediaList->append($li);
                    }
                    $mediaBox->append($mediaList);
                }
                $main->append($mediaBox);
            } else {
                $main->append(Builder::p('admin-warning')->content('Страница доступна только для просмотра.'));
            }
        } else {
            $main->append(Builder::p()->content('Выберите страницу слева.'));
        }
        $layout->append($main);
        $wrap->append($layout);

        return $wrap;
    }

    /**
     * @param list<array{path: string, title: string, url: string, editable: bool}> $pages
     */
    /**
     * @param array{types: list<string>, fields: list<string>, methods: list<array{provider: string, name: string, params: list<string>}>} $hints
     */
    private function editorToolbar(array $hints, bool $php): Node
    {
        $bar = Builder::div('content-toolbar');

        if (!$php) {
            $select = Builder::elA('select', ['id' => 'content-insert-block']);
            foreach ($hints['types'] as $type) {
                $option = Builder::el('option');
                $option->attrs()->add('value', $type);
                $option->content($type);
                $select->append($option);
            }
            $bar->append($select);
            $insert = Builder::button('button', 'ui-btn');
            $insert->attrs()->add('data-insert-block', '1');
            $insert->attrs()->add('type', 'button');
            $insert->content('Вставить блок');
            $bar->append($insert);
        } else {
            $select = Builder::elA('select', ['id' => 'content-insert-method']);
            $seen = [];
            foreach ($hints['methods'] as $method) {
                if (isset($seen[$method['name']])) {
                    continue;
                }
                $seen[$method['name']] = true;
                $option = Builder::el('option');
                $option->attrs()->add('value', $method['name']);
                $option->content($method['provider'] . '::' . $method['name']);
                $select->append($option);
            }
            $bar->append($select);
            $insert = Builder::button('button', 'ui-btn');
            $insert->attrs()->add('data-insert-method', '1');
            $insert->attrs()->add('type', 'button');
            $insert->content('Вставить метод');
            $bar->append($insert);
        }

        return $bar;
    }

    private function selectPath(array $pages, string $path, bool $superadmin): string
    {
        if ($path !== '' && $this->findPage($pages, $path) !== null) {
            return $path;
        }

        foreach ($pages as $page) {
            if ($page['editable']) {
                return $page['path'];
            }
        }
        if ($superadmin) {
            foreach ($pages as $page) {
                if ($page['path'] !== '') {
                    return $page['path'];
                }
            }
        }

        return '';
    }

    /**
     * @param list<array{path: string, title: string, url: string, editable: bool}> $pages
     *
     * @return array{path: string, title: string, url: string, editable: bool}|null
     */
    private function findPage(array $pages, string $path): ?array
    {
        foreach ($pages as $page) {
            if ($page['path'] === $path) {
                return $page;
            }
        }

        return null;
    }


    private function resolveProject(SiteConfig $site, array $session): ?ProjectSettings
    {
        $requested = (string) ($this->context->request->query('project') ?? '');
        if ($session['role'] === 'domainadmin') {
            $requested = (string) $session['scope'];
        }

        if ($requested !== '') {
            $project = $site->projectByHost($requested);
            if ($project !== null && AdminPolicy::allows($session, AdminPolicy::CONTENT_EDIT, $project->key)) {
                return $project;
            }
        }

        foreach ($site->projects() as $key => $project) {
            if ($key !== 'admin' && AdminPolicy::allows($session, AdminPolicy::CONTENT_EDIT, $project->key)) {
                return $project;
            }
        }

        return null;
    }
}
