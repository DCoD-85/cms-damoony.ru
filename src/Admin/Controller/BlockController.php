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
            foreach (scandir($blocksDir) ?: [] as $item) {
                if ($item === '.' || $item === '..' || !is_dir($blocksDir . '/' . $item)) {
                    continue;
                }
                $type = $manifestBlocks[$item]['type'] ?? 'mixed';
                if ($filterType !== 'all' && $filterType !== $type) {
                    continue;
                }
                $blocks[] = ['name' => $item, 'type' => $type];
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
        $actions->append(Builder::a('/admin/?p=block:new&domain=' . urlencode($domain), 'btn btn-primary')->content(View::t('block.add')));
        $header->append($actions);
        $card->append($header);
        $card->append(View::domainTabs($domain, 'blocks'));

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
                $actionsHtml->append(Builder::a($codeUrl, 'btn btn-primary')->content(View::t('page.edit')));

                $delForm = Form::create($deleteUrl, 'POST')->csrf($this->csrf->field())->submit(View::t('stub.delete'));
                $delBtn = $delForm->build();
                foreach ($delBtn->children() as $child) {
                    if ($child instanceof Element && $child->tag() === 'button') {
                        $child->attrs()->classAdd('btn btn-danger');
                        $child->attrs()->styleAdd('padding:4px 10px;font-size:12px;');
                    }
                }
                $actionsHtml->append($delBtn);

                $table->row([Builder::el('b')->content($name), $typeLabel, $actionsHtml]);
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
