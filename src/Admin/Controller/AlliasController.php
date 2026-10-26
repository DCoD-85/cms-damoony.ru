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
use Damoony\Ui\Builder;
use Damoony\Ui\Field;
use Damoony\Ui\Form;
use Damoony\Ui\Message;
use Damoony\Ui\Table;
use Damoony\Ui\Text;

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

        $domain = (string) $request->query('domain', '');
        if ($domain === '') return $this->redirect('/admin/?p=domain');

        $aliases = $this->siteConfig->getByKey(['alliases_real', $domain], []);
        
        // Заголовок
        $header = Builder::div('page-header');
        $header->append(Builder::h(1)->content('Алиасы: ' . View::esc($domain)));
        
        $actions = Builder::div('page-actions');
        $actions->append(Builder::a('/admin/?p=domain', 'btn btn-outline')->content('&larr; Назад'));
        $actions->append(Builder::a('/admin/?p=allias:new&domain=' . urlencode($domain), 'btn btn-primary')->content('+ Добавить алиас'));
        $header->append($actions);

        // Таблица
        $table = (new Table())->headers('Алиас', 'Путь', 'Действия');

        if (is_array($aliases) && $aliases !== []) {
            foreach ($aliases as $name => $mapped) {
                if ($name === '') continue;
                
                $pathHtml = '<span style="font-family:monospace; font-size:12px; background:#f3f4f6; padding:2px 6px; border-radius:4px;">app/' . View::esc($domain) . '/' . View::esc((string)$name) . '</span>';
                
                $deleteBtn = Builder::a('/admin/?p=allias:remove&domain=' . urlencode($domain) . '&name=' . urlencode((string)$name), 'btn btn-danger')->content('Удалить');
                $fillerBtn = Builder::a('/admin/?p=filler&domain=' . urlencode($domain) . '&allias=' . urlencode((string)$name), 'btn btn-outline')->content('Fillers');
                $pageBtn = Builder::a('/admin/?p=page&domain=' . urlencode($domain) . '&allias=' . urlencode((string)$name), 'btn btn-outline')->content('Страницы');

                $table->row([
                    Text::raw('<b>' . View::esc((string)$name) . '</b>'),
                    Text::raw($pathHtml),
                    Text::raw($pageBtn->release() . ' ' . $fillerBtn->release() . ' ' . $deleteBtn->release())
                ]);
            }
        } else {
            $table->row([Text::raw('Алиасов нет'), Text::raw(''), Text::raw('')]);
        }

        $card = Builder::div('admin-card');
        $card->append($table->build());

        $content = Builder::div();
        $content->append($header);
        $content->append($card);

        return Response::html($this->view->render($content, ['title' => 'Алиасы']));
    }

    public function new(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) return $auth;

        $domain = (string) $request->query('domain', '') ?? (string)($request->post['domain'] ?? '');
        if ($domain === '') return $this->redirect('/admin/?p=domain');

        $error = '';
        $nameVal = '';

        if ($request->isPost()) {
            $csrfErr = $this->requireCsrf($request);
            if ($csrfErr !== null) return $csrfErr;

            $nameVal = (string) ($request->post['name'] ?? '');

            $cmd = new AlliasNewCommand($this->paths);
            $res = $cmd->run(['domain' => $domain, 'name' => $nameVal], new Context(static fn() => null));

            if ($res->ok) {
                return $this->redirect('/admin/?p=allias&domain=' . urlencode($domain));
            }
            $error = implode('; ', $res->errors);
        }

        $form = Form::create('/admin/?p=allias:new&domain=' . urlencode($domain), 'POST')
            ->csrf($this->csrf->field())
            ->add(Field::text('name')->label('Имя алиаса')->placeholder('admin, api, mobile')->required()->value($nameVal))
            ->submit('Создать');

        $card = Builder::div('admin-card');
        $card->append(Builder::h(1)->content('Добавить алиас: ' . View::esc($domain)));
        if ($error !== '') {
            $card->append(Message::error($error)->build());
        }
        $card->append($form->build());
        
        $backBtn = Builder::a('/admin/?p=allias&domain=' . urlencode($domain), 'btn btn-outline')->content('Назад');
        $backBtn->attrs()->styleAdd('margin-top:20px;');
        $card->append($backBtn);

        return Response::html($this->view->render($card, ['title' => 'Новый алиас']));
    }
}