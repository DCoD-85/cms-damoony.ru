<?php

declare(strict_types=1);

namespace Damoony\Admin\Controller;

use Damoony\Admin\View;
use Damoony\Cli\Commands\AlliasNewCommand;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Files\Paths;
use Damoony\Http\Request;
use Damoony\Http\Response;

final class AlliasController extends BaseController
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

        $domain = $request->query('domain', '');
        if ($domain === '') {
            return $this->redirect('/admin/?p=domain');
        }

        $aliases = $this->siteConfig->getByKey(['alliases_real', $domain], []);
        
        $html = '<div class="admin-card"><h1>Алиасы: ' . View::esc($domain) . '</h1>';
        $html .= '<p><a href="/admin/?p=domain" style="margin-right:16px;">&larr; Назад к доменам</a>';
        $html .= '<a href="/admin/?p=allias:new&domain=' . urlencode($domain) . '" class="btn">+ Добавить алиас</a></p>';
        
        $html .= '<table style="width:100%; border-collapse:collapse; margin-top:16px;">';
        $html .= '<tr><th style="text-align:left; padding:8px; border-bottom:1px solid #ddd;">Алиас</th><th style="text-align:left; padding:8px; border-bottom:1px solid #ddd;">Путь</th></tr>';

        if (is_array($aliases)) {
            foreach ($aliases as $name => $mapped) {
                if ($name === '') continue;
                $html .= '<tr>';
                $html .= '<td style="padding:8px; border-bottom:1px solid #eee;">' . View::esc($name) . '</td>';
                $html .= '<td style="padding:8px; border-bottom:1px solid #eee;">' . View::esc($domain . '/' . $name) . '</td>';
                $html .= '</tr>';
            }
        }
        $html .= '</table></div>';

        return Response::html($this->view->render($html, ['title' => 'Алиасы']));
    }

    public function new(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) return $auth;

        $domain = $request->query('domain', '') ?? (string)($request->post['domain'] ?? '');
        if ($domain === '') {
            return $this->redirect('/admin/?p=domain');
        }

        $error = '';
        if ($request->isPost()) {
            $csrfErr = $this->requireCsrf($request);
            if ($csrfErr !== null) return $csrfErr;

            $name = (string) ($request->post['name'] ?? '');

            $cmd = new AlliasNewCommand($this->paths);
            $result = new Result();
            $ctx = new Context(static fn() => null);
            $res = $cmd->run(['domain' => $domain, 'name' => $name], $ctx);

            if ($res->ok) {
                return $this->redirect('/admin/?p=allias&domain=' . urlencode($domain));
            }
            $error = implode('; ', $res->errors);
        }

        $html = '<div class="admin-card"><h1>Добавить алиас для ' . View::esc($domain) . '</h1>';
        if ($error !== '') $html .= '<div class="error">' . View::esc($error) . '</div>';
        $html .= '<form method="post" class="admin-form">';
        $html .= $this->csrf->field();
        $html .= '<input type="hidden" name="domain" value="' . View::esc($domain) . '">';
        $html .= '<label>Имя алиаса (например, admin, www)<input type="text" name="name" required></label>';
        $html .= '<button type="submit">Создать</button>';
        $html .= '<a href="/admin/?p=allias&domain=' . urlencode($domain) . '" style="text-align:center;">Отмена</a>';
        $html .= '</form></div>';

        return Response::html($this->view->render($html, ['title' => 'Новый алиас']));
    }
}