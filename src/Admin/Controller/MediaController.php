<?php

declare(strict_types=1);

namespace Damoony\Admin\Controller;

use Damoony\Admin\View;
use Damoony\Http\Request;
use Damoony\Http\Response;

final class MediaController extends BaseController
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
        $dir = $this->root . '/www/media/' . ($domain !== '' ? $domain : 'shared');
        
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $files = [];
        if (is_dir($dir)) {
            $items = scandir($dir) ?: [];
            foreach ($items as $item) {
                if ($item === '.' || $item === '..' || is_dir($dir . '/' . $item)) continue;
                $files[] = [
                    'name' => $item,
                    'size' => filesize($dir . '/' . $item) ?: 0,
                    'url' => '/media/' . ($domain !== '' ? $domain . '/' : 'shared/') . $item
                ];
            }
        }

        $html = '<div class="admin-card"><h1>Медиа: ' . View::esc($domain ?: 'Общие') . '</h1>';
        $html .= '<p><a href="/admin/?p=domain">&larr; Назад</a></p>';
        
        $html .= '<form method="post" enctype="multipart/form-data" action="/admin/?p=media:upload&domain=' . urlencode($domain) . '" style="margin-bottom:24px; display:flex; gap:12px; align-items:center;">';
        $html .= $this->csrf->field();
        $html .= '<input type="file" name="file" required>';
        $html .= '<button type="submit" class="btn" style="padding:8px 16px; background:#0071e3; color:#fff; border:none; border-radius:6px; cursor:pointer;">Загрузить</button>';
        $html .= '</form>';

        $html .= '<table style="width:100%; border-collapse:collapse;">';
        $html .= '<tr><th style="text-align:left; padding:8px; border-bottom:1px solid #ddd;">Файл</th><th style="text-align:left; padding:8px; border-bottom:1px solid #ddd;">Размер</th><th style="text-align:left; padding:8px; border-bottom:1px solid #ddd;">Действия</th></tr>';

        if ($files === []) {
            $html .= '<tr><td colspan="3" style="padding:8px;">Файлов нет</td></tr>';
        }

        foreach ($files as $f) {
            $html .= '<tr><td style="padding:8px; border-bottom:1px solid #eee;"><a href="' . View::esc($f['url']) . '" target="_blank">' . View::esc($f['name']) . '</a></td>';
            $html .= '<td style="padding:8px; border-bottom:1px solid #eee;">' . round($f['size'] / 1024, 1) . ' KB</td>';
            $html .= '<td style="padding:8px; border-bottom:1px solid #eee;"><a href="/admin/?p=media:delete&domain=' . urlencode($domain) . '&file=' . urlencode($f['name']) . '" style="color:red;" onclick="return confirm(\'Удалить?\')">Удалить</a></td></tr>';
        }
        $html .= '</table></div>';

        return Response::html($this->view->render($html, ['title' => 'Медиа']));
    }

    public function upload(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) return $auth;

        $csrfErr = $this->requireCsrf($request);
        if ($csrfErr !== null) return $csrfErr;

        $domain = (string) $request->query('domain', '');
        $dir = $this->root . '/www/media/' . ($domain !== '' ? $domain : 'shared');
        
        if (!is_dir($dir)) @mkdir($dir, 0775, true);

        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $name = basename($_FILES['file']['name']);
            $name = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $name);
            $dest = $dir . '/' . $name;
            move_uploaded_file($_FILES['file']['tmp_name'], $dest);
        }

        return $this->redirect('/admin/?p=media&domain=' . urlencode($domain));
    }

    public function delete(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) return $auth;

        $domain = (string) $request->query('domain', '');
        $file = (string) $request->query('file', '');
        
        if ($file !== '') {
            $path = $this->root . '/www/media/' . ($domain !== '' ? $domain : 'shared') . '/' . basename($file);
            if (is_file($path)) @unlink($path);
        }

        return $this->redirect('/admin/?p=media&domain=' . urlencode($domain));
    }
}