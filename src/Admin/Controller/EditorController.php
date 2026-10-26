<?php

declare(strict_types=1);

namespace Damoony\Admin\Controller;

use Damoony\Admin\View;
use Damoony\Files\Fs;
use Damoony\Http\Request;
use Damoony\Http\Response;

final class EditorController extends BaseController
{
    public function __construct(
        \Damoony\Admin\AuthInterface $auth,
        \Damoony\Security\Csrf $csrf,
        View $view,
        private readonly string $root,
    ) {
        parent::__construct($auth, $csrf, $view);
    }

    public function index(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) return $auth;

        $domain = (string) $request->query('domain', '');
        $file = (string) $request->query('file', '');

        if ($domain === '' || $file === '') {
            return $this->redirect('/admin/?p=domain');
        }

        // Защита от Path Traversal
        if (str_contains($file, '..') || str_contains($domain, '..')) {
            return $this->redirect('/admin/?p=domain');
        }

        $path = $this->root . '/app/alpha.0.0/domain/' . $domain . '/' . ltrim($file, '/');
        
        if (!is_file($path)) {
            return Response::notFound('File not found');
        }

        $error = '';

        if ($request->isPost()) {
            $csrfErr = $this->requireCsrf($request);
            if ($csrfErr !== null) return $csrfErr;

            $content = (string) $request->post['content'];
            Fs::writeAtomic($path, $content);
            
            return $this->redirect('/admin/?p=editor&domain=' . urlencode($domain) . '&file=' . urlencode($file) . '&saved=1');
        }

        $content = (string) file_get_contents($path);
        $saved = $request->query('saved') ? '<div class="success">Файл сохранен</div>' : '';

        $html = '<div class="admin-card"><h1>Редактор: ' . View::esc($file) . '</h1>' . $saved;
        $html .= '<p><a href="/admin/?p=domain">&larr; Назад к доменам</a></p>';
        $html .= '<form method="post" class="admin-form" style="max-width:100%;">';
        $html .= $this->csrf->field();
        $html .= '<textarea name="content" style="width:100%; height:500px; font-family:monospace; font-size:14px; padding:12px; border:1px solid #ccc; border-radius:6px; background:#1e1e1e; color:#d4d4d4; white-space:pre;">' . View::esc($content) . '</textarea>';
        $html .= '<button type="submit" style="margin-top:12px;">Сохранить</button>';
        $html .= '</form></div>';

        return Response::html($this->view->render($html, ['title' => 'Редактор']));
    }
}