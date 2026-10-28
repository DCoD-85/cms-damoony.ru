<?php

declare(strict_types=1);

namespace Damoony\Admin\Controller;

use Damoony\Admin\Support\VisualEditorRenderer;
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

        $table = (new Table())->headers('Схема', View::t('domain.bind_th_act'));

        if ($schemes !== []) {
            foreach ($schemes as $s) {
                if (!is_string($s)) {
                    continue;
                }

                $editUrl = '/admin/?p=scheme:edit&domain=' . urlencode($domain) . '&name=' . urlencode($s);
                $codeFile = '.alliasscheme/' . $s . '.data.php';
                $codeUrl = '/admin/?p=editor&domain=' . urlencode($domain) . '&file=' . urlencode($codeFile);

                $actionsHtml = Builder::div('table-actions');
                $actionsHtml->append(Builder::a($editUrl, 'btn btn-primary')->content('Визуально'));
                $actionsHtml->append(Builder::a($codeUrl, 'btn btn-outline')->content(View::t('page.edit')));

                // Кнопка копирования (через GET с подтверждением)
                $copyUrl = '/admin/?p=scheme:copy&domain=' . urlencode($domain) . '&name=' . urlencode($s);
                $actionsHtml->append(Builder::a($copyUrl, 'btn btn-outline')->content('Копировать'));

                // Кнопка удаления (через GET с JS-подтверждением)
                $delUrl = '/admin/?p=scheme:delete&domain=' . urlencode($domain) . '&name=' . urlencode($s);
                $delLink = Builder::a($delUrl, 'btn btn-danger')->content('Удалить');
                $delLink->attrs()->add('onclick', "return confirm('Вы уверены, что хотите удалить схему?');");
                $actionsHtml->append($delLink);

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
        sort($availableSlots);

        // Собираем текущие слоты из схемы (с их тегами и классами)
        $currentSlots = [];
        $this->collectSlots($data, $currentSlots);

        if ($request->isPost()) {
            $csrfErr = $this->requireCsrf($request);
            if ($csrfErr !== null) {
                return $csrfErr;
            }

            $slotsData = $request->post['slots'] ?? [];
            if (!is_array($slotsData)) {
                $slotsData = [];
            }

            $children = [];
            foreach ($slotsData as $slotItem) {
                if (!isset($slotItem['name']) || !in_array($slotItem['name'], $availableSlots, true)) {
                    continue;
                }

                $tag = (string)($slotItem['tag'] ?? 'div');
                $class = (string)($slotItem['class'] ?? '');

                // Разрешаем только безопасные теги
                if (!in_array($tag, ['div', 'header', 'main', 'footer', 'aside', 'section', 'article'], true)) {
                    $tag = 'div';
                }

                $node = ['tag' => $tag];
                if ($class !== '') {
                    $node['class'] = $class;
                }
                $node['slot'] = $slotItem['name'];
                $children[] = $node;
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

        // Строим форму вручную для гибкости
        $form = '<form method="post" action="/admin/?p=scheme:edit&domain=' . urlencode($domain) . '&name=' . urlencode($name) . '" class="editor-form">';
        $form .= $this->csrf->field();

        $form .= '<div id="slots-container">';

        if ($currentSlots === []) {
            // Если пусто, показываем один слот по умолчанию
            $currentSlots[] = ['slot' => 'center', 'tag' => 'div', 'class' => ''];
        }

        foreach ($currentSlots as $i => $item) {
            $form .= VisualEditorRenderer::renderSlotRow($i, $item, $availableSlots);
        }

        $form .= '</div>';

        $form .= '<div style="margin-top: 15px; display:flex; gap:10px;">';
        $form .= '<button type="button" id="add-slot-btn" class="btn btn-outline">+ Добавить слот</button>';
        $form .= '<button type="submit" class="btn btn-primary">' . View::t('domain.save') . '</button>';
        $form .= '</div>';
        $form .= '</form>';

        $card->append(Text::raw($form));

        return Response::html($this->view->render($card, ['title' => 'Визуальный редактор схем']));
    }

    /**
     * @param array<string, mixed> $node
     * @param list<array{slot: string, tag: string, class: string}> $slots
     */
    private function collectSlots(array $node, array &$slots): void
    {
        if (isset($node['slot']) && is_string($node['slot'])) {
            $slots[] = [
                'slot' => $node['slot'],
                'tag' => (string)($node['tag'] ?? 'div'),
                'class' => (string)($node['class'] ?? ''),
            ];
        }
        if (isset($node['children']) && is_array($node['children'])) {
            foreach ($node['children'] as $child) {
                if (is_array($child)) {
                    $this->collectSlots($child, $slots);
                }
            }
        }
    }

    public function copy(Request $request): Response
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

        $newName = $name . '_copy';
        $i = 1;
        while (is_file($this->paths->domain($domain) . '/.alliasscheme/' . $newName . '.data.php')) {
            $newName = $name . '_copy_' . $i++;
        }

        $srcFile = $this->paths->domain($domain) . '/.alliasscheme/' . $name . '.data.php';
        $dstFile = $this->paths->domain($domain) . '/.alliasscheme/' . $newName . '.data.php';

        if (is_file($srcFile)) {
            copy($srcFile, $dstFile);

            // Обновляем schemes.json
            $manifestFile = $this->paths->domain($domain) . '/.alliasscheme/schemes.json';
            $manifest = is_file($manifestFile) ? json_decode((string)file_get_contents($manifestFile), true) : [];
            if (!is_array($manifest)) {
                $manifest = ['schemes' => []];
            }
            $manifest['schemes'][$newName] = ['enabled' => true];
            Fs::writeAtomic($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
        }

        return $this->redirect('/admin/?p=scheme&domain=' . urlencode($domain));
    }

    public function delete(Request $request): Response
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
        if (is_file($file)) {
            unlink($file);

            // Обновляем schemes.json
            $manifestFile = $this->paths->domain($domain) . '/.alliasscheme/schemes.json';
            $manifest = is_file($manifestFile) ? json_decode((string)file_get_contents($manifestFile), true) : [];
            if (is_array($manifest) && isset($manifest['schemes'][$name])) {
                unset($manifest['schemes'][$name]);
                Fs::writeAtomic($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
            }
        }

        return $this->redirect('/admin/?p=scheme&domain=' . urlencode($domain));
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
