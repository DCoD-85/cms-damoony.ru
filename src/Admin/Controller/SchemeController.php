<?php

declare(strict_types=1);

namespace Damoony\Admin\Controller;

use Damoony\Admin\View;
use Damoony\Cli\Commands\SchemeNewCommand;
use Damoony\Cli\Context;
use Damoony\Files\Fs;
use Damoony\Files\Paths;
use Damoony\Http\Request;
use Damoony\Http\Response;
use Damoony\Scheme\SchemeCompiler;
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
        if ($auth !== null) {
            return $auth;
        }

        $domain = (string) $request->query('domain', '');
        if ($domain === '') {
            return $this->redirect('/admin/?p=domain');
        }

        $manifestFile = $this->paths->domain($domain) . '/.alliasscheme/schemes.json';
        $manifest = is_file($manifestFile) ? json_decode((string) file_get_contents($manifestFile), true) : [];
        $schemes = is_array($manifest) && isset($manifest['schemes']) ? array_keys($manifest['schemes']) : [];

        $card = Builder::div('admin-card');

        $breadcrumbs = Builder::div('breadcrumbs');
        $breadcrumbs->append(Builder::a('/admin/?p=domain')->content(View::t('domain.bread_domains')));
        $breadcrumbs->append(Text::raw(' / '));
        $breadcrumbs->append(Builder::a('/admin/?p=domain:edit&domain=' . urlencode($domain))->content($domain));
        $card->append($breadcrumbs);

        $header = Builder::div('page-header');
        $header->append(Builder::h(1)->content(View::t('tab.schemes')));

        $actions = Builder::div('page-actions');
        $actions->append(Builder::a('/admin/?p=scheme:new&domain=' . urlencode($domain), 'btn btn-primary')->content(View::t('stub.add')));
        $header->append($actions);
        $card->append($header);

        $card->append(View::domainTabs($domain, 'schemes'));

        $table = (new Table())->headers(View::t('filler.th_block'), View::t('domain.bind_th_act'));

        if ($schemes !== []) {
            foreach ($schemes as $s) {
                if (!is_string($s)) {
                    continue;
                }

                // Новая ссылка на визуальный редактор
                $editUrl = '/admin/?p=scheme:edit&domain=' . urlencode($domain) . '&name=' . urlencode($s);

                // Старая ссылка на код
                $codeFile = '.alliasscheme/' . $s . '.data.php';
                $codeUrl = '/admin/?p=editor&domain=' . urlencode($domain) . '&file=' . urlencode($codeFile);

                $actionsHtml = Builder::div('table-actions');
                $actionsHtml->append(Builder::a($editUrl, 'btn btn-primary')->content('Визуально'));
                $actionsHtml->append(Builder::a($codeUrl, 'btn btn-outline')->content(View::t('page.edit')));

                $table->row([
                    Text::raw('<b>' . View::esc($s) . '</b>'),
                    $actionsHtml,
                ]);
            }
        } else {
            $table->row([Text::raw(View::t('stub.empty')), Text::raw('')]);
        }

        $card->append($table->build());
        return Response::html($this->view->render($card, ['title' => View::t('tab.schemes')]));
    }

    public function edit(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) {
            return $auth;
        }

        $domain = (string) $request->query('domain', '');
        $name = (string) $request->query('name', '');

        if ($domain === '' || $name === '') {
            return $this->redirect('/admin/?p=domain');
        }

        $file = $this->paths->domain($domain) . '/.alliasscheme/' . $name . '.data.php';
        if (!is_file($file)) {
            return $this->redirect('/admin/?p=scheme&domain=' . urlencode($domain));
        }

        $data = require $file;
        if (!is_array($data)) {
            $data = [];
        }

        $success = $request->query('saved') ? View::t('msg.saved') : '';

        // Получаем доступные слоты (из глобальных блоков)
        $blocksDir = $this->paths->domain($domain) . '/.alliasglobal';
        $availableSlots = ['center'];
        if (is_dir($blocksDir)) {
            $dirs = scandir($blocksDir) ?: [];
            foreach ($dirs as $d) {
                if ($d !== '.' && $d !== '..' && is_dir($blocksDir . '/' . $d)) {
                    $availableSlots[] = $d;
                }
            }
        }

        // Собираем текущие слоты из схемы
        $currentSlots = [];
        $this->collectSlots($data, $currentSlots);

        if ($request->isPost()) {
            $csrfErr = $this->requireCsrf($request);
            if ($csrfErr !== null) {
                return $csrfErr;
            }

            $selected = $request->post['slots'] ?? ['center'];
            if (!is_array($selected)) {
                $selected = ['center'];
            }

            // Формируем новую структуру схемы (простой вертикальный стек)
            $children = [];
            foreach ($selected as $slotName) {
                $children[] = ['tag' => 'div', 'slot' => $slotName];
            }

            $newData = [
                'tag' => 'div',
                'class' => 'layout',
                'children' => $children,
            ];

            $compiler = new SchemeCompiler();
            Fs::writeAtomic($file, $compiler->compile($newData));

            return $this->redirect('/admin/?p=scheme:edit&domain=' . urlencode($domain) . '&name=' . urlencode($name) . '&saved=1');
        }

        $card = Builder::div('admin-card');

        $breadcrumbs = Builder::div('breadcrumbs');
        $breadcrumbs->append(Builder::a('/admin/?p=domain')->content(View::t('domain.bread_domains')));
        $breadcrumbs->append(Text::raw(' / '));
        $breadcrumbs->append(Builder::a('/admin/?p=scheme&domain=' . urlencode($domain))->content(View::t('tab.schemes')));
        $card->append($breadcrumbs);

        $card->append(Builder::h(1)->content('Схема: ' . View::esc($name)));
        $card->append(Message::info('Выберите слоты, которые должны быть на странице, в нужном порядке.')->build());

        if ($success !== '') {
            $card->append(Message::success($success)->build());
        }

        $form = Form::create('/admin/?p=scheme:edit&domain=' . urlencode($domain) . '&name=' . urlencode($name), 'POST')
            ->csrf($this->csrf->field());

        // Генерируем чекбоксы для слотов
        foreach ($availableSlots as $slot) {
            $checked = in_array($slot, $currentSlots, true);
            $form->add(Field::checkbox('slots[]')->label('Слот: ' . strtoupper($slot))->value($slot)->checked($checked));
        }

        $form->submit(View::t('domain.save'));

        $card->append($form->build());

        return Response::html($this->view->render($card, ['title' => 'Визуальный редактор схем']));
    }

    /**
     * @param array<string, mixed> $node
     * @param list<string> $slots
     */
    private function collectSlots(array $node, array &$slots): void
    {
        if (isset($node['slot']) && is_string($node['slot'])) {
            $slots[] = $node['slot'];
        }
        if (isset($node['children']) && is_array($node['children'])) {
            foreach ($node['children'] as $child) {
                $this->collectSlots($child, $slots);
            }
        }
    }

    public function new(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) {
            return $auth;
        }

        $domain = (string) ($request->query('domain', '') ?: ($request->post['domain'] ?? ''));
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
            $cmd = new SchemeNewCommand($this->paths);
            $res = $cmd->run(['domain' => $domain, 'name' => $nameVal], new Context(static fn () => null));

            if ($res->ok) {
                return $this->redirect('/admin/?p=scheme&domain=' . urlencode($domain));
            }
            $error = implode('; ', $res->errors);
        }

        $card = Builder::div('admin-card');
        $card->append(Builder::h(1)->content('Создать схему'));

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
