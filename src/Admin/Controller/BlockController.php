<?php

declare(strict_types=1);

namespace Damoony\Admin\Controller;

use Damoony\Admin\View;
use Damoony\Cli\Commands\BlockNewCommand;
use Damoony\Cli\Commands\BlockRemoveCommand;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Files\Fs;
use Damoony\Files\Paths;
use Damoony\Http\Request;
use Damoony\Http\Response;

final class BlockController extends BaseController
{
    public function __construct(
        \Damoony\Admin\AuthInterface $auth,
        \Damoony\Security\Csrf $csrf,
        View $view,
        private readonly Paths $paths,
        private readonly Config $siteConfig,
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
        $manifestFile = $this->paths->domain($logic, $domain) . '/alliasglobal/blocks.json';
        $manifest = is_file($manifestFile) ? json_decode((string) file_get_contents($manifestFile), true) : [];
        $blocks = is_array($manifest) && isset($manifest['blocks']) ? array_keys($manifest['blocks']) : [];

        $html = '<div class="admin-card"><h1>Блоки: ' . View::esc($domain) . '</h1>';
        $html .= '<p><a href="/admin/?p=domain">&larr; Назад</a> | <a href="/admin/?p=block:new&domain=' . urlencode($domain) . '">+ Добавить блок</a></p>';
        $html .= '<table style="width:100%; border-collapse:collapse; margin-top:16px;">';
        $html .= '<tr><th style="text-align:left; padding:8px; border-bottom:1px solid #ddd;">Имя</th><th style="text-align:left; padding:8px; border-bottom:1px solid #ddd;">Действия</th></tr>';

        foreach ($blocks as $b) {
            if (!is_string($b)) continue;
            $html .= '<tr><td style="padding:8px; border-bottom:1px solid #eee;">' . View::esc($b) . '</td>';
            $html .= '<td style="padding:8px; border-bottom:1px solid #eee;"><a href="/admin/?p=block:remove&domain=' . urlencode($domain) . '&name=' . urlencode($b) . '" style="color:red;">Удалить</a></td></tr>';
        }
        $html .= '</table></div>';

        return Response::html($this->view->render($html, ['title' => 'Блоки']));
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
            $cmd = new BlockNewCommand($this->paths);
            $res = $cmd->run(['domain' => $domain, 'name' => $name], new Context(static fn() => null));

            if ($res->ok) {
                return $this->redirect('/admin/?p=block&domain=' . urlencode($domain));
            }
            $error = implode('; ', $res->errors);
        }

        $html = '<div class="admin-card"><h1>Новый блок: ' . View::esc($domain) . '</h1>';
        if ($error !== '') $html .= '<div class="error">' . View::esc($error) . '</div>';
        $html .= '<form method="post" class="admin-form">' . $this->csrf->field();
        $html .= '<input type="hidden" name="domain" value="' . View::esc($domain) . '">';
        $html .= '<label>Имя блока (латиница)<input type="text" name="name" required></label>';
        $html .= '<button type="submit">Создать</button><a href="/admin/?p=block&domain=' . urlencode($domain) . '">Отмена</a></form></div>';

        return Response::html($this->view->render($html, ['title' => 'Новый блок']));
    }

    public function remove(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) return $auth;

        $domain = (string) $request->query('domain', '');
        $name = (string) $request->query('name', '');

        if ($domain !== '' && $name !== '') {
            $cmd = new BlockRemoveCommand($this->paths);
            $cmd->run(['domain' => $domain, 'name' => $name], new Context(static fn() => null));
        }

        return $this->redirect('/admin/?p=block&domain=' . urlencode($domain));
    }
}