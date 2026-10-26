<?php

declare(strict_types=1);

namespace Damoony\Admin\Controller;

use Damoony\Admin\View;
use Damoony\Cli\Commands\DomainNewCommand;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Files\Paths;
use Damoony\Http\Request;
use Damoony\Http\Response;

final class DomainController extends BaseController
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
        if ($auth !== null) {
            return $auth;
        }

        $domains = $this->siteConfig->getByKey(['domains_real'], []);
        
        // Группируем входящие домены по реальному домену
        $grouped = [];
        if (is_array($domains)) {
            foreach ($domains as $incoming => $real) {
                $grouped[$real][] = $incoming;
            }
        }

        $html = '<div class="admin-card"><h1>Домены</h1>';
        $html .= '<p><a href="/admin/?p=domain:new" class="btn">+ Добавить домен</a></p>';
        $html .= '<table style="width:100%; border-collapse:collapse; margin-top:16px;">';
        $html .= '<tr><th style="text-align:left; padding:8px; border-bottom:1px solid #ddd;">Реальный домен</th><th style="text-align:left; padding:8px; border-bottom:1px solid #ddd;">Привязанные домены</th><th style="text-align:left; padding:8px; border-bottom:1px solid #ddd;">Действия</th></tr>';

        foreach ($grouped as $real => $incomingDomains) {
            $ns = (string) $this->siteConfig->getByKey(['domains_namespace', $real], '-');
            
            $html .= '<tr>';
            // Реальный домен
            $html .= '<td style="padding:8px; border-bottom:1px solid #eee;"><b>' . View::esc($real) . '</b><br><small style="color:#666;">ns: ' . View::esc($ns) . '</small></td>';
            
            // Привязанные
            $html .= '<td style="padding:8px; border-bottom:1px solid #eee;">';
            foreach ($incomingDomains as $inc) {
                $html .= '<div>' . View::esc($inc) . ($inc === $real ? ' <small>(основной)</small>' : '') . '</div>';
            }
            $html .= '</td>';
            
            // Действия
            $html .= '<td style="padding:8px; border-bottom:1px solid #eee;">';
            $html .= '<a href="/admin/?p=allias&domain=' . urlencode($real) . '">Алиасы</a> | ';
            $html .= '<a href="/admin/?p=block&domain=' . urlencode($real) . '">Блоки</a> | ';
            $html .= '<a href="/admin/?p=page&domain=' . urlencode($real) . '&allias=www">Страницы</a> | ';
            $html .= '<a href="/admin/?p=scheme&domain=' . urlencode($real) . '">Схемы</a> | ';
            $html .= '<a href="/admin/?p=filler&domain=' . urlencode($real) . '&allias=www">Fillers</a> | ';
            $html .= '<a href="/admin/?p=media&domain=' . urlencode($real) . '">Медиа</a> | ';
            $html .= '<a href="/admin/?p=settings">Настройки</a>';
            $html .= '</td>';
            $html .= '</tr>';
        }
        $html .= '</table></div>';

        return Response::html($this->view->render($html, ['title' => 'Домены']));
    }

    public function new(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) return $auth;

        $error = '';
        if ($request->isPost()) {
            $csrfErr = $this->requireCsrf($request);
            if ($csrfErr !== null) return $csrfErr;

            $domain = (string) ($request->post['domain'] ?? '');
            $ns = (string) ($request->post['namespace'] ?? '');

            $cmd = new DomainNewCommand($this->paths);
            $result = new Result();
            $ctx = new Context(static fn() => null); // Step handler stub
            $res = $cmd->run(['domain' => $domain, 'namespace' => $ns], $ctx);

            if ($res->ok) {
                return $this->redirect('/admin/?p=domain');
            }
            $error = implode('; ', $res->errors);
        }

        $html = '<div class="admin-card"><h1>Добавить домен</h1>';
        if ($error !== '') $html .= '<div class="error">' . View::esc($error) . '</div>';
        $html .= '<form method="post" class="admin-form">';
        $html .= $this->csrf->field();
        $html .= '<label>Домен (например, example.ru)<input type="text" name="domain" required></label>';
        $html .= '<label>Namespace (латиница, опционально)<input type="text" name="namespace"></label>';
        $html .= '<button type="submit">Создать</button>';
        $html .= '<a href="/admin/?p=domain" style="text-align:center;">Отмена</a>';
        $html .= '</form></div>';

        return Response::html($this->view->render($html, ['title' => 'Новый домен']));
    }
}