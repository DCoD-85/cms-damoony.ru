<?php

declare(strict_types=1);

namespace Damoony\Admin\Controller;

use Damoony\Admin\View;
use Damoony\Cli\Commands\SchemeNewCommand;
use Damoony\Cli\Context;
use Damoony\Files\Paths;
use Damoony\Http\Request;
use Damoony\Http\Response;
use Damoony\Ui\Builder;
use Damoony\Ui\Field;
use Damoony\Ui\Form;
use Damoony\Ui\Message;
use Damoony\Ui\Table;
use Damoony\Ui\Text;

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

        $manifestFile = $this->paths->domain($domain) . '/.alliasscheme/schemes.json';
        $manifest = is_file($manifestFile) ? json_decode((string) file_get_contents($manifestFile), true) : [];
        $schemes = is_array($manifest) && isset($manifest['schemes']) ? array_keys($manifest['schemes']) : [];

        $card = Builder::div('admin-card');

        // Хлебные крошки
        $breadcrumbs = Builder::div('breadcrumbs');
        $breadcrumbs->append(Builder::a('/admin/?p=domain')->content('Домены'));
        $breadcrumbs->append(Text::raw(' / '));
        $breadcrumbs->append(Builder::a('/admin/?p=domain:edit&domain=' . urlencode($domain))->content($domain));
        $card->append($breadcrumbs);

        // Заголовок и кнопка
        $header = Builder::div('page-header');
        $header->append(Builder::h(1)->content('Схемы'));
        
        $actions = Builder::div('page-actions');
        $actions->append(Builder::a('/admin/?p=scheme:new&domain=' . urlencode($domain), 'btn btn-primary')->content('+ Создать схему'));
        $header->append($actions);
        $card->append($header);

        // Табы
        $card->append(View::domainTabs($domain, 'schemes'));

        // Таблица
        $table = (new Table())->headers('Имя схемы', 'Действия');

        if ($schemes !== []) {
            foreach ($schemes as $s) {
                if (!is_string($s)) continue;
                
                $editFile = '.alliasscheme/' . $s . '.data.php';
                $editUrl = '/admin/?p=editor&domain=' . urlencode($domain) . '&file=' . urlencode($editFile);

                $actionsHtml = Builder::div('table-actions');
                $actionsHtml->append(Builder::a($editUrl, 'btn btn-outline')->content('Редактировать'));

                $table->row([
                    Text::raw('<b>' . View::esc($s) . '</b>'),
                    $actionsHtml
                ]);
            }
        } else {
            $table->row([Text::raw('Схем пока нет'), Text::raw('')]);
        }

        $card->append($table->build());

        return Response::html($this->view->render($card, ['title' => 'Схемы: ' . $domain]));
    }

    public function new(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) return $auth;

        $domain = (string) ($request->query('domain', '') ?: ($request->post['domain'] ?? ''));
        if ($domain === '') return $this->redirect('/admin/?p=domain');

        $error = '';
        $nameVal = '';

        if ($request->isPost()) {
            $csrfErr = $this->requireCsrf($request);
            if ($csrfErr !== null) return $csrfErr;

            $nameVal = (string) ($request->post['name'] ?? '');
            $cmd = new SchemeNewCommand($this->paths);
            $res = $cmd->run(['domain' => $domain, 'name' => $nameVal], new Context(static fn() => null));

            if ($res->ok) {
                return $this->redirect('/admin/?p=scheme&domain=' . urlencode($domain));
            }
            $error = implode('; ', $res->errors);
        }

        $card = Builder::div('admin-card');

        // Хлебные крошки
        $breadcrumbs = Builder::div('breadcrumbs');
        $breadcrumbs->append(Builder::a('/admin/?p=domain')->content('Домены'));
        $breadcrumbs->append(Text::raw(' / '));
        $breadcrumbs->append(Builder::a('/admin/?p=domain:edit&domain=' . urlencode($domain))->content($domain));
        $card->append($breadcrumbs);

        $card->append(Builder::h(1)->content('Создать схему'));
        $card->append(View::domainTabs($domain, 'schemes'));

        if ($error !== '') {
            $card->append(Message::error($error)->build());
        }

        $form = Form::create('/admin/?p=scheme:new&domain=' . urlencode($domain), 'POST')
            ->csrf($this->csrf->field())
            ->add(Field::text('name')->label('Имя схемы')->placeholder('visuo, default')->required()->value($nameVal))
            ->submit('Создать');

        $card->append($form->build());

        return Response::html($this->view->render($card, ['title' => 'Новая схема']));
    }
}