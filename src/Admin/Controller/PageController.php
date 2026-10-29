<?php

declare(strict_types=1);

namespace Damoony\Admin\Controller;

use Damoony\Admin\View;
use Damoony\Cli\Commands\PageNewCommand;
use Damoony\Cli\Commands\PageToggleCommand;
use Damoony\Cli\Context;
use Damoony\Core\Config;
use Damoony\Files\Fs;
use Damoony\Files\Paths;
use Damoony\Http\Request;
use Damoony\Http\Response;
use Damoony\Page\PageCompiler;
use Damoony\Ui\Builder;
use Damoony\Ui\Element;
use Damoony\Ui\Field;
use Damoony\Ui\Form;
use Damoony\Ui\Message;
use Damoony\Ui\Text;

final class PageController extends BaseController
{
    public function __construct(
        \Damoony\Admin\AuthInterface $auth,
        \Damoony\Security\Csrf $csrf,
        View $view,
        private readonly Paths $paths,
    ) {
        parent::__construct($auth, $csrf, $view);
    }

    public function index(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) {
            return $auth;
        }

        $domain = (string) $request->query('domain', '');
        $allias = (string) $request->query('allias', 'www');

        if ($domain === '') {
            return $this->redirect('/admin/?p=domain');
        }

        $baseDir = $this->paths->algoritm($domain, $allias);

        // Корневой узел (/) — сам algoritm.php
        $rootNode = [
            'name' => View::t('admin.page.root'),
            'path' => '',
            'auth' => false,
            'search' => true,
            'children' => $this->buildPageTree($baseDir, ''),
        ];

        // Читаем настройки корня
        $rootRules = $baseDir . '/.rules.json';
        if (is_file($rootRules)) {
            $r = json_decode((string)file_get_contents($rootRules), true);
            $rootNode['auth'] = (bool)($r['auth'] ?? false);
            $rootNode['search'] = (bool)($r['search'] ?? true);
        }

        $tree = [$rootNode];

        $card = Builder::div('admin-card');
        $link = Builder::el('link', true);
        $link->attrs()->add('rel', 'stylesheet');
        $link->attrs()->add('href', '/media/admin/page-builder.css');
        $card->append($link);

        $breadcrumbs = Builder::div('breadcrumbs');
        $breadcrumbs->append(Builder::a('/admin/?p=domain')->content(View::t('domain.bread_domains')));
        $breadcrumbs->append(Text::raw(' / '));
        $breadcrumbs->append(Builder::a('/admin/?p=domain:edit&domain=' . urlencode($domain))->content($domain));
        $card->append($breadcrumbs);

        $header = Builder::div('page-header');
        $header->append(Builder::h(1)->content(View::t('page.title') . ': ' . View::esc($allias)));
        $actions = Builder::div('page-actions');
        $actions->append(Builder::a('/admin/?p=page:new&domain=' . urlencode($domain) . '&allias=' . urlencode($allias), 'btn btn-primary')->content(View::t('page.add')));
        $header->append($actions);
        $card->append($header);
        $card->append(View::alliasTabs($domain, $allias, 'pages'));

        $card->append($this->renderTreeHtml($tree, $domain, $allias));

        return Response::html($this->view->render($card, ['title' => View::t('page.title')]));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildPageTree(string $baseDir, string $relativePath): array
    {
        $nodes = [];
        $currentDir = $baseDir . ($relativePath === '' ? '' : '/' . $relativePath);
        if (!is_dir($currentDir)) {
            return [];
        }

        $items = scandir($currentDir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || !is_dir($currentDir . '/' . $item)) {
                continue;
            }
            if (str_starts_with($item, '.')) {
                continue;
            } // Пропускаем системные

            $nodePath = $relativePath === '' ? $item : $relativePath . '/' . $item;
            $rulesFile = $currentDir . '/' . $item . '/.rules.json';

            $isAuth = false;
            $isSearch = true;
            if (is_file($rulesFile)) {
                $rules = json_decode((string)file_get_contents($rulesFile), true);
                $isAuth = (bool)($rules['auth'] ?? false);
                $isSearch = (bool)($rules['search'] ?? true);
            }

            $nodes[] = [
                'name' => $item,
                'path' => $nodePath,
                'auth' => $isAuth,
                'search' => $isSearch,
                'children' => $this->buildPageTree($baseDir, $nodePath),
            ];
        }
        return $nodes;
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     */
    private function renderTreeHtml(array $nodes, string $domain, string $allias): Element
    {
        $ul = Builder::ul('page-tree');
        if (empty($nodes)) {
            return $ul;
        }

        foreach ($nodes as $node) {
            $li = Builder::li('page-tree-item');
            $header = Builder::div('page-tree-row');

            // Левая часть: Иконка и ссылка
            $left = Builder::div('page-tree-left');
            $icon = $node['path'] === '' ? '🏠' : '📄';
            $url = '/admin/?p=page:edit&domain=' . urlencode($domain) . '&allias=' . urlencode($allias) . '&path=' . urlencode($node['path']);
            $left->append(Builder::a($url, 'page-tree-link')->content($icon . ' /' . ($node['path'] ?: '')));
            $header->append($left);

            // Правая часть: Кнопки
            $right = Builder::div('page-tree-actions');

            // Визуально
            $btnVisual = Builder::a($url, 'btn btn-primary btn-sm')->content(View::t('admin.page.visual_editor'));
            $right->append($btnVisual);

            // Код
            $phpFile = $node['path'] === ''
                ? $allias . '/algoritm/algoritm.php'
                : $allias . '/algoritm/' . $node['path'] . '/algoritm_' . str_replace('/', '_', $node['path']) . '.php';
            $codeUrl = '/admin/?p=editor&domain=' . urlencode($domain) . '&file=' . urlencode($phpFile);
            $btnCode = Builder::a($codeUrl, 'btn btn-outline btn-sm')->content(View::t('admin.page.code'));
            $right->append($btnCode);

            // Toggles (Auth и Search)
            $toggleUrl = '/admin/?p=page:toggle&domain=' . urlencode($domain) . '&allias=' . urlencode($allias);

            $authForm = Form::create($toggleUrl, 'POST')->csrf($this->csrf->field())
                ->add(Field::hidden('path')->value($node['path']))
                ->add(Field::hidden('key')->value('auth'))
                ->add(Field::hidden('value')->value($node['auth'] ? '0' : '1'))
                ->submit($node['auth'] ? View::t('admin.page.btn_auth_on') : View::t('admin.page.btn_auth_off'));
            $authBtn = $authForm->build();
            foreach ($authBtn->children() as $child) {
                if ($child instanceof Element && $child->tag() === 'button') {
                    $child->attrs()->classAdd('btn btn-toggle ' . ($node['auth'] ? 'toggle-on' : 'toggle-off'));
                }
            }
            $right->append($authBtn);

            $searchForm = Form::create($toggleUrl, 'POST')->csrf($this->csrf->field())
                ->add(Field::hidden('path')->value($node['path']))
                ->add(Field::hidden('key')->value('search'))
                ->add(Field::hidden('value')->value($node['search'] ? '0' : '1'))
                ->submit($node['search'] ? View::t('admin.page.btn_search_on') : View::t('admin.page.btn_search_off'));
            $searchBtn = $searchForm->build();
            foreach ($searchBtn->children() as $child) {
                if ($child instanceof Element && $child->tag() === 'button') {
                    $child->attrs()->classAdd('btn btn-toggle ' . ($node['search'] ? 'toggle-on' : 'toggle-off'));
                }
            }
            $right->append($searchBtn);

            $header->append($right);
            $li->append($header);

            if (!empty($node['children'])) {
                $li->append($this->renderTreeHtml($node['children'], $domain, $allias));
            }
            $ul->append($li);
        }
        return $ul;
    }

    public function edit(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) {
            return $auth;
        }

        $domain = (string) $request->query('domain', '');
        $allias = (string) $request->query('allias', 'www');
        $path = (string) $request->query('path', '');

        if ($domain === '' || $path === '') {
            return $this->redirect('/admin/?p=domain');
        }

        $baseDir = $this->paths->algoritm($domain, $allias) . '/' . $path;
        if (!is_dir($baseDir)) {
            return $this->redirect('/admin/?p=page&domain=' . urlencode($domain) . '&allias=' . urlencode($allias));
        }

        // Получаем слоты из схемы домена
        $site = \Damoony\Core\Config::fromFile($this->paths->siteConfig())->all();
        $realDomain = (string) ($site['domains_real'][$domain] ?? $domain);
        $schemeName = (string) ($site['domains_scheme'][$realDomain] ?? 'www');
        $schemeFile = $this->paths->domain($realDomain) . '/.alliasscheme/' . $schemeName . '.data.php';

        $availableSlots = ['center'];
        if (is_file($schemeFile)) {
            $schemeData = require $schemeFile;
            $slots = [];
            $this->collectSlots($schemeData, $slots);
            if ($slots !== []) {
                $availableSlots = array_map('strval', array_keys($slots));
            }
        }

        // Читаем текущие настройки страницы (мигрируем со старого .edit.json)
        $editorFile = $baseDir . '/page.editor.json';
        $legacyFile = $baseDir . '/.edit.json';

        $pageData = ['slots' => []];
        if (is_file($editorFile)) {
            $pageData = json_decode((string)file_get_contents($editorFile), true);
        } elseif (is_file($legacyFile)) {
            $legacy = json_decode((string)file_get_contents($legacyFile), true);
            if (is_array($legacy) && isset($legacy['slots'])) {
                $pageData = $legacy; // Переносим старый формат
            }
        }

        // Собираем финальную структуру для JS
        $jsSlots = [];
        foreach ($availableSlots as $slot) {
            $jsSlots[$slot] = $pageData['slots'][$slot] ?? ['status' => 'global', 'blocks' => []];
            if (!isset($jsSlots[$slot]['blocks'])) {
                $jsSlots[$slot]['blocks'] = [];
            }
        }

        $card = Builder::div('admin-card page-builder-app');
        $link = Builder::el('link', true);
        $link->attrs()->add('rel', 'stylesheet');
        $link->attrs()->add('href', '/media/admin/page-builder.css');
        $card->append($link);
        $script = Builder::el('script');
        $script->attrs()->add('src', '/media/admin/page-builder.js');
        $script->attrs()->add('defer', 'true');
        $card->append($script);

        $breadcrumbs = Builder::div('breadcrumbs');
        $breadcrumbs->append(Builder::a('/admin/?p=domain')->content(View::t('domain.bread_domains')));
        $breadcrumbs->append(Text::raw(' / '));
        $breadcrumbs->append(Builder::a('/admin/?p=page&domain=' . urlencode($domain) . '&allias=' . urlencode($allias))->content(View::t('page.title')));
        $card->append($breadcrumbs);

        $card->append(Builder::h(1)->content('Редактор страницы: /' . View::esc($path)));

        $layout = Builder::div('pb-layout');

        $canvas = Builder::div('pb-canvas');
        $canvas->attrs()->add('id', 'pb-canvas');
        $layout->append($canvas);

        $tools = Builder::div('pb-tools');
        $tools->append(Builder::h(3)->content('Добавить блок'));
        foreach (['h1', 'h2', 'text', 'img'] as $type) {
            $btn = Builder::div("pb-tool pb-tool-{$type}")->content(strtoupper($type));
            $btn->attrs()->add('data-type', $type)->add('draggable', 'true');
            $tools->append($btn);
        }

        $layout->append($tools);
        $card->append($layout);

        $form = Form::create('/admin/?p=page:save&domain=' . $domain . '&allias=' . $allias . '&path=' . $path, 'POST');
        $form->csrf($this->csrf->field());
        $form->add(Field::hidden('page_json'));
        $form->submit('Сохранить страницу');
        $card->append($form->build());

        $rulesFile = $baseDir . '/.rules.json';
        $rules = is_file($rulesFile) ? json_decode((string)file_get_contents($rulesFile), true) : [];
        $isAuth = (bool)($rules['auth'] ?? false);

        $initData = json_encode([
            'slots' => $jsSlots,
            'available' => $availableSlots,
            'auth' => $isAuth,
        ], JSON_UNESCAPED_UNICODE);
        $scriptData = Builder::el('script');
        $scriptData->attrs()->add('type', 'application/json');
        $scriptData->attrs()->add('id', 'pb-init-data');
        $scriptData->append(Text::raw((string)$initData));
        $card->append($scriptData);

        return Response::html($this->view->render($card, ['title' => 'Редактор страницы']));
    }

    public function save(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) {
            return $auth;
        }

        $csrfErr = $this->requireCsrf($request);
        if ($csrfErr !== null) {
            return $csrfErr;
        }

        $domain = (string) $request->query('domain', '');
        $allias = (string) $request->query('allias', 'www');
        $path = (string) $request->query('path', '');

        $baseDir = $this->paths->algoritm($domain, $allias) . '/' . $path;
        if (!is_dir($baseDir)) {
            return $this->redirect('/admin/?p=page&domain=' . urlencode($domain));
        }

        $json = (string) ($request->post['page_json'] ?? '');
        $pageData = json_decode($json, true);
        if (!is_array($pageData)) {
            return Response::json(['error' => 'Invalid JSON']);
        }

        // 1. Сохраняем JSON
        Fs::writeAtomic($baseDir . '/page.editor.json', json_encode($pageData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");

        // 2. Удаляем старый .edit.json, чтобы не мешал
        if (is_file($baseDir . '/.edit.json')) {
            @unlink($baseDir . '/.edit.json');
        }

        // 3. Компилируем PHP
        $site = Config::fromFile($this->paths->siteConfig())->all();
        $realDomain = (string) $site['domains_real'][$domain];
        $namespace = (string) ($site['domains_namespace'][$realDomain] ?? str_replace(['.', '-'], '_', $realDomain));

        $classChain = 'algoritm_' . str_replace('/', '_', $path);
        $compiler = new PageCompiler();

        $php = $compiler->compile($namespace, $allias, $classChain, $pageData['slots'] ?? []);
        Fs::writeAtomic($baseDir . '/' . $classChain . '.php', $php);

        // 4. Обновляем .rules.json (auth)
        $rulesFile = $baseDir . '/.rules.json';
        $rules = is_file($rulesFile) ? json_decode((string)file_get_contents($rulesFile), true) : [];
        if (!is_array($rules)) {
            $rules = [];
        }
        $rules['auth'] = (bool)($pageData['auth'] ?? false);
        $rules['available'] = [$path]; // Убедимся, что страница доступна
        Fs::writeAtomic($rulesFile, json_encode($rules, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

        return $this->redirect('/admin/?p=page:edit&domain=' . urlencode($domain) . '&allias=' . urlencode($allias) . '&path=' . urlencode($path) . '&saved=1');
    }

    /**
     * @param array<string, mixed> $node
     * @param array<string, bool> $slots
     */
    private function collectSlots(array $node, array &$slots): void
    {
        if (isset($node['slot']) && is_string($node['slot'])) {
            $slots[$node['slot']] = true;
        }
        if (isset($node['children']) && is_array($node['children'])) {
            foreach ($node['children'] as $child) {
                if (is_array($child)) {
                    $this->collectSlots($child, $slots);
                }
            }
        }
    }

    public function new(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) {
            return $auth;
        }

        $domain = (string) $request->query('domain', '');
        $allias = (string) $request->query('allias', 'www');
        $parent = (string) $request->query('parent', '');

        if ($domain === '') {
            return $this->redirect('/admin/?p=domain');
        }

        $error = '';
        $nameVal = '';

        if ($request->isPost()) {
            $csrfErr = $this->requireCsrf($request);
            if ($csrfErr !== null) {
                return $csrfErr;
            }

            $nameVal = (string) ($request->post['name'] ?? '');
            $cmd = new PageNewCommand($this->paths);
            $res = $cmd->run(['domain' => $domain, 'allias' => $allias, 'parent' => $parent, 'name' => $nameVal], new Context(static fn () => null));

            if ($res->ok) {
                return $this->redirect('/admin/?p=page&domain=' . urlencode($domain) . '&allias=' . urlencode($allias) . ($parent !== '' ? '&path=' . urlencode($parent) : ''));
            }
            $error = implode('; ', $res->errors);
        }

        $form = Form::create('/admin/?p=page:new&domain=' . urlencode($domain) . '&allias=' . urlencode($allias) . '&parent=' . urlencode($parent), 'POST')
            ->csrf($this->csrf->field())
            ->add(Field::text('name')->label(View::t('page.name_label'))->placeholder('about, contacts')->required()->value($nameVal))
            ->submit(View::t('domain.new_submit'));

        $card = Builder::div('admin-card page-builder-app');

        $breadcrumbs = Builder::div('breadcrumbs');
        $breadcrumbs->append(Builder::a('/admin/?p=page&domain=' . urlencode($domain) . '&allias=' . urlencode($allias))->content(View::t('domain.bind_back')));
        $card->append($breadcrumbs);

        $card->append(Builder::h(1)->content(View::t('page.new_title')));
        $card->append(View::alliasTabs($domain, $allias, 'pages'));

        if ($parent !== '') {
            $card->append(Builder::p()->content('Родительская папка: /' . View::esc($parent)));
        }

        if ($error !== '') {
            $card->append(Message::error($error)->build());
        }

        $card->append($form->build());

        return Response::html($this->view->render($card, ['title' => View::t('page.new_title')]));
    }

    public function toggle(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) {
            return $auth;
        }

        if (!$request->isPost()) {
            return $this->redirect('/admin/?p=domain');
        }

        $csrfErr = $this->requireCsrf($request);
        if ($csrfErr !== null) {
            return $csrfErr;
        }

        $domain = (string) $request->query('domain', '');
        $allias = (string) $request->query('allias', 'www');
        $path = (string) ($request->post['path'] ?? '');
        $key = (string) ($request->post['key'] ?? 'auth');
        $value = (string) ($request->post['value'] ?? '1');

        if ($domain !== '') {
            $cmd = new PageToggleCommand($this->paths);
            $cmd->run(['domain' => $domain, 'allias' => $allias, 'path' => $path, 'key' => $key, 'value' => $value], new Context(static fn () => null));
        }

        return $this->redirect('/admin/?p=page&domain=' . urlencode($domain) . '&allias=' . urlencode($allias) . '&path=' . urlencode($path));
    }
}
