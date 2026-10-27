<?php

declare(strict_types=1);

namespace Damoony\Admin\Controller;

use Damoony\Admin\View;
use Damoony\Files\Fs;
use Damoony\Http\Request;
use Damoony\Http\Response;
use Damoony\Ui\Builder;
use Damoony\Ui\Message;

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

        if ($domain === '' || $file === '' || str_contains($file, '..') || str_contains($domain, '..')) {
            return $this->redirect('/admin/?p=domain');
        }

        $path = $this->root . '/app/' . $domain . '/' . ltrim($file, '/');
        
        if (!is_file($path)) {
            return Response::notFound('Файл не найден: ' . $file);
        }

        $error = '';
        $success = $request->query('saved') ? 'Файл сохранён' : '';

        if ($request->isPost()) {
            $csrfErr = $this->requireCsrf($request);
            if ($csrfErr !== null) return $csrfErr;

            $content = (string) ($request->post['content'] ?? '');
            try {
                Fs::writeAtomic($path, $content);
                return $this->redirect('/admin/?p=editor&domain=' . urlencode($domain) . '&file=' . urlencode($file) . '&saved=1');
            } catch (\Throwable $e) {
                $error = 'Ошибка сохранения: ' . $e->getMessage();
            }
        }

        $content = (string) file_get_contents($path);
        $backUrl = '/admin/?p=domain';

        // Определяем, куда вернуться
        if (str_starts_with($file, '.alliasglobal/')) {
            $backUrl = '/admin/?p=block&domain=' . urlencode($domain);
        } elseif (str_starts_with($file, '.alliasscheme/')) {
            $backUrl = '/admin/?p=scheme&domain=' . urlencode($domain);
        } elseif (str_contains($file, '/algoritm/')) {
            $backUrl = '/admin/?p=page&domain=' . urlencode($domain) . '&allias=www';
        }

        $card = Builder::div('admin-card');
        $card->append(Builder::a($backUrl)->content('← Назад'));
        $card->append(Builder::h(1)->content('Редактор: ' . View::esc($file)));
        
        if ($success !== '') {
            $card->append(Message::success($success)->build());
        }
        if ($error !== '') {
            $card->append(Message::error($error)->build());
        }

        $form = '<form method="post" class="editor-form">';
        $form .= $this->csrf->field();
        $form .= '<textarea name="content" class="code-editor" spellcheck="false">' . htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</textarea>';
        $form .= '<div class="editor-actions"><button type="submit" class="btn btn-primary">Сохранить</button></div>';
        $form .= '</form>';

        $card->append(\Damoony\Ui\Text::raw($form));

        return Response::html($this->view->render($card, ['title' => 'Редактор']));
    }
}