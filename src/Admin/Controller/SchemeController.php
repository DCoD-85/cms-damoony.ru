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
use Damoony\Ui\Element;
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

        $table = (new Table())->headers(View::t('admin.scheme.col_name'), View::t('domain.bind_th_act'));

        if ($schemes !== []) {
            foreach ($schemes as $s) {
                if (!is_string($s)) {
                    continue;
                }

                $editUrl = '/admin/?p=scheme:visual&domain=' . urlencode($domain) . '&name=' . urlencode($s);
                $codeFile = '.alliasscheme/' . $s . '.data.php';
                $codeUrl = '/admin/?p=editor&domain=' . urlencode($domain) . '&file=' . urlencode($codeFile);

                $actionsHtml = Builder::div('table-actions');
                $actionsHtml->append(Builder::a($editUrl, 'btn btn-primary')->content(View::t('admin.page.visual_editor')));
                $actionsHtml->append(Builder::a($codeUrl, 'btn btn-outline')->content(View::t('page.edit')));

                $copyUrl = '/admin/?p=scheme:copy&domain=' . urlencode($domain) . '&name=' . urlencode($s);
                $copyForm = Form::create($copyUrl, 'POST')
                    ->csrf($this->csrf->field())
                    ->submit(View::t('admin.scheme.btn_copy'));
                $copyBtn = $copyForm->build();
                foreach ($copyBtn->children() as $child) {
                    if ($child instanceof Element && $child->tag() === 'button') {
                        $child->attrs()->classAdd('btn btn-outline');
                        $child->attrs()->styleAdd('padding:4px 10px;font-size:12px;');
                    }
                }
                $actionsHtml->append($copyBtn);

                $delUrl = '/admin/?p=scheme:delete&domain=' . urlencode($domain) . '&name=' . urlencode($s);
                $delForm = Form::create($delUrl, 'POST')
                    ->csrf($this->csrf->field())
                    ->submit(View::t('admin.scheme.btn_delete'));
                $delBtn = $delForm->build();
                foreach ($delBtn->children() as $child) {
                    if ($child instanceof Element && $child->tag() === 'button') {
                        $child->attrs()->classAdd('btn btn-danger');
                        $child->attrs()->styleAdd('padding:4px 10px;font-size:12px;');
                    }
                }
                $actionsHtml->append($delBtn);

                $table->row([
                    Builder::el('b')->content($s),
                    $actionsHtml,
                ]);
            }
        } else {
            $table->row([Text::raw(View::t('stub.empty')), Text::raw('')]);
        }

        $card->append($table->build());
        return Response::html($this->view->render($card, ['title' => View::t('tab.schemes')]));
    }
    public function copy(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) {
            return $auth;
        }
        $postErr = $this->requirePost($request);
        if ($postErr !== null) {
            return $postErr;
        }
        $csrfErr = $this->requireCsrf($request);
        if ($csrfErr !== null) {
            return $csrfErr;
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
        $postErr = $this->requirePost($request);
        if ($postErr !== null) {
            return $postErr;
        }
        $csrfErr = $this->requireCsrf($request);
        if ($csrfErr !== null) {
            return $csrfErr;
        }

        $domain = (string) $request->query('domain', '');
        $name = (string) $request->query('name', '');

        if ($domain === '' || $name === '') {
            return $this->redirect('/admin/?p=domain');
        }

        $file = $this->paths->domain($domain) . '/.alliasscheme/' . $name . '.data.php';
        if (is_file($file)) {
            unlink($file);
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

    public function visual(Request $request): Response
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

        $schemesDir = $this->paths->domain($domain) . '/.alliasscheme';
        $editorFile = $schemesDir . '/' . $name . '.editor.json';
        $dataFile = $schemesDir . '/' . $name . '.data.php';

        if (!is_file($editorFile)) {
            return $this->redirect('/admin/?p=scheme&domain=' . urlencode($domain));
        }

        try {
            $editorData = json_decode((string)file_get_contents($editorFile), true);
            if (!is_array($editorData) || !isset($editorData['scheme'])) {
                throw new \RuntimeException('Файл правил схемы поврежден.');
            }
        } catch (\Throwable $e) {
            $card = Builder::div('admin-card');
            $card->append(Builder::h(1)->content('Ошибка схемы'));
            $card->append(Message::error($e->getMessage())->build());
            return Response::html($this->view->render($card, ['title' => 'Ошибка схемы']));
        }

        $scheme = $editorData['scheme'];
        $fields = $editorData['fields'] ?? [];

        // Проверка рассинхрона (диск vs rules)
        $warnings = [];
        $diskBlocks = [];
        $blocksDir = $this->paths->domain($domain) . '/.alliasglobal';
        if (is_dir($blocksDir)) {
            $dirs = array_filter(scandir($blocksDir) ?: [], fn ($d) => $d !== '.' && $d !== '..' && is_dir($blocksDir . '/' . $d));
            foreach ($dirs as $bName) {
                $diskBlocks[] = $bName;
            }
        }

        $ruleBlocks = array_column($fields, 'name');

        $newOnDisk = array_diff($diskBlocks, $ruleBlocks);
        if (!empty($newOnDisk)) {
            $warnings[] = 'На диске найдены новые блоки: ' . implode(', ', $newOnDisk) . ". Нажмите 'Обновить правила' в списке схем.";
        }

        $deletedFromDisk = array_diff($ruleBlocks, $diskBlocks);
        if (!empty($deletedFromDisk)) {
            $warnings[] = 'Блоки удалены с диска: ' . implode(', ', $deletedFromDisk) . '. Схема может работать некорректно.';
        }

        $card = Builder::div('admin-card scheme-builder-app');
        $link = Builder::el('link', true);
        $link->attrs()->add('rel', 'stylesheet');
        $link->attrs()->add('href', '/media/admin/scheme-builder.css');
        $card->append($link);
        $script = Builder::el('script');
        $script->attrs()->add('src', '/media/admin/scheme-builder.js');
        $script->attrs()->add('defer', 'true');
        $card->append($script);

        foreach ($warnings as $w) {
            $card->append(Message::warning($w)->build());
        }

        $grid = Builder::div('sb-layout');

        $colLeft = Builder::div('sb-col sb-col-left');
        $zoneAvailable = Builder::div('sb-zone sb-zone-available');
        $zoneAvailable->attrs()->add('id', 'sb-zone-available');
        $zoneAvailable->append(Builder::h(4)->content('1. Неактивные (все)'));

        $zoneData = Builder::div('sb-zone sb-zone-data');
        $zoneData->attrs()->add('id', 'sb-zone-data');
        $zoneData->append(Builder::h(4)->content('2. Скрипты (Data)'));

        $canvas = Builder::div('sb-canvas');
        $canvas->attrs()->add('id', 'sb-canvas');
        $canvas->append(Builder::h(4)->content('3. Визуал + Логика'));

        $colLeft->append($zoneAvailable)->append($zoneData)->append($canvas);

        $colRight = Builder::div('sb-col sb-col-right');
        $colRight->append(Builder::h(3)->content('Свойства'));
        $props = Builder::div('sb-properties');
        $props->attrs()->add('id', 'sb-properties');
        $colRight->append($props);

        $colFile = Builder::div('sb-col sb-col-file');
        $colFile->append(Builder::h(3)->content('Файл блока'));
        $fileView = Builder::div('sb-file-view');
        $fileView->attrs()->add('id', 'sb-file-view');
        $fileView->content('Выберите блок...');
        $colFile->append($fileView);

        $grid->append($colLeft)->append($colRight)->append($colFile);
        $card->append($grid);

        $form = Form::create('/admin/?p=scheme:saveVisual&domain=' . $domain . '&name=' . $name, 'POST');
        $form->csrf($this->csrf->field());
        $form->add(Field::hidden('scheme_json'));
        $form->submit('Сохранить');
        $card->append($form->build());

        $initData = json_encode([
            'scheme' => $scheme,
            'fields' => $fields,
        ], JSON_UNESCAPED_UNICODE);

        $scriptData = Builder::el('script');
        $scriptData->attrs()->add('type', 'application/json');
        $scriptData->attrs()->add('id', 'sb-init-data');
        $scriptData->append(Text::raw((string)$initData));
        $card->append($scriptData);

        return Response::html($this->view->render($card, ['title' => 'Визуальный редактор схем']));
    }

    public function saveVisual(Request $request): Response
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
        $name = (string) $request->query('name', '');

        $json = (string) ($request->post['scheme_json'] ?? '');
        $scheme = json_decode($json, true);
        if (!is_array($scheme)) {
            return Response::json(['error' => 'Invalid JSON']);
        }

        $schemesDir = $this->paths->domain($domain) . '/.alliasscheme';
        $editorFile = $schemesDir . '/' . $name . '.editor.json';
        $dataFile = $schemesDir . '/' . $name . '.data.php';

        // 1. Обновляем editor.json
        $editorData = json_decode((string)file_get_contents($editorFile), true);
        $editorData['scheme'] = $scheme;
        Fs::writeAtomic($editorFile, json_encode($editorData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");

        // 2. Компилируем data.php
        $phpData = [
            'tag' => 'div',
            'class' => 'layout',
            'children' => $scheme['children'] ?? [],
            'scripts' => $scheme['scripts'] ?? [],
        ];

        $compiler = new SchemeCompiler();
        Fs::writeAtomic($dataFile, $compiler->compile($phpData));

        return $this->redirect('/admin/?p=scheme:visual&domain=' . urlencode($domain) . '&name=' . urlencode($name) . '&saved=1');
    }
}
