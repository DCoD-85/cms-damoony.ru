<?php

declare(strict_types=1);

namespace Damoony\Admin\Controller;

use Damoony\Admin\View;
use Damoony\Cli\Commands\FillerNewCommand;
use Damoony\Cli\Commands\FillerToggleCommand;
use Damoony\Cli\Context;
use Damoony\Core\Config;
use Damoony\Files\Paths;
use Damoony\Http\Request;
use Damoony\Http\Response;

final class FillerController extends BaseController
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
        $allias = (string) $request->query('allias', '');
        
        if ($domain === '' || $allias === '') {
            return $this->redirect('/admin/?p=domain');
        }

        $fillers = $this->siteConfig->getByKey(['fillers', $domain, $allias], []);
        
        $html = '<div class="admin-card"><h1>Fillers: ' . View::esc($domain . '/' . $allias) . '</h1>';
        $html .= '<p><a href="/admin/?p=allias&domain=' . urlencode($domain) . '">&larr; Назад</a> | <a href="/admin/?p=filler:new&domain=' . urlencode($domain) . '&allias=' . urlencode($allias) . '">+ Добавить filler</a></p>';
        $html .= '<table style="width:100%; border-collapse:collapse; margin-top:16px;">';
        $html .= '<tr><th style="text-align:left; padding:8px; border-bottom:1px solid #ddd;">Блок</th><th style="text-align:left; padding:8px; border-bottom:1px solid #ddd;">Статус</th><th style="text-align:left; padding:8px; border-bottom:1px solid #ddd;">Действия</th></tr>';

        if (is_array($fillers)) {
            foreach ($fillers as $block => $enabled) {
                $status = $enabled ? 'Вкл' : 'Выкл';
                $color = $enabled ? 'green' : 'red';
                $toggleAction = $enabled ? '0' : '1';
                $toggleLabel = $enabled ? 'Выключить' : 'Включить';
                
                $html .= '<tr><td style="padding:8px; border-bottom:1px solid #eee;">' . View::esc((string)$block) . '</td>';
                $html .= '<td style="padding:8px; border-bottom:1px solid #eee; color:' . $color . ';">' . $status . '</td>';
                $html .= '<td style="padding:8px; border-bottom:1px solid #eee;">';
                $html .= '<a href="/admin/?p=filler:toggle&domain=' . urlencode($domain) . '&allias=' . urlencode($allias) . '&block=' . urlencode((string)$block) . '&value=' . $toggleAction . '">' . $toggleLabel . '</a>';
                $html .= '</td></tr>';
            }
        }
        $html .= '</table></div>';

        return Response::html($this->view->render($html, ['title' => 'Fillers']));
    }

    public function new(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) return $auth;

        $domain = (string) $request->query('domain', '');
        $allias = (string) $request->query('allias', '');
        if ($domain === '' || $allias === '') return $this->redirect('/admin/?p=domain');

        $error = '';
        if ($request->isPost()) {
            $csrfErr = $this->requireCsrf($request);
            if ($csrfErr !== null) return $csrfErr;

            $block = (string) ($request->post['block'] ?? '');
            $cmd = new FillerNewCommand($this->paths);
            $res = $cmd->run(['domain' => $domain, 'allias' => $allias, 'block' => $block], new Context(static fn() => null));

            if ($res->ok) {
                return $this->redirect('/admin/?p=filler&domain=' . urlencode($domain) . '&allias=' . urlencode($allias));
            }
            $error = implode('; ', $res->errors);
        }

        $html = '<div class="admin-card"><h1>Новый filler</h1>';
        if ($error !== '') $html .= '<div class="error">' . View::esc($error) . '</div>';
        $html .= '<form method="post" class="admin-form">' . $this->csrf->field();
        $html .= '<label>Имя блока (например, top)<input type="text" name="block" required></label>';
        $html .= '<button type="submit">Создать</button><a href="/admin/?p=filler&domain=' . urlencode($domain) . '&allias=' . urlencode($allias) . '">Отмена</a></form></div>';

        return Response::html($this->view->render($html, ['title' => 'Новый filler']));
    }

    public function toggle(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) return $auth;

        $domain = (string) $request->query('domain', '');
        $allias = (string) $request->query('allias', '');
        $block = (string) $request->query('block', '');
        $value = (string) $request->query('value', '1');

        if ($domain !== '' && $allias !== '' && $block !== '') {
            $cmd = new FillerToggleCommand($this->paths);
            $cmd->run(['domain' => $domain, 'allias' => $allias, 'block' => $block, 'value' => $value], new Context(static fn() => null));
        }

        return $this->redirect('/admin/?p=filler&domain=' . urlencode($domain) . '&allias=' . urlencode($allias));
    }
}