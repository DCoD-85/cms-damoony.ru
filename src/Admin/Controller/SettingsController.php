<?php

declare(strict_types=1);

namespace Damoony\Admin\Controller;

use Damoony\Admin\View;
use Damoony\Core\Config;
use Damoony\Files\Fs;
use Damoony\Http\Request;
use Damoony\Http\Response;

final class SettingsController extends BaseController
{
    public function __construct(
        \Damoony\Admin\AuthInterface $auth,
        \Damoony\Security\Csrf $csrf,
        View $view,
        private readonly Config $siteConfig,
        private readonly string $root,
    ) {
        parent::__construct($auth, $csrf, $view);
    }

    public function index(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) return $auth;

        $file = $this->root . '/config/site.config';
        $success = $request->query('saved') ? '<div class="success">Сохранено</div>' : '';

        if ($request->isPost()) {
            $csrfErr = $this->requireCsrf($request);
            if ($csrfErr !== null) return $csrfErr;

            $title = (string) $request->post['title'];
            $lang = (string) $request->post['language'];

            $data = $this->siteConfig->all();
            $data['title'] = $title;
            $data['language'] = $lang;

            Fs::writeAtomic($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
            return $this->redirect('/admin/?p=settings&saved=1');
        }

        $title = (string) $this->siteConfig->get('title', 'Damoony');
        $lang = (string) $this->siteConfig->get('language', 'ru-ru');

        $html = '<div class="admin-card"><h1>Настройки сайта</h1>' . $success;
        $html .= '<form method="post" class="admin-form">' . $this->csrf->field();
        $html .= '<label>Название сайта<input type="text" name="title" value="' . View::esc($title) . '" required></label>';
        $html .= '<label>Язык по умолчанию<select name="language" style="padding:8px; border:1px solid #d2d2d7; border-radius:6px;">';
        $html .= '<option value="ru-ru" ' . ($lang === 'ru-ru' ? 'selected' : '') . '>ru-ru</option>';
        $html .= '<option value="en-en" ' . ($lang === 'en-en' ? 'selected' : '') . '>en-en</option>';
        $html .= '</select></label>';
        $html .= '<button type="submit">Сохранить</button></form></div>';

        return Response::html($this->view->render($html, ['title' => 'Настройки']));
    }
}