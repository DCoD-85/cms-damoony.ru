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

        $logic = 'alpha.0.0';
        $baseDir = $this->paths->algoritm($logic, $domain, $allias) . ($path === '' ? '' : '/' . $path);
        
        $html = '<div class="admin-card"><h1>Страницы: ' . View::esc($domain . '/' . $allias) . ($path !== '' ? '/' . View::esc($path) : '') . '</h1>';
        $html .= '<p><a href="/admin/?p=domain">&larr; Назад к доменам</a> | <a href="/admin/?p=page:new&domain=' . urlencode($domain) . '&allias=' . urlencode($allias) . '&parent=' . urlencode($path) . '">+ Добавить страницу</a></p>';

        if (!Fs::isDir($baseDir)) {
            $html .= '<div class="error">Каталог не найден</div></div>';
            return Response::html($this->view->render($html, ['title' => 'Страницы']));
        }

        $items = scandir($baseDir) ?: [];
        $dirs = [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || !is_dir($baseDir . '/' . $item)) continue;
            $dirs[] = $item;
        }
        sort($dirs);

        $html .= '<table style="width:100%; border-collapse:collapse; margin-top:16px;">';
        $html .= '<tr><th style="text-align:left; padding:8px; border-bottom:1px solid #ddd;">Путь</th><th style="text-align:left; padding:8px; border-bottom:1px solid #ddd;">Действия</th></tr>';

        if ($dirs === []) $html .= '<tr><td colspan="2" style="padding:8px;">Страниц нет</td></tr>';
        
        foreach ($dirs as $d) {
            $newPath = $path === '' ? $d : $path . '/' . $d;
            $html .= '<tr><td style="padding:8px; border-bottom:1px solid #eee;"><a href="/admin/?p=page&domain=' . urlencode($domain) . '&allias=' . urlencode($allias) . '&path=' . urlencode($newPath) . '">' . View::esc($newPath) . '</a></td>';
            $html .= '<td style="padding:8px; border-bottom:1px solid #eee;">';
            $html .= '<a href="/admin/?p=page:toggle&domain=' . urlencode($domain) . '&allias=' . urlencode($allias) . '&path=' . urlencode($newPath) . '&key=auth&value=1">Auth On</a> | ';
            $html .= '<a href="/admin/?p=page:toggle&domain=' . urlencode($domain) . '&allias=' . urlencode($allias) . '&path=' . urlencode($newPath) . '&key=auth&value=0">Auth Off</a>';
            $html .= '</td></tr>';
        }
        $html .= '</table></div>';

        return Response::html($this->view->render($html, ['title' => 'Страницы']));
    }

    public function new(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) return $auth;

        $domain = (string) ($request->query('domain', '') ?: ($request->post['domain'] ?? ''));
        $allias = (string) ($request->query('allias', 'www') ?: ($request->post['allias'] ?? 'www'));
        $parent = (string) ($request->query('parent', '') ?: ($request->post['parent'] ?? ''));

        if ($domain === '') return $this->redirect('/admin/?p=domain');

        $error = '';
        if ($request->isPost()) {
            $csrfErr = $this->requireCsrf($request);
            if ($csrfErr !== null) return $csrfErr;

            $name = (string) ($request->post['name'] ?? '');
            $cmd = new PageNewCommand($this->paths);
            $res = $cmd->run(['domain' => $domain, 'allias' => $allias, 'parent' => $parent, 'name' => $name], new Context(static fn() => null));

            if ($res->ok) {
                return $this->redirect('/admin/?p=page&domain=' . urlencode($domain) . '&allias=' . urlencode($allias) . '&path=' . urlencode($parent));
            }
            $error = implode('; ', $res->errors);
        }

        $html = '<div class="admin-card"><h1>Новая страница</h1>';
        if ($error !== '') $html .= '<div class="error">' . View::esc($error) . '</div>';
        $html .= '<form method="post" class="admin-form">' . $this->csrf->field();
        $html .= '<input type="hidden" name="domain" value="' . View::esc($domain) . '">';
        $html .= '<input type="hidden" name="allias" value="' . View::esc($allias) . '">';
        $html .= '<input type="hidden" name="parent" value="' . View::esc($parent) . '">';
        $html .= '<label>Имя страницы (латиница)<input type="text" name="name" required></label>';
        $html .= '<button type="submit">Создать</button><a href="/admin/?p=page&domain=' . urlencode($domain) . '&allias=' . urlencode($allias) . '">Отмена</a></form></div>';

        return Response::html($this->view->render($html, ['title' => 'Новая страница']));
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