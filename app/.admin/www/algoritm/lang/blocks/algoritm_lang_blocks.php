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
use Damoony\UI\Components\Form;
use Damoony\UI\Components\Input;
use Damoony\UI\Node;
use RuntimeException;

final class algoritm_lang_blocks extends AdminPage
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

        $message = null;
        $error = null;
        if ($this->isPost() && $this->csrfValid()) {
            $postProject = $site->project((string) $this->context->request->post('project'));
            $postAlias = (string) $this->context->request->post('alias');
            if ($postProject !== null
                && AdminPolicy::allows($session, AdminPolicy::CONTENT_EDIT, $postProject->key)
                && $postProject->hasAlias($postAlias)
            ) {
                try {
                    if ((string) $this->context->request->post('op') === 'save_block') {
                        $scope = (string) $this->context->request->post('scope');
                        $name = (string) $this->context->request->post('name');
                        if ((string) $this->context->request->post('mode') === 'source') {
                            if ($session['role'] !== 'superadmin') {
                                throw new RuntimeException('PHP-редактор доступен только superadmin');
                            }
                            $service->saveBlockSource($postProject, $postAlias, $scope, $name, (string) $this->context->request->post('code'));
                            $this->auditAction($session, 'content.block.source', $postProject->key . ':' . $postAlias . ':' . $scope . ':' . $name);
                        } else {
                            $decoded = json_decode((string) $this->context->request->post('blocks'), true);
                            if (!is_array($decoded) || !array_is_list($decoded)) {
                                throw new RuntimeException('Ожидается JSON-массив блоков');
                            }
                            $service->saveBlock($postProject, $postAlias, $scope, $name, $decoded);
                            $this->auditAction($session, 'content.block.saved', $postProject->key . ':' . $postAlias . ':' . $scope . ':' . $name);
                        }
                        $message = 'Блок сохранён';
                    } else {
                        $service->toggleBlock(
                            $postProject,
                            $postAlias,
                            (string) $this->context->request->post('scope'),
                            (string) $this->context->request->post('name'),
                            (bool) $this->context->request->post('enabled'),
                        );
                        $this->auditAction($session, 'content.block.toggled', $postProject->key . ':' . $postAlias);
                        $message = 'Блок обновлён';
                    }
                    $project = $postProject;
                    $alias = $postAlias;
                } catch (RuntimeException $e) {
                    $error = $e->getMessage();
                }
            } else {
                $error = 'Недостаточно прав';
            }
        }

        $blocks = $service->blocks($project, $alias);
        $csrf = $this->csrfToken();
        $wrap = Builder::div('admin-box content-admin');
        $wrap->append(Builder::h(1)->content('Блоки'));
        if ($message !== null) {
            $wrap->append(Builder::p('admin-toast is-success')->content($message));
        }
        if ($error !== null) {
            $wrap->append(Builder::p('admin-toast is-error')->content($error));
        }

        if ($session['role'] === 'superadmin') {
            $tabs = Builder::div('content-tabs');
            foreach ($site->projects() as $key => $item) {
                if ($key === 'admin') {
                    continue;
                }
                $active = $key === $project->key ? ' is-active' : '';
                $tabs->append(Builder::a('/' . $lang . '/blocks/?project=' . urlencode((string) $key), 'content-tab' . $active)->content((string) $key));
            }
            $wrap->append($tabs);
        }

        $aliases = Builder::div('content-tabs');
        foreach ($project->aliasNames() as $name) {
            $active = $name === $alias ? ' is-active' : '';
            $aliases->append(Builder::a(
                '/' . $lang . '/blocks/?project=' . urlencode($project->key) . '&alias=' . urlencode($name),
                'content-tab' . $active,
            )->content($name));
        }
        $wrap->append($aliases);

        $selected = (string) ($this->context->request->query('block') ?? '');
        $selectedBlock = null;
        foreach ($blocks as $block) {
            if ($block['file'] === $selected) {
                $selectedBlock = $block;
                break;
            }
        }
        if ($selectedBlock === null && $blocks !== []) {
            $selectedBlock = $blocks[0];
        }

        if ($blocks === []) {
            $wrap->append(Builder::p()->content('Блоков нет.'));
        } else {
            $list = Builder::ul('admin-blocks');
            foreach ($blocks as $block) {
                $item = Builder::li();
                $blockLink = Builder::a('/' . $lang . '/blocks/?project=' . urlencode($project->key) . '&alias=' . urlencode($alias) . '&block=' . urlencode($block['file']), 'content-page' . ($selectedBlock !== null && $selectedBlock['file'] === $block['file'] ? ' is-active' : ''));
                $blockLink->content($block['scope'] . ':' . $block['name']);
                $item->append($blockLink);
                $item->append(Builder::span()->content($block['enabled'] ? ' — включён' : ' — выключен'));

                $form = new Form('admin-block-toggle');
                $form->method('POST');
                $form->action('/' . $lang . '/blocks/?project=' . urlencode($project->key) . '&alias=' . urlencode($alias));
                $form->add(new Input('hidden', '_csrf', $csrf));
                $form->add(new Input('hidden', 'project', $project->key));
                $form->add(new Input('hidden', 'alias', $alias));
                $form->add(new Input('hidden', 'scope', $block['scope']));
                $form->add(new Input('hidden', 'name', $block['name']));
                $form->add(new Input('hidden', 'enabled', $block['enabled'] ? '0' : '1'));
                $switch = Builder::button('submit', 'admin-switch' . ($block['enabled'] ? ' is-on' : ''));
                $switch->attrs()->add('aria-pressed', $block['enabled'] ? 'true' : 'false');
                $switch->append(Builder::span('admin-switch-slider'));
                $form->add($switch);
                $item->append($form);

                $list->append($item);
            }
            $wrap->append($list);
        }

        if ($selectedBlock !== null) {
            $scope = $selectedBlock['scope'];
            $name = $selectedBlock['name'];
            try {
                $data = $service->readBlock($project, $alias, $scope, $name);
                $editable = $data['blocks'] !== [];
                $sourceMode = !$editable && $session['role'] === 'superadmin';

                $wrap->append(Builder::h(2)->content('Блок ' . $scope . ':' . $name));
                if ($editable || $sourceMode) {
                    $form = new Form('block-editor');
                    $form->method('POST');
                    $form->action('/' . $lang . '/blocks/?project=' . urlencode($project->key) . '&alias=' . urlencode($alias) . '&block=' . urlencode($selectedBlock['file']));
                    $form->add(new Input('hidden', '_csrf', $csrf));
                    $form->add(new Input('hidden', 'op', 'save_block'));
                    $form->add(new Input('hidden', 'project', $project->key));
                    $form->add(new Input('hidden', 'alias', $alias));
                    $form->add(new Input('hidden', 'scope', $scope));
                    $form->add(new Input('hidden', 'name', $name));
                    $form->add(new Input('hidden', 'mode', $sourceMode ? 'source' : 'json'));

                    $textarea = Builder::el('textarea');
                    $textarea->attrs()->add('id', 'content-editor');
                    $textarea->attrs()->add('data-mode', $sourceMode ? 'php' : 'json');
                    $textarea->attrs()->add('data-hints', (string) json_encode($service->hints($project, $alias), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                    $textarea->attrs()->add('name', $sourceMode ? 'code' : 'blocks');
                    $textarea->attrs()->classAdd('content-editor-text');
                    $value = $sourceMode
                        ? (string) $data['source']
                        : (string) (json_encode($data['blocks'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '[]');
                    $textarea->content($value);
                    $form->add($textarea);
                    $form->add(new \Damoony\UI\Components\Button('Сохранить'));
                    $wrap->append($form);
                } else {
                    $wrap->append(Builder::p('admin-warning')->content('Блок доступен только для просмотра.'));
                }
            } catch (RuntimeException $e) {
                $wrap->append(Builder::p('admin-toast is-error')->content($e->getMessage()));
            }
        }

        return $wrap;
    }

    /** @param array{role: string, scope: string, account: array<string, mixed>} $session */
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
