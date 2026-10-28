<?php

declare(strict_types=1);

namespace Damoony\Admin\Controller;

use Damoony\Admin\View;
use Damoony\Cli\Commands\PageNewCommand;
use Damoony\Cli\Commands\PageToggleCommand;
use Damoony\Cli\Context;
use Damoony\Files\Paths;
use Damoony\Http\Request;
use Damoony\Http\Response;
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

                // Читаем .rules.json для флагов
                $rulesFile = $pageDir . '/.rules.json';
                $rules = is_file($rulesFile) ? json_decode((string)file_get_contents($rulesFile), true) : [];
                if (!is_array($rules)) {
                    $rules = [];
                }
                $isAuth = (bool)($rules['auth'] ?? false);
                $isSearch = (bool)($rules['search'] ?? true);

                // Ссылка
                $folderUrl = '/admin/?p=page&domain=' . urlencode($domain) . '&allias=' . urlencode($allias) . '&path=' . urlencode($newPath);
                $pageLink = Builder::a($folderUrl, 'domain-link')->content('/' . View::esc($newPath));

                // Генератор форм для переключения
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

                // Кнопка редактирования
                $phpFile = 'algoritm/' . ($path === '' ? '' : $path . '/') . $page . '/algoritm_' . str_replace('/', '_', $newPath) . '.php';
                $editUrl = '/admin/?p=editor&domain=' . urlencode($domain) . '&file=' . urlencode($phpFile);
                $actionsHtml = Builder::div('table-actions');
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
