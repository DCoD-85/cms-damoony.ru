<?php

declare(strict_types=1);

namespace Damoony\Admin\Controller;

use Damoony\Admin\View;
use Damoony\Cli\Commands\SchemeNewCommand;
use Damoony\Cli\Context;
use Damoony\Core\Config;
use Damoony\Files\Fs;
use Damoony\Files\Paths;
use Damoony\Http\Request;
use Damoony\Http\Response;

final class SchemeController extends BaseController
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
        if ($domain === '') return $this->redirect('/admin/?p=domain');

        $logic = 'alpha.0.0';
        $manifestFile = $this->paths->domain($logic, $domain) . '/alliasscheme/schemes.json';
        $manifest = is_file($manifestFile) ? json_decode((string) file_get_contents($manifestFile), true) : [];
        $schemes = is_array($manifest) && isset($manifest['schemes']) ? array_keys($manifest['schemes']) : [];

        $html = '<div class="admin-card"><h1>Схемы: ' . View::esc($domain) . '</h1>';
        $html .= '<p><a href="/admin/?p=domain">&larr; Назад</a> | <a href="/admin/?p=scheme:new&domain=' . urlencode($domain) . '">+ Добавить схему</a></p>';
        $html .= '<table style="width:100%; border-collapse:collapse; margin-top:16px;">';
        $html .= '<tr><th style="text-align:left; padding:8px; border-bottom:1px solid #ddd;">Имя</th></tr>';

        foreach ($schemes as $s) {
            if (!is_string($s)) continue;
            $html .= '<tr><td style="padding:8px; border-bottom:1px solid #eee;">' . View::esc($s) . '</td></tr>';
        }
        $html .= '</table></div>';

        return Response::html($this->view->render($html, ['title' => 'Схемы']));
    }

    public function new(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) return $auth;

        $domain = (string) ($request->query('domain', '') ?: ($request->post['domain'] ?? ''));
        if ($domain === '') return $this->redirect('/admin/?p=domain');

        $error = '';
        if ($request->isPost()) {
            $csrfErr = $this->requireCsrf($request);
            if ($csrfErr !== null) return $csrfErr;

            $name = (string) ($request->post['name'] ?? '');
            $cmd = new SchemeNewCommand($this->paths);
            $res = $cmd->run(['domain' => $domain, 'name' => $name], new Context(static fn() => null));

            if ($res->ok) {
                return $this->redirect('/admin/?p=scheme&domain=' . urlencode($domain));
            }
            $error = implode('; ', $res->errors);
        }

        $html = '<div class="admin-card"><h1>Новая схема: ' . View::esc($domain) . '</h1>';
        if ($error !== '') $html .= '<div class="error">' . View::esc($error) . '</div>';
        $html .= '<form method="post" class="admin-form">' . $this->csrf->field();
        $html .= '<input type="hidden" name="domain" value="' . View::esc($domain) . '">';
        $html .= '<label>Имя схемы (например, visuo)<input type="text" name="name" required></label>';
        $html .= '<button type="submit">Создать</button><a href="/admin/?p=scheme&domain=' . urlencode($domain) . '">Отмена</a></form></div>';

        return Response::html($this->view->render($html, ['title' => 'Новая схема']));
    }
}