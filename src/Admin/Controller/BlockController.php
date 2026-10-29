<?php

declare(strict_types=1);

namespace Damoony\Admin\Controller;

use Damoony\Admin\Support\VisualEditorRenderer;
use Damoony\Admin\View;
use Damoony\Cli\Commands\BlockNewCommand;
use Damoony\Cli\Commands\BlockRemoveCommand;
use Damoony\Cli\Context;
use Damoony\Core\Config;
use Damoony\Files\Fs;
use Damoony\Files\Paths;
use Damoony\Http\Request;
use Damoony\Http\Response;
use Damoony\Region\RegionCompiler;
use Damoony\Ui\Builder;
use Damoony\Ui\Element;
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

        $filterType = (string) $request->query('type', 'all');

        $blocksDir = $this->paths->domain($domain) . '/.alliasglobal';
        $manifestFile = $blocksDir . '/blocks.json';
        $manifest = is_file($manifestFile) ? json_decode((string)file_get_contents($manifestFile), true) : [];
        $manifestBlocks = is_array($manifest) && isset($manifest['blocks']) ? $manifest['blocks'] : [];

        $blocks = [];
        if (is_dir($blocksDir)) {
            $items = scandir($blocksDir) ?: [];
            foreach ($items as $item) {
                if ($item === '.' || $item === '..' || !is_dir($blocksDir . '/' . $item)) {
                    continue;
                }
                $type = $manifestBlocks[$item]['type'] ?? 'mixed';

                if ($filterType !== 'all' && $filterType !== $type) {
                    continue;
                }

                $blocks[] = [
                    'name' => $item,
                    'type' => $type,
                ];
            }
        }
        usort($blocks, fn ($a, $b) => strcmp($a['name'], $b['name']));

        $card = Builder::div('admin-card');

        $breadcrumbs = Builder::div('breadcrumbs');
        $breadcrumbs->append(Builder::a('/admin/?p=domain')->content(View::t('domain.bread_domains')));
        $breadcrumbs->append(Text::raw(' / '));
        $breadcrumbs->append(Builder::a('/admin/?p=domain:edit&domain=' . urlencode($domain))->content($domain));
        $card->append($breadcrumbs);

        $header = Builder::div('page-header');
        $header->append(Builder::h(1)->content(View::t('tab.blocks')));
        $actions = Builder::div('page-actions');
        $actions->append(Builder::a('/admin/?p=block:new&domain=' . urlencode($domain), 'btn btn-primary')->content(View::t('domain.add')));
        $header->append($actions);
        $card->append($header);

        $card->append(View::domainTabs($domain, 'blocks'));

        // Табы фильтрации
        $tabs = Builder::div('nav-tabs');
        $types = [
            'all' => View::t('block.all'),
            'script' => View::t('block.type_script'),
            'visual' => View::t('block.type_visual'),
            'mixed' => View::t('block.type_mixed'),
        ];
        foreach ($types as $code => $label) {
            $url = '/admin/?p=block&domain=' . urlencode($domain) . '&type=' . $code;
            $a = Builder::a($url, 'nav-tab');
            if ($filterType === $code) {
                $a->attrs()->classAdd('active');
            }
            $a->content($label);
            $tabs->append($a);
        }
        $card->append($tabs);

        $table = (new Table())->headers(View::t('block.th_name'), View::t('block.th_type'), View::t('domain.bind_th_act'));

        if ($blocks === []) {
            $table->row([Text::raw(View::t('stub.empty')), Text::raw(''), Text::raw('')]);
        } else {
            foreach ($blocks as $block) {
                $name = $block['name'];
                $type = $block['type'];
                $typeLabel = $types[$type] ?? $type;

                $codeUrl = '/admin/?p=editor&domain=' . urlencode($domain) . '&file=' . urlencode('.alliasglobal/' . $name . '/' . $name . '.php');
                $deleteUrl = '/admin/?p=block:remove&domain=' . urlencode($domain) . '&name=' . urlencode($name);

                $actionsHtml = Builder::div('table-actions');

                if ($type !== 'script') {
                    $visualUrl = '/admin/?p=block:edit&domain=' . urlencode($domain) . '&name=' . urlencode($name);
                    $actionsHtml->append(Builder::a($visualUrl, 'btn btn-primary')->content(View::t('block.visual_editor')));
                }

                $actionsHtml->append(Builder::a($codeUrl, 'btn btn-outline')->content(View::t('page.edit')));

                $delForm = Form::create($deleteUrl, 'POST')
                    ->csrf($this->csrf->field())
                    ->submit(View::t('stub.delete'));
                $delBtn = $delForm->build();
                foreach ($delBtn->children() as $child) {
                    if ($child instanceof Element && $child->tag() === 'button') {
                        $child->attrs()->classAdd('btn btn-danger');
                        $child->attrs()->styleAdd('padding:4px 10px;font-size:12px;');
                    }
                }
                $actionsHtml->append($delBtn);

                $table->row([
                    Text::raw('<b>' . View::esc($name) . '</b>'),
                    Text::raw(View::esc($typeLabel)),
                    $actionsHtml,
                ]);
            }
        }
        $card->append($table->build());

        return Response::html($this->view->render($card, ['title' => View::t('tab.blocks') . ': ' . $domain]));
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
        $typeVal = 'mixed';

        if ($request->isPost()) {
            $csrfErr = $this->requireCsrf($request);
            if ($csrfErr !== null) {
                return $csrfErr;
            }

            $nameVal = (string) ($request->post['name'] ?? '');
            $typeVal = (string) ($request->post['type'] ?? 'mixed');

            $cmd = new BlockNewCommand($this->paths);
            $res = $cmd->run(['domain' => $domain, 'name' => $nameVal, 'type' => $typeVal], new Context(static fn () => null));

            if ($res->ok) {
                return $this->redirect('/admin/?p=block&domain=' . urlencode($domain));
            }
            $error = implode('; ', $res->errors);
        }

        $form = Form::create('/admin/?p=block:new&domain=' . urlencode($domain), 'POST')
            ->csrf($this->csrf->field())
            ->add(Field::text('name')->label(View::t('filler.block_label'))->placeholder('top, footer, header')->required()->value($nameVal))
            ->add(Field::select('type')->label(View::t('block.type_label'))->options([
                'script' => View::t('block.opt_script'),
                'visual' => View::t('block.opt_visual'),
                'mixed' => View::t('block.opt_mixed'),
            ])->selected($typeVal))
            ->submit(View::t('domain.new_submit'));

        $card = Builder::div('admin-card');
        $card->append(Builder::h(1)->content(View::t('block.create_title')));
        if ($error !== '') {
            $card->append(Message::error($error)->build());
        }
        $card->append($form->build());

        return Response::html($this->view->render($card, ['title' => View::t('block.create_title')]));
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

        $blockDir = $this->paths->domain($domain) . '/.alliasglobal/' . $name;
        if (!is_dir($blockDir)) {
            return $this->redirect('/admin/?p=block&domain=' . urlencode($domain));
        }

        $editFile = $blockDir . '/.edit.json';
        $data = is_file($editFile) ? json_decode((string) file_get_contents($editFile), true) : [];
        $blocks = is_array($data) && isset($data['blocks']) ? $data['blocks'] : [];

        $card = Builder::div('admin-card');
        $card->append(Builder::a('/admin/?p=block&domain=' . urlencode($domain))->content(View::t('domain.bind_back')));
        $card->append(Builder::h(1)->content(View::t('block.edit_title') . ': ' . View::esc($name)));

        // Сборка формы через Builder
        $form = Builder::el('form');
        $form->attrs()->add('method', 'post');
        $form->attrs()->add('action', '/admin/?p=block:save&domain=' . urlencode($domain) . '&name=' . urlencode($name));
        $form->attrs()->classAdd('editor-form');
        $form->append(Text::raw($this->csrf->field()));

        $blocksContainer = Builder::div();
        $blocksContainer->attrs()->add('id', 'blocks-container');

        if ($blocks === []) {
            $blocks = [['type' => 'text', 'content' => '']];
        }
        foreach ($blocks as $i => $block) {
            // Рендерер возвращает строку, поэтому оборачиваем в Text::raw
            $blocksContainer->append(Text::raw(VisualEditorRenderer::renderBlockRow($i, $block)));
        }
        $form->append($blocksContainer);

        $actionsDiv = Builder::div();
        $actionsDiv->attrs()->styleAdd('margin-top: 15px; display: flex; gap: 10px;');

        $addBtn = Builder::el('button');
        $addBtn->attrs()->add('type', 'button');
        $addBtn->attrs()->add('id', 'add-block-btn');
        $addBtn->attrs()->classAdd('btn btn-outline');
        $addBtn->content(View::t('page.add_block'));
        $actionsDiv->append($addBtn);

        $saveBtn = Builder::el('button');
        $saveBtn->attrs()->add('type', 'submit');
        $saveBtn->attrs()->classAdd('btn btn-primary');
        $saveBtn->content(View::t('domain.save'));
        $actionsDiv->append($saveBtn);

        $form->append($actionsDiv);
        $card->append($form);

        return Response::html($this->view->render($card, ['title' => View::t('block.edit_title')]));
    }

    public function save(Request $request): Response
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

        $blockDir = $this->paths->domain($domain) . '/.alliasglobal/' . $name;
        if (!is_dir($blockDir)) {
            return $this->redirect('/admin/?p=block&domain=' . urlencode($domain));
        }

        $blocks = $request->post['blocks'] ?? [];
        if (!is_array($blocks)) {
            $blocks = [];
        }

        $cleanBlocks = [];
        foreach ($blocks as $b) {
            if (isset($b['type'], $b['content']) && trim((string)$b['content']) !== '') {
                $cleanBlocks[] = ['type' => (string)$b['type'], 'content' => (string)$b['content']];
            }
        }

        Fs::writeAtomic($blockDir . '/.edit.json', json_encode(['blocks' => $cleanBlocks], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");

        $siteFile = $this->paths->siteConfig();
        $site = Config::fromFile($siteFile)->all();
        $realDomain = (string) $site['domains_real'][$domain];
        $namespace = (string) ($site['domains_namespace'][$realDomain] ?? str_replace(['.', '-'], '_', $realDomain));

        $compiler = new RegionCompiler();
        $php = $compiler->compile($namespace, $name, $cleanBlocks);

        Fs::writeAtomic($blockDir . '/' . $name . '.php', $php);

        return $this->redirect('/admin/?p=block&domain=' . urlencode($domain));
    }

    public function remove(Request $request): Response
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

        if ($domain !== '' && $name !== '') {
            $cmd = new BlockRemoveCommand($this->paths);
            $cmd->run(['domain' => $domain, 'name' => $name], new Context(static fn () => null));
        }

        return $this->redirect('/admin/?p=block&domain=' . urlencode($domain));
    }
}
