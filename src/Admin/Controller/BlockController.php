<?php

declare(strict_types=1);

namespace Damoony\Admin\Controller;

use Damoony\Admin\View;
use Damoony\Cli\Commands\BlockNewCommand;
use Damoony\Cli\Commands\BlockRemoveCommand;
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

final class BlockController extends BaseController
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
        if ($auth !== null) {
            return $auth;
        }

        $domain = (string) $request->query('domain', '');
        if ($domain === '') {
            return $this->redirect('/admin/?p=domain');
        }

        $blocksDir = $this->paths->domain($domain) . '/.alliasglobal';
        $blocks = [];
        if (is_dir($blocksDir)) {
            $items = scandir($blocksDir) ?: [];
            foreach ($items as $item) {
                if ($item === '.' || $item === '..' || !is_dir($blocksDir . '/' . $item)) {
                    continue;
                }
                $blocks[] = $item;
            }
        }
        sort($blocks);

        $card = Builder::div('admin-card');

        // Хлебные крошки
        $breadcrumbs = Builder::div('breadcrumbs');
        $breadcrumbs->append(Builder::a('/admin/?p=domain')->content('Домены'));
        $breadcrumbs->append(Text::raw(' / '));
        $breadcrumbs->append(Builder::a('/admin/?p=domain:edit&domain=' . urlencode($domain))->content($domain));
        $card->append($breadcrumbs);

        // Заголовок и кнопка
        $header = Builder::div('page-header');
        $header->append(Builder::h(1)->content('Блоки'));
        $actions = Builder::div('page-actions');
        $actions->append(Builder::a('/admin/?p=block:new&domain=' . urlencode($domain), 'btn btn-primary')->content('+ Создать блок'));
        $header->append($actions);
        $card->append($header);

        // Табы
        $card->append(View::domainTabs($domain, 'blocks'));

        // Таблица
        $table = (new Table())->headers('Имя блока', 'Действия');

        if ($blocks === []) {
            $table->row([Text::raw('Блоков пока нет'), Text::raw('')]);
        } else {
            foreach ($blocks as $block) {
                $editUrl = '/admin/?p=editor&domain=' . urlencode($domain) . '&file=' . urlencode('.alliasglobal/' . $block . '/' . $block . '.php');
                $deleteUrl = '/admin/?p=block:remove&domain=' . urlencode($domain) . '&name=' . urlencode($block);

                $actionsHtml = Builder::div('table-actions');
                $actionsHtml->append(Builder::a($editUrl, 'btn btn-outline')->content('Редактировать'));
                $actionsHtml->append(Builder::a($deleteUrl, 'btn btn-danger')->content('Удалить'));

                $table->row([
                    Text::raw('<b>' . View::esc($block) . '</b>'),
                    $actionsHtml,
                ]);
            }
        }
        $card->append($table->build());

        return Response::html($this->view->render($card, ['title' => 'Блоки: ' . $domain]));
    }

    public function new(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) {
            return $auth;
        }

        $domain = (string) $request->query('domain', '');
        if ($domain === '') {
            return $this->redirect('/admin/?p=domain');
        }

        $error = '';
        $nameVal = '';

        if ($request->isPost()) {
            $csrfErr = $this->requireCsrf($request);
            if ($csrfErr !== null) {
                return $csrfErr;
            }

            $nameVal = (string) ($request->post['name'] ?? '');
            $cmd = new BlockNewCommand($this->paths);
            $res = $cmd->run(['domain' => $domain, 'name' => $nameVal], new Context(static fn () => null));

            if ($res->ok) {
                return $this->redirect('/admin/?p=block&domain=' . urlencode($domain));
            }
            $error = implode('; ', $res->errors);
        }

        $form = Form::create('/admin/?p=block:new&domain=' . urlencode($domain), 'POST')
            ->csrf($this->csrf->field())
            ->add(Field::text('name')->label('Имя блока')->placeholder('top, footer, header')->required()->value($nameVal))
            ->submit('Создать');

        $card = Builder::div('admin-card');

        // Хлебные крошки
        $breadcrumbs = Builder::div('breadcrumbs');
        $breadcrumbs->append(Builder::a('/admin/?p=domain')->content('Домены'));
        $breadcrumbs->append(Text::raw(' / '));
        $breadcrumbs->append(Builder::a('/admin/?p=domain:edit&domain=' . urlencode($domain))->content($domain));
        $card->append($breadcrumbs);

        $card->append(Builder::h(1)->content('Создать блок'));
        $card->append(View::domainTabs($domain, 'blocks'));

        if ($error !== '') {
            $card->append(Message::error($error)->build());
        }

        $card->append($form->build());

        return Response::html($this->view->render($card, ['title' => 'Новый блок']));
    }

    public function remove(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) {
            return $auth;
        }

        $domain = (string) $request->query('domain', '');
        $name = (string) $request->query('name', '');

        if ($domain !== '' && $name !== '') {
            $cmd = new BlockRemoveCommand($this->paths);
            $cmd->run(['domain' => $domain, 'name' => $name], new Context(static fn () => null));
        }

        return $this->redirect('/admin/?p=block&domain=' . urlencode($domain));
    }
}
