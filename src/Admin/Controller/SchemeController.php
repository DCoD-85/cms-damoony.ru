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

                $copyUrl = '/admin/?p=scheme:copy&domain=' . urlencode($domain) . '&name=' . urlencode($s);
                $copyForm = Form::create($copyUrl, 'POST')
                    ->csrf($this->csrf->field())
                    ->submit('Копировать');
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
                    ->submit('Удалить');
                $delBtn = $delForm->build();
                foreach ($delBtn->children() as $child) {
                    if ($child instanceof Element && $child->tag() === 'button') {
                        $child->attrs()->classAdd('btn btn-danger');
                        $child->attrs()->styleAdd('padding:4px 10px;font-size:12px;');
                    }
                }
                $actionsHtml->append($delBtn);

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
        $allowedTags = ['div', 'header', 'main', 'footer', 'aside', 'section', 'article'];

        // Доступные слоты (из глобальных блоков)
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

        // Корневые поля и плоский список строк
        $rootTag = (string)($data['tag'] ?? 'div');
        $rootClass = (string)($data['class'] ?? 'layout');
        $rootChildren = is_array($data['children'] ?? null) ? $data['children'] : [];

        $currentSlots = [];
        foreach ($rootChildren as $child) {
            if (is_array($child)) {
                $this->flattenTree($child, $currentSlots, null);
            }
        }

        if ($request->isPost()) {
            $csrfErr = $this->requireCsrf($request);
            if ($csrfErr !== null) {
                return $csrfErr;
            }

            $rowsData = $request->post['slots'] ?? [];
            if (!is_array($rowsData)) {
                $rowsData = [];
            }

            $newChildren = $this->rebuildTree($rowsData, $availableSlots, $allowedTags);

            $newRootTag = (string)($request->post['root_tag'] ?? 'div');
            if (!in_array($newRootTag, $allowedTags, true)) {
                $newRootTag = 'div';
            }
            $newRootClass = trim((string)($request->post['root_class'] ?? 'layout'));

            $newData = ['tag' => $newRootTag];
            if ($newRootClass !== '') {
                $newData['class'] = $newRootClass;
            }
            $newData['children'] = $newChildren;

            $compiler = new SchemeCompiler();
            Fs::writeAtomic($file, $compiler->compile($newData));

            return $this->redirect('/admin/?p=scheme:edit&domain=' . urlencode($domain) . '&name=' . urlencode($name) . '&saved=1');
        }

        // Рендер формы
        $card = Builder::div('admin-card');
        $breadcrumbs = Builder::div('breadcrumbs');
        $breadcrumbs->append(Builder::a('/admin/?p=domain')->content(View::t('domain.bread_domains')));
        $breadcrumbs->append(Text::raw(' / '));
        $breadcrumbs->append(Builder::a('/admin/?p=scheme&domain=' . urlencode($domain))->content(View::t('tab.schemes')));
        $card->append($breadcrumbs);

        $card->append(Builder::h(1)->content('Схема: ' . View::esc($name)));
        $card->append(Message::info('Слоты и контейнеры. Вкладывайте строки друг в друга через «в #» для вложенных схем.')->build());

        if ($success !== '') {
            $card->append(Message::success($success)->build());
        }

        if ($currentSlots === []) {
            $currentSlots[] = ['id' => 0, 'parent' => null, 'tag' => 'div', 'class' => '', 'layout' => '', 'slot' => 'center'];
        }

        $containers = [];
        foreach ($currentSlots as $idx => $item) {
            if ($item['slot'] === '') {
                $label = $item['tag'];
                if ($item['class'] !== '') {
                    $label .= '.' . $item['class'];
                }
                $containers[] = ['id' => $item['id'], 'label' => $label];
            }
        }

        $action = '/admin/?p=scheme:edit&domain=' . urlencode($domain) . '&name=' . urlencode($name);
        $form = '<form method="post" action="' . $action . '" class="editor-form">';
        $form .= $this->csrf->field();

        $form .= '<div class="editor-row editor-root-row" style="margin-bottom:10px;padding:10px;background:#f5f5f5;">';
        $form .= '<strong style="margin-right:10px;">Корень:</strong>';
        $form .= '<select name="root_tag" class="tag-select">';
        foreach ($allowedTags as $t) {
            $sel = $rootTag === $t ? ' selected' : '';
            $form .= '<option value="' . htmlspecialchars($t) . '"' . $sel . '>&lt;' . htmlspecialchars($t) . '&gt;</option>';
        }
        $form .= '</select> ';
        $form .= '<input type="text" name="root_class" class="content-input" value="' . htmlspecialchars($rootClass) . '" placeholder="CSS класс" />';
        $form .= '</div>';

        $form .= '<div id="slots-container">';
        foreach ($currentSlots as $i => $item) {
            $form .= VisualEditorRenderer::renderSlotRow($i, $item, $availableSlots, $containers);
        }
        $form .= '</div>';

        $form .= '<div style="margin-top:15px;display:flex;gap:10px;">';
        $form .= '<button type="button" id="add-slot-btn" class="btn btn-outline">+ Добавить строку</button>';
        $form .= '<button type="submit" class="btn btn-primary">' . View::t('domain.save') . '</button>';
        $form .= '</div>';
        $form .= '</form>';

        $card->append(Text::raw($form));

        return Response::html($this->view->render($card, ['title' => 'Визуальный редактор схем']));
    }

    /**
     * @param array<string, mixed> $node
     * @param list<array{id:int, parent:?int, tag:string, class:string, layout:string, slot:string}> $rows
     */
    private function flattenTree(array $node, array &$rows, ?int $parentId = null): int
    {
        $id = count($rows);
        $rows[] = [
            'id' => $id,
            'parent' => $parentId,
            'tag' => (string)($node['tag'] ?? 'div'),
            'class' => (string)($node['class'] ?? ''),
            'layout' => (string)($node['layout'] ?? ''),
            'slot' => (string)($node['slot'] ?? ''),
        ];

        if (!empty($node['children']) && is_array($node['children'])) {
            foreach ($node['children'] as $child) {
                if (is_array($child)) {
                    $this->flattenTree($child, $rows, $id);
                }
            }
        }
        return $id;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param list<string> $availableSlots
     * @param list<string> $allowedTags
     *
     * @return list<array<string, mixed>>
     */
    private function rebuildTree(array $rows, array $availableSlots, array $allowedTags): array
    {
        $nodes = [];
        $parents = [];

        foreach ($rows as $formIdx => $row) {
            if (!is_array($row)) {
                continue;
            }
            $realId = (int)($row['id'] ?? $formIdx);
            $slotName = (string)($row['name'] ?? '');
            $tag = (string)($row['tag'] ?? 'div');
            if (!in_array($tag, $allowedTags, true)) {
                $tag = 'div';
            }
            $class = trim((string)($row['class'] ?? ''));
            $layout = (string)($row['layout'] ?? '');
            $parentRaw = (string)($row['parent'] ?? '');
            $parentId = ($parentRaw === '') ? null : (int)$parentRaw;

            if ($slotName === '') {
                $node = ['tag' => $tag];
                if ($class !== '') {
                    $node['class'] = $class;
                }
                if (in_array($layout, ['vertical', 'horizontal'], true)) {
                    $node['layout'] = $layout;
                }
            } else {
                if (!in_array($slotName, $availableSlots, true)) {
                    continue;
                }
                $node = ['tag' => $tag];
                if ($class !== '') {
                    $node['class'] = $class;
                }
                $node['slot'] = $slotName;
            }

            $nodes[$realId] = $node;
            $parents[$realId] = $parentId;
        }

        $childrenOf = [];
        $roots = [];
        foreach ($nodes as $id => $_) {
            $p = $parents[$id];
            if ($p === null || $p === $id || !isset($nodes[$p]) || isset($nodes[$p]['slot'])) {
                $roots[] = $id;
            } else {
                $childrenOf[$p][] = $id;
            }
        }

        $visited = [];
        $build = function (int $id) use (&$build, &$visited, $nodes, $childrenOf): array {
            $node = $nodes[$id];
            if (isset($visited[$id])) {
                return $node;
            }
            $visited[$id] = true;
            if (!empty($childrenOf[$id])) {
                $node['children'] = [];
                foreach ($childrenOf[$id] as $childId) {
                    $node['children'][] = $build($childId);
                }
            }
            unset($visited[$id]);
            return $node;
        };

        $result = [];
        foreach ($roots as $r) {
            $result[] = $build($r);
        }
        return $result;
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
