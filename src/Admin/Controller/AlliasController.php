<?php

declare(strict_types=1);

namespace Damoony\Admin\Controller;

use Damoony\Admin\View;
use Damoony\Cli\Commands\AlliasNewCommand;
use Damoony\Cli\Context;
use Damoony\Core\Config;
use Damoony\Files\Fs;
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
        if ($auth !== null) {
            return $auth;
        }

        $domain = (string) $request->query('domain', '');
        if ($domain === '') {
            return $this->redirect('/admin/?p=domain');
        }

        $aliases = $this->siteConfig->getByKey(['alliases_real', $domain], []);

        $card = Builder::div('admin-card');

        // Хлебные крошки
        $breadcrumbs = Builder::div('breadcrumbs');
        $breadcrumbs->append(Builder::a('/admin/?p=domain')->content('Домены'));
        $breadcrumbs->append(Text::raw(' / '));
        $breadcrumbs->append(Builder::a('/admin/?p=domain:edit&domain=' . urlencode($domain))->content($domain));
        $card->append($breadcrumbs);

        // Заголовок
        $header = Builder::div('page-header');
        $header->append(Builder::h(1)->content('Алиасы'));

        $actions = Builder::div('page-actions');
        $actions->append(Builder::a('/admin/?p=allias:new&domain=' . urlencode($domain), 'btn btn-primary')->content('+ Создать алиас'));
        $header->append($actions);
        $card->append($header);

        // Табы
        $card->append(View::domainTabs($domain, 'allias'));

        // Таблица
        $table = (new Table())->headers('Статус', 'Алиас', 'Путь', 'Действия');

        if (is_array($aliases) && $aliases !== []) {
            foreach ($aliases as $name => $mapped) {
                if ($name === '') {
                    continue;
                }

                // Проверяем статус (по умолчанию включен)
                $available = (bool) $this->siteConfig->getByKey(['alliases_available', $domain, (string)$name], true);

                $statusUrl = '/admin/?p=allias:toggle&domain=' . urlencode($domain) . '&name=' . urlencode((string)$name);
                $statusHtml = $available
                    ? '<a href="' . $statusUrl . '" class="status-toggle" title="Выключить"></a>'
                    : '<a href="' . $statusUrl . '" class="status-toggle off" title="Включить"></a>';

                $editUrl = '/admin/?p=allias:edit&domain=' . urlencode($domain) . '&allias=' . urlencode((string)$name);
                $aliasLink = Builder::a($editUrl, 'domain-link')->content((string)$name);

                $pathHtml = '<span style="font-family:monospace; font-size:12px; background:#f3f4f6; padding:2px 6px; border-radius:4px;">app/' . View::esc($domain) . '/' . View::esc((string)$name) . '</span>';

                $deleteBtn = Builder::a('/admin/?p=allias:remove&domain=' . urlencode($domain) . '&name=' . urlencode((string)$name), 'btn btn-danger')->content('Удалить');

                $table->row([
                    Text::raw($statusHtml),
                    Text::raw($aliasLink->release()),
                    Text::raw($pathHtml),
                    $deleteBtn,
                ]);
            }
        } else {
            $table->row([Text::raw(''), Text::raw('Алиасов нет'), Text::raw(''), Text::raw('')]);
        }

        $card->append($table->build());
        return Response::html($this->view->render($card, ['title' => 'Алиасы']));
    }

    public function toggle(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) {
            return $auth;
        }

        $domain = (string) $request->query('domain', '');
        $name = (string) $request->query('name', '');

        if ($domain !== '' && $name !== '') {
            $data = $this->siteConfig->all();
            $current = (bool) ($data['alliases_available'][$domain][$name] ?? true);
            $data['alliases_available'][$domain][$name] = !$current;

            Fs::writeAtomic(
                $this->paths->siteConfig(),
                json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n",
            );
        }

        return $this->redirect('/admin/?p=allias&domain=' . urlencode($domain));
    }

    public function new(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) {
            return $auth;
        }

        $domain = (string) $request->query('domain', '');
        if ($domain === '') {
            $domain = (string) ($request->post['domain'] ?? '');
        }
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
            $cmd = new AlliasNewCommand($this->paths);
            $res = $cmd->run(['domain' => $domain, 'name' => $nameVal], new Context(static fn () => null));

            if ($res->ok) {
                return $this->redirect('/admin/?p=allias&domain=' . urlencode($domain));
            }
            $error = implode('; ', $res->errors);
        }

        $card = Builder::div('admin-card');

        $breadcrumbs = Builder::div('breadcrumbs');
        $breadcrumbs->append(Builder::a('/admin/?p=allias&domain=' . urlencode($domain))->content('← Назад к алиасам'));
        $card->append($breadcrumbs);

        $card->append(Builder::h(1)->content('Добавить алиас: ' . View::esc($domain)));
        $card->append(View::domainTabs($domain, 'allias'));

        if ($error !== '') {
            $card->append(Message::error($error)->build());
        }

        $form = Form::create('/admin/?p=allias:new&domain=' . urlencode($domain), 'POST')
            ->csrf($this->csrf->field())
            ->add(Field::text('name')->label('Имя алиаса')->placeholder('admin, api, mobile')->required()->value($nameVal))
            ->submit('Создать');

        $card->append($form->build());

        return Response::html($this->view->render($card, ['title' => 'Новый алиас']));
    }

    public function edit(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) {
            return $auth;
        }

        $domain = (string) $request->query('domain', '');
        $allias = (string) $request->query('allias', '');
        if ($domain === '' || $allias === '') {
            return $this->redirect('/admin/?p=domain');
        }

        $card = Builder::div('admin-card');

        $breadcrumbs = Builder::div('breadcrumbs');
        $breadcrumbs->append(Builder::a('/admin/?p=domain')->content('Домены'));
        $breadcrumbs->append(Text::raw(' / '));
        $breadcrumbs->append(Builder::a('/admin/?p=domain:edit&domain=' . urlencode($domain))->content($domain));
        $breadcrumbs->append(Text::raw(' / '));
        $breadcrumbs->append(Builder::a('/admin/?p=allias&domain=' . urlencode($domain))->content('Алиасы'));
        $card->append($breadcrumbs);

        $card->append(Builder::h(1)->content('Алиас: ' . View::esc($allias)));
        $card->append(View::alliasTabs($domain, $allias, 'settings'));

        $card->append(Message::info('Это заготовка дашборда алиаса. Здесь будут настройки skin, title и БД для конкретного алиаса.')->build());

        $card->append(Builder::a('/admin/?p=page&domain=' . urlencode($domain) . '&allias=' . urlencode($allias), 'btn btn-primary')->content('Перейти к страницам'));

        return Response::html($this->view->render($card, ['title' => 'Алиас: ' . $allias]));
    }
}
