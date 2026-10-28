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
use Damoony\Ui\Table;
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
        $path = (string) $request->query('path', '');

        if ($domain === '') {
            return $this->redirect('/admin/?p=domain');
        }

        $baseDir = $this->paths->algoritm($domain, $allias) . ($path === '' ? '' : '/' . $path);

        $pages = [];
        if (is_dir($baseDir)) {
            $items = scandir($baseDir) ?: [];
            foreach ($items as $item) {
                if ($item === '.' || $item === '..' || !is_dir($baseDir . '/' . $item)) {
                    continue;
                }
                $pages[] = $item;
            }
        }
        sort($pages);

        $card = Builder::div('admin-card');

        $breadcrumbs = Builder::div('breadcrumbs');
        $breadcrumbs->append(Builder::a('/admin/?p=domain')->content(View::t('domain.bread_domains')));
        $breadcrumbs->append(Text::raw(' / '));
        $breadcrumbs->append(Builder::a('/admin/?p=domain:edit&domain=' . urlencode($domain))->content($domain));
        $breadcrumbs->append(Text::raw(' / '));
        $breadcrumbs->append(Builder::a('/admin/?p=allias&domain=' . urlencode($domain))->content(View::t('tab.alliases')));
        $card->append($breadcrumbs);

        $header = Builder::div('page-header');
        $header->append(Builder::h(1)->content(View::t('page.title') . ': ' . View::esc($allias)));

        $actions = Builder::div('page-actions');
        if ($path !== '') {
            $parentPath = dirname($path);
            $upUrl = '/admin/?p=page&domain=' . urlencode($domain) . '&allias=' . urlencode($allias) . ($parentPath === '.' ? '' : '&path=' . urlencode($parentPath));
            $actions->append(Builder::a($upUrl, 'btn btn-outline')->content(View::t('page.up')));
        }
        $actions->append(Builder::a('/admin/?p=page:new&domain=' . urlencode($domain) . '&allias=' . urlencode($allias) . '&parent=' . urlencode($path), 'btn btn-primary')->content(View::t('domain.add')));
        $header->append($actions);
        $card->append($header);

        $card->append(View::alliasTabs($domain, $allias, 'pages'));

        if ($path !== '') {
            $pathInfo = Builder::p('text-muted')->content(View::t('page.current_path', ['path' => View::esc($path)]));
            $pathInfo->attrs()->styleAdd('margin-bottom: 16px;');
            $card->append($pathInfo);
        }

        $table = (new Table())->headers(
            View::t('page.th_page'),
            View::t('page.th_auth'),
            View::t('page.th_search'),
            View::t('page.th_actions'),
        );

        if ($pages === []) {
            $table->row([Text::raw(View::t('page.empty')), Text::raw(''), Text::raw(''), Text::raw('')]);
        } else {
            foreach ($pages as $page) {
                $newPath = $path === '' ? $page : $path . '/' . $page;
                $pageDir = $baseDir . '/' . $page;

                $rulesFile = $pageDir . '/.rules.json';
                $rules = is_file($rulesFile) ? json_decode((string)file_get_contents($rulesFile), true) : [];
                if (!is_array($rules)) {
                    $rules = [];
                }
                $isAuth = (bool)($rules['auth'] ?? false);
                $isSearch = (bool)($rules['search'] ?? true);

                $folderUrl = '/admin/?p=page&domain=' . urlencode($domain) . '&allias=' . urlencode($allias) . '&path=' . urlencode($newPath);
                $pageLink = Builder::a($folderUrl, 'domain-link')->content('/' . View::esc($newPath));

                $makeToggle = function (string $key, bool $val) use ($domain, $allias, $newPath) {
                    $form = Form::create('/admin/?p=page:toggle&domain=' . urlencode($domain) . '&allias=' . urlencode($allias), 'POST')
                        ->csrf($this->csrf->field())
                        ->add(Field::hidden('path')->value($newPath))
                        ->add(Field::hidden('key')->value($key))
                        ->add(Field::hidden('value')->value($val ? '0' : '1'))
                        ->submit($val ? View::t('page.on') : View::t('page.off'));

                    $btn = $form->build();
                    foreach ($btn->children() as $child) {
                        if ($child instanceof Element && $child->tag() === 'button') {
                            $child->attrs()->classAdd('btn ' . ($val ? 'btn-outline' : 'btn-danger'));
                            $child->attrs()->styleAdd('padding: 4px 10px; font-size: 12px; min-width: 50px;');
                        }
                    }
                    return $btn;
                };

                $authBtn = $makeToggle('auth', $isAuth);
                $searchBtn = $makeToggle('search', $isSearch);

                $actionsHtml = Builder::div('table-actions');

                // Ссылка на визуальный редактор
                $visualUrl = '/admin/?p=page:edit&domain=' . urlencode($domain) . '&allias=' . urlencode($allias) . '&path=' . urlencode($newPath);
                $actionsHtml->append(Builder::a($visualUrl, 'btn btn-primary')->content(View::t('page.visual_editor')));

                // Ссылка на код
                $phpFile = 'algoritm/' . ($path === '' ? '' : $path . '/') . $page . '/algoritm_' . str_replace('/', '_', $newPath) . '.php';
                $editUrl = '/admin/?p=editor&domain=' . urlencode($domain) . '&file=' . urlencode($phpFile);
                $actionsHtml->append(Builder::a($editUrl, 'btn btn-outline')->content(View::t('page.edit')));

                $table->row([
                    Text::raw('<div style="display:flex; align-items:center; gap:8px;">📁 ' . $pageLink->release() . '</div>'),
                    $authBtn,
                    $searchBtn,
                    $actionsHtml,
                ]);
            }
        }
        $card->append($table->build());

        return Response::html($this->view->render($card, ['title' => View::t('page.title')]));
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

        $editFile = $baseDir . '/.edit.json';
        $data = is_file($editFile) ? json_decode((string) file_get_contents($editFile), true) : [];
        $blocks = is_array($data) && isset($data['blocks']) ? $data['blocks'] : [];

        $card = Builder::div('admin-card');

        $breadcrumbs = Builder::div('breadcrumbs');
        $breadcrumbs->append(Builder::a('/admin/?p=domain')->content(View::t('domain.bread_domains')));
        $breadcrumbs->append(Text::raw(' / '));
        $breadcrumbs->append(Builder::a('/admin/?p=page&domain=' . urlencode($domain) . '&allias=' . urlencode($allias))->content(View::t('page.title')));
        $card->append($breadcrumbs);

        $card->append(Builder::h(1)->content('Визуальный редактор: /' . View::esc($path)));
        $card->append(View::alliasTabs($domain, $allias, 'pages'));

        $form = '<form method="post" action="/admin/?p=page:save&domain=' . urlencode($domain) . '&allias=' . urlencode($allias) . '&path=' . urlencode($path) . '" class="editor-form">';
        $form .= $this->csrf->field();

        $form .= '<div id="blocks-container">';
        if ($blocks === []) {
            $blocks = [['type' => 'text', 'content' => '']]; // Один блок по умолчанию
        }
        foreach ($blocks as $i => $block) {
            $form .= $this->renderBlockRow($i, $block);
        }
        $form .= '</div>';

        $form .= '<div style="margin-top: 15px; display:flex; gap:10px;">';
        $form .= '<button type="button" id="add-block-btn" class="btn btn-outline">' . View::t('page.add_block') . '</button>';
        $form .= '<button type="submit" class="btn btn-primary">' . View::t('domain.save') . '</button>';
        $form .= '</div>';
        $form .= '</form>';

        $card->append(Text::raw($form));

        return Response::html($this->view->render($card, ['title' => 'Визуальный редактор']));
    }

    /**
     * @param array<string, mixed> $block
     */
    private function renderBlockRow(int $index, array $block): string
    {
        $type = $block['type'] ?? 'text';
        $content = htmlspecialchars((string)($block['content'] ?? ''), ENT_QUOTES);

        $html = '<div class="block-row" style="display:flex; gap:10px; margin-bottom:10px; padding:10px; border:1px solid #ddd; border-radius:8px; background:#fafafa;">';

        $html .= '<select name="blocks[' . $index . '][type]" style="width: 200px; padding:8px; border-radius:6px; border:1px solid #ccc;">';
        $html .= '<option value="h1"' . ($type === 'h1' ? ' selected' : '') . '>' . View::t('page.type_h1') . '</option>';
        $html .= '<option value="h2"' . ($type === 'h2' ? ' selected' : '') . '>' . View::t('page.type_h2') . '</option>';
        $html .= '<option value="text"' . ($type === 'text' ? ' selected' : '') . '>' . View::t('page.type_text') . '</option>';
        $html .= '<option value="img"' . ($type === 'img' ? ' selected' : '') . '>' . View::t('page.type_img') . '</option>';
        $html .= '</select>';

        $html .= '<input type="text" name="blocks[' . $index . '][content]" value="' . $content . '" placeholder="' . View::t('page.block_content') . '" style="flex-grow:1; padding:8px; border-radius:6px; border:1px solid #ccc;">';

        $html .= '<button type="button" class="btn btn-danger remove-block-btn" style="padding: 0 15px;">✕</button>';

        $html .= '</div>';

        return $html;
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

        $blocks = $request->post['blocks'] ?? [];
        if (!is_array($blocks)) {
            $blocks = [];
        }

        $cleanBlocks = [];
        foreach ($blocks as $b) {
            if (isset($b['type'], $b['content']) && trim((string)$b['content']) !== '') {
                $cleanBlocks[] = [
                    'type' => (string)$b['type'],
                    'content' => (string)$b['content'],
                ];
            }
        }

        $editFile = $baseDir . '/.edit.json';
        Fs::writeAtomic($editFile, json_encode(['blocks' => $cleanBlocks], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");

        // Компилируем в PHP
        $siteFile = $this->paths->siteConfig();
        $site = Config::fromFile($siteFile)->all();
        $realDomain = (string) $site['domains_real'][$domain];
        $namespace = (string) ($site['domains_namespace'][$realDomain] ?? str_replace(['.', '-'], '_', $realDomain));

        $classChain = 'algoritm_' . str_replace('/', '_', $path);
        $compiler = new PageCompiler();
        $php = $compiler->compile($namespace, $allias, $classChain, $cleanBlocks);

        $phpFile = $baseDir . '/' . $classChain . '.php';
        Fs::writeAtomic($phpFile, $php);

        return $this->redirect('/admin/?p=page:edit&domain=' . urlencode($domain) . '&allias=' . urlencode($allias) . '&path=' . urlencode($path) . '&saved=1');
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

        $card = Builder::div('admin-card');

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
