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
        if ($auth !== null) return $auth;

        $domain = (string) $request->query('domain', '');
        $allias = (string) $request->query('allias', 'www');
        $path = (string) $request->query('path', ''); // relative path inside algoritm

        if ($domain === '') return $this->redirect('/admin/?p=domain');

        $baseDir = $this->paths->algoritm($domain, $allias) . ($path === '' ? '' : '/' . $path);
        
        // Собираем страницы (папки) в текущей директории
        $pages = [];
        if (is_dir($baseDir)) {
            $items = scandir($baseDir) ?: [];
            foreach ($items as $item) {
                if ($item === '.' || $item === '..' || !is_dir($baseDir . '/' . $item)) continue;
                $pages[] = $item;
            }
        }
        sort($pages);

        $card = Builder::div('admin-card');

        // Хлебные крошки
        $breadcrumbs = Builder::div('breadcrumbs');
        $breadcrumbs->append(Builder::a('/admin/?p=domain')->content('Домены'));
        $breadcrumbs->append(Text::raw(' / '));
        $breadcrumbs->append(Builder::a('/admin/?p=domain:edit&domain=' . urlencode($domain))->content($domain));
        $card->append($breadcrumbs);

        // Заголовок и кнопка
        $header = Builder::div('page-header');
        $header->append(Builder::h(1)->content('Страницы: ' . View::esc($allias)));
        
        $actions = Builder::div('page-actions');
        // Кнопка "На уровень выше" (если не в корне)
        if ($path !== '') {
            $parentPath = dirname($path);
            $upUrl = '/admin/?p=page&domain=' . urlencode($domain) . '&allias=' . urlencode($allias) . ($parentPath === '.' ? '' : '&path=' . urlencode($parentPath));
            $actions->append(Builder::a($upUrl, 'btn btn-outline')->content('&larr; Наверх'));
        }
        $actions->append(Builder::a('/admin/?p=page:new&domain=' . urlencode($domain) . '&allias=' . urlencode($allias) . '&parent=' . urlencode($path), 'btn btn-primary')->content('+ Создать страницу'));
        $header->append($actions);
        $card->append($header);

        // Табы
        $card->append(View::domainTabs($domain, 'pages'));

        // Текущий путь
        if ($path !== '') {
            $pathInfo = Builder::p('text-muted')->content('Текущий путь: /' . View::esc($path));
			$pathInfo->attrs()->styleAdd('margin-bottom: 16px;');
			$card->append($pathInfo);
        }

        // Таблица
        $table = (new Table())->headers('Страница', 'Действия');

        if ($pages === []) {
            $table->row([Text::raw('Страниц пока нет'), Text::raw('')]);
        } else {
            foreach ($pages as $page) {
                $newPath = $path === '' ? $page : $path . '/' . $page;
                
                // Ссылка на вход внутрь папки
                $folderUrl = '/admin/?p=page&domain=' . urlencode($domain) . '&allias=' . urlencode($allias) . '&path=' . urlencode($newPath);
                $pageLink = Builder::a($folderUrl, 'domain-link')->content('/' . View::esc($newPath));
                
                // Кнопка редактирования PHP-файла
                $phpFile = 'algoritm/' . ($path === '' ? '' : $path . '/') . $page . '/algoritm_' . str_replace('/', '_', $newPath) . '.php';
                $editUrl = '/admin/?p=editor&domain=' . urlencode($domain) . '&file=' . urlencode($phpFile);

                $actionsHtml = Builder::div('table-actions');
                $actionsHtml->append(Builder::a($editUrl, 'btn btn-outline')->content('Редактировать'));

                $table->row([
                    Text::raw('<div style="display:flex; align-items:center; gap:8px;">📁 ' . $pageLink->release() . '</div>'),
                    $actionsHtml
                ]);
            }
        }
        $card->append($table->build());

        return Response::html($this->view->render($card, ['title' => 'Страницы: ' . $domain]));
    }

    public function new(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) return $auth;

        $domain = (string) $request->query('domain', '');
        $allias = (string) $request->query('allias', 'www');
        $parent = (string) $request->query('parent', '');

        if ($domain === '') return $this->redirect('/admin/?p=domain');

        $error = '';
        $nameVal = '';

        if ($request->isPost()) {
            $csrfErr = $this->requireCsrf($request);
            if ($csrfErr !== null) return $csrfErr;

            $nameVal = (string) ($request->post['name'] ?? '');
            $cmd = new PageNewCommand($this->paths);
            $res = $cmd->run(['domain' => $domain, 'allias' => $allias, 'parent' => $parent, 'name' => $nameVal], new Context(static fn() => null));

            if ($res->ok) {
                return $this->redirect('/admin/?p=page&domain=' . urlencode($domain) . '&allias=' . urlencode($allias) . ($parent !== '' ? '&path=' . urlencode($parent) : ''));
            }
            $error = implode('; ', $res->errors);
        }

        $form = Form::create('/admin/?p=page:new&domain=' . urlencode($domain) . '&allias=' . urlencode($allias) . '&parent=' . urlencode($parent), 'POST')
            ->csrf($this->csrf->field())
            ->add(Field::text('name')->label('Имя страницы')->placeholder('about, contacts')->required()->value($nameVal))
            ->submit('Создать');

        $card = Builder::div('admin-card');

        // Хлебные крошки
        $breadcrumbs = Builder::div('breadcrumbs');
        $breadcrumbs->append(Builder::a('/admin/?p=domain')->content('Домены'));
        $breadcrumbs->append(Text::raw(' / '));
        $breadcrumbs->append(Builder::a('/admin/?p=domain:edit&domain=' . urlencode($domain))->content($domain));
        $card->append($breadcrumbs);

        $card->append(Builder::h(1)->content('Создать страницу'));
        $card->append(View::domainTabs($domain, 'pages'));
        
        if ($parent !== '') {
            $card->append(Builder::p()->content('Родительская папка: /' . View::esc($parent)));
        }
        
        if ($error !== '') {
            $card->append(Message::error($error)->build());
        }
        
        $card->append($form->build());

        return Response::html($this->view->render($card, ['title' => 'Новая страница']));
    }

    public function toggle(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) return $auth;

        $domain = (string) $request->query('domain', '');
        $allias = (string) $request->query('allias', 'www');
        $path = (string) $request->query('path', '');
        $key = (string) $request->query('key', 'auth');
        $value = (string) $request->query('value', '1');

        if ($domain !== '') {
            $cmd = new PageToggleCommand($this->paths);
            $cmd->run(['domain' => $domain, 'allias' => $allias, 'path' => $path, 'key' => $key, 'value' => $value], new Context(static fn() => null));
        }

        return $this->redirect('/admin/?p=page&domain=' . urlencode($domain) . '&allias=' . urlencode($allias) . '&path=' . urlencode($path));
    }
}