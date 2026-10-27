<?php

declare(strict_types=1);

namespace Damoony\Admin\Controller;

use Damoony\Admin\View;
use Damoony\Http\Request;
use Damoony\Http\Response;
use Damoony\Ui\Builder;
use Damoony\Ui\Field;
use Damoony\Ui\Form;
use Damoony\Ui\Message;
use Damoony\Ui\Table;
use Damoony\Ui\Text;

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
        if ($auth !== null) {
            return $auth;
        }

        $domain = (string) $request->query('domain', '');
        $dirName = $domain !== '' ? $domain : 'shared';
        $dir = $this->root . '/www/media/' . $dirName;

        if (!is_dir($dir)) {
            @mkdir($dir, 0o775, true);
        }

        $files = [];
        if (is_dir($dir)) {
            $items = scandir($dir) ?: [];
            foreach ($items as $item) {
                if ($item === '.' || $item === '..' || is_dir($dir . '/' . $item)) {
                    continue;
                }
                $files[] = [
                    'name' => $item,
                    'size' => filesize($dir . '/' . $item) ?: 0,
                    'url' => '/media/' . $dirName . '/' . $item,
                ];
            }
        }

        $card = Builder::div('admin-card');

        // Хлебные крошки
        $breadcrumbs = Builder::div('breadcrumbs');
        $breadcrumbs->append(Builder::a('/admin/?p=domain')->content('Домены'));
        $breadcrumbs->append(Text::raw(' / '));
        $breadcrumbs->append(Builder::a('/admin/?p=domain:edit&domain=' . urlencode($domain))->content($domain));
        $card->append($breadcrumbs);

        $header = Builder::div('page-header');
        $header->append(Builder::h(1)->content('Медиа: ' . View::esc($dirName)));
        $card->append($header);

        if ($domain !== '') {
            $card->append(View::domainTabs($domain, 'media'));
        }

        if ($request->query('saved')) {
            $card->append(Message::success('Файл загружен')->build());
        }
        if ($request->query('deleted')) {
            $card->append(Message::success('Файл удален')->build());
        }

        // Форма загрузки
        $form = Form::create('/admin/?p=media:upload&domain=' . urlencode($domain), 'POST')
            ->enctype('multipart/form-data')
            ->csrf($this->csrf->field())
            ->add(Field::file('file')->label('Загрузить файл')->required())
            ->submit('Загрузить');

        $card->append($form->build());

        // ИСПРАВЛЕНИЕ ОШИБКИ append(Attrs): разделяем создание hr и добавление стилей
        $hr = Builder::el('hr');
        $hr->attrs()->styleAdd('margin: 30px 0; border: 0; border-top: 1px solid #eee;');
        $card->append($hr);

        // Таблица файлов
        $table = (new Table())->headers('Файл', 'Размер', 'Действия');

        if ($files === []) {
            $table->row([Text::raw('Файлов пока нет'), Text::raw(''), Text::raw('')]);
        } else {
            foreach ($files as $f) {
                $sizeKb = round($f['size'] / 1024, 1) . ' KB';

                $actionsHtml = Builder::div('table-actions');
                $openBtn = Builder::a($f['url'], 'btn btn-outline')->content('Открыть');
                $openBtn->attrs()->add('target', '_blank');
                $actionsHtml->append($openBtn);

                // ИСПРАВЛЕНИЕ CSRF: Удаление через POST-форму, а не GET-ссылку
                $delForm = Form::create('/admin/?p=media:delete&domain=' . urlencode($domain) . '&file=' . urlencode($f['name']), 'POST')
                    ->csrf($this->csrf->field())
                    ->submit('Удалить');
                $delFormNode = $delForm->build();
                $delFormNode->attrs()->styleAdd('display:inline-block; margin:0; border:none;');

                $actionsHtml->append($delFormNode);

                $table->row([
                    Text::raw('<a href="' . View::esc($f['url']) . '" target="_blank">' . View::esc($f['name']) . '</a>'),
                    Text::raw($sizeKb),
                    $actionsHtml,
                ]);
            }
        }

        $card->append($table->build());

        return Response::html($this->view->render($card, ['title' => 'Медиа']));
    }

    public function upload(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) {
            return $auth;
        }

        $csrfErr = $this->requireCsrf($request);
        if ($csrfErr !== null) {
            return $csrfErr;
        }

        $domain = (string) $request->query('domain', '');
        $dirName = $domain !== '' ? $domain : 'shared';
        $dir = $this->root . '/www/media/' . $dirName;

        if (!is_dir($dir)) {
            @mkdir($dir, 0o775, true);
        }

        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $rawName = $_FILES['file']['name'] ?? '';

            // ИСПРАВЛЕНИЕ pathinfo: проверяем, что имя это строка
            if (is_string($rawName)) {
                $name = basename($rawName);
                $name = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $name) ?: 'file';
                $dest = $dir . '/' . $name;

                // Защита от перезаписи
                $i = 1;
                while (is_file($dest)) {
                    $pathInfo = pathinfo($name);
                    $filename = $pathInfo['filename'];
                    $ext = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
                    $dest = $dir . '/' . $filename . '_' . $i . $ext;
                    $i++;
                }

                move_uploaded_file($_FILES['file']['tmp_name'], $dest);
            }
        }

        return $this->redirect('/admin/?p=media&domain=' . urlencode($domain) . '&saved=1');
    }

    public function delete(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) {
            return $auth;
        }

        // ИСПРАВЛЕНИЕ БЕЗОПАСНОСТИ: требуем POST
        $postErr = $this->requirePost($request);
        if ($postErr !== null) {
            return $postErr;
        }

        $csrfErr = $this->requireCsrf($request);
        if ($csrfErr !== null) {
            return $csrfErr;
        }

        $domain = (string) $request->query('domain', '');
        $file = (string) $request->query('file', '');

        if ($file !== '') {
            $dirName = $domain !== '' ? $domain : 'shared';
            $path = $this->root . '/www/media/' . $dirName . '/' . basename($file);
            if (is_file($path)) {
                @unlink($path);
            }
        }

        return $this->redirect('/admin/?p=media&domain=' . urlencode($domain) . '&deleted=1');
    }
}
