<?php

declare(strict_types=1);

namespace Damoony\Admin\Controller;

use Damoony\Admin\View;
use Damoony\Cli\Commands\FillerNewCommand;
use Damoony\Cli\Commands\FillerToggleCommand;
use Damoony\Cli\Context;
use Damoony\Core\Config;
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

final class FillerController extends BaseController
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
        $allias = (string) $request->query('allias', '');

        if ($domain === '' || $allias === '') {
            return $this->redirect('/admin/?p=domain');
        }

        $fillers = $this->siteConfig->getByKey(['fillers', $domain, $allias], []);

        $success = '';
        if ($request->query('synced')) {
            $count = (int) $request->query('synced');
            $success = $count > 0
                ? View::t('filler.sync_success', ['count' => $count])
                : View::t('filler.sync_empty');
        }

        $card = Builder::div('admin-card');

        $breadcrumbs = Builder::div('breadcrumbs');
        $breadcrumbs->append(Builder::a('/admin/?p=domain')->content(View::t('domain.bread_domains')));
        $breadcrumbs->append(Text::raw(' / '));
        $breadcrumbs->append(Builder::a('/admin/?p=domain:edit&domain=' . urlencode($domain))->content($domain));
        $breadcrumbs->append(Text::raw(' / '));
        $breadcrumbs->append(Builder::a('/admin/?p=allias&domain=' . urlencode($domain))->content(View::t('tab.alliases')));
        $card->append($breadcrumbs);

        $header = Builder::div('page-header');
        $header->append(Builder::h(1)->content(View::t('filler.title') . ': ' . View::esc($allias)));

        $actions = Builder::div('page-actions');

        // Форма синхронизации (POST)
        $syncForm = Form::create('/admin/?p=filler:sync&domain=' . urlencode($domain) . '&allias=' . urlencode($allias), 'POST')
            ->csrf($this->csrf->field())
            ->submit(View::t('filler.sync'));

        $syncBtn = $syncForm->build();
        foreach ($syncBtn->children() as $child) {
            if ($child instanceof Element && $child->tag() === 'button') {
                $child->attrs()->classAdd('btn btn-outline');
            }
        }

        $actions->append($syncBtn);
        $actions->append(Builder::a('/admin/?p=filler:new&domain=' . urlencode($domain) . '&allias=' . urlencode($allias), 'btn btn-primary')->content(View::t('filler.add')));
        $header->append($actions);
        $card->append($header);

        $card->append(View::alliasTabs($domain, $allias, 'blocks'));

        if ($success !== '') {
            $card->append(Message::success($success)->build());
        }

        $table = (new Table())->headers(View::t('filler.th_block'), View::t('filler.th_status'), View::t('domain.bind_th_act'));

        if (is_array($fillers) && $fillers !== []) {
            foreach ($fillers as $block => $enabled) {
                $statusHtml = $enabled
                    ? '<span class="badge" style="background:#dcfce7; color:#166534;">' . View::t('filler.enabled') . '</span>'
                    : '<span class="badge" style="background:#fee2e2; color:#991b1b;">' . View::t('filler.disabled') . '</span>';

                $form = Form::create('/admin/?p=filler:toggle&domain=' . urlencode($domain) . '&allias=' . urlencode($allias), 'POST')
                    ->csrf($this->csrf->field())
                    ->add(Field::hidden('block')->value((string)$block))
                    ->add(Field::hidden('value')->value($enabled ? '0' : '1'))
                    ->submit($enabled ? View::t('filler.disable') : View::t('filler.enable'));

                $toggleBtn = $form->build();
                foreach ($toggleBtn->children() as $child) {
                    if ($child instanceof Element && $child->tag() === 'button') {
                        $child->attrs()->classAdd('btn ' . ($enabled ? 'btn-danger' : 'btn-outline'));
                        $child->attrs()->styleAdd('padding: 6px 12px; font-size: 13px;');
                    }
                }

                $table->row([
                    Text::raw('<b>' . View::esc((string)$block) . '</b>'),
                    Text::raw($statusHtml),
                    $toggleBtn,
                ]);
            }
        } else {
            $table->row([Text::raw(View::t('filler.empty')), Text::raw(''), Text::raw('')]);
        }

        $card->append($table->build());

        return Response::html($this->view->render($card, ['title' => View::t('filler.title')]));
    }

    public function sync(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) {
            return $auth;
        }

        if (!$request->isPost()) {
            return $this->redirect('/admin/?p=domain');
        }

        $csrfErr = $this->requireCsrf($request);
        if ($csrfErr !== null) {
            return $csrfErr;
        }

        $domain = (string) $request->query('domain', '');
        $allias = (string) $request->query('allias', '');

        $globalDir = $this->paths->domain($domain) . '/.alliasglobal';
        $blocks = is_dir($globalDir) ? glob($globalDir . '/*', GLOB_ONLYDIR) : [];

        $syncedCount = 0;
        $cmd = new FillerNewCommand($this->paths);

        if ($blocks) {
            foreach ($blocks as $blockPath) {
                $blockName = basename($blockPath);

                // Пытаемся создать для каждого блока.
                // Если файл уже есть, FillerNewCommand вернет ошибку 'Filler уже существует'.
                $res = $cmd->run(['domain' => $domain, 'allias' => $allias, 'block' => $blockName], new Context(static fn () => null));

                // Если команда отработала успешно — значит, создала новый файл
                if ($res->ok) {
                    $syncedCount++;
                }
            }
        }

        return $this->redirect('/admin/?p=filler&domain=' . urlencode($domain) . '&allias=' . urlencode($allias) . '&synced=' . $syncedCount);
    }

    public function new(Request $request): Response
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

        $error = '';
        $blockVal = '';

        if ($request->isPost()) {
            $csrfErr = $this->requireCsrf($request);
            if ($csrfErr !== null) {
                return $csrfErr;
            }

            $blockVal = (string) ($request->post['block'] ?? '');
            $cmd = new FillerNewCommand($this->paths);
            $res = $cmd->run(['domain' => $domain, 'allias' => $allias, 'block' => $blockVal], new Context(static fn () => null));

            if ($res->ok) {
                return $this->redirect('/admin/?p=filler&domain=' . urlencode($domain) . '&allias=' . urlencode($allias));
            }
            $error = implode('; ', $res->errors);
        }

        $card = Builder::div('admin-card');

        $breadcrumbs = Builder::div('breadcrumbs');
        $breadcrumbs->append(Builder::a('/admin/?p=filler&domain=' . urlencode($domain) . '&allias=' . urlencode($allias))->content(View::t('domain.bind_back')));
        $card->append($breadcrumbs);

        $card->append(Builder::h(1)->content(View::t('filler.new_title') . ' (' . View::esc($allias) . ')'));
        $card->append(View::alliasTabs($domain, $allias, 'blocks'));

        if ($error !== '') {
            $card->append(Message::error($error)->build());
        }

        $form = Form::create('/admin/?p=filler:new&domain=' . urlencode($domain) . '&allias=' . urlencode($allias), 'POST')
            ->csrf($this->csrf->field())
            ->add(Field::text('block')->label(View::t('filler.block_label'))->required()->value($blockVal))
            ->submit(View::t('domain.new_submit'));

        $card->append($form->build());

        return Response::html($this->view->render($card, ['title' => View::t('filler.new_title')]));
    }

    public function toggle(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) {
            return $auth;
        }

        if (!$request->isPost()) {
            return $this->redirect('/admin/?p=domain');
        }

        $csrfErr = $this->requireCsrf($request);
        if ($csrfErr !== null) {
            return $csrfErr;
        }

        $domain = (string) $request->query('domain', '');
        $allias = (string) $request->query('allias', '');
        $block = (string) ($request->post['block'] ?? '');
        $value = (string) ($request->post['value'] ?? '1');

        if ($domain !== '' && $allias !== '' && $block !== '') {
            $cmd = new FillerToggleCommand($this->paths);
            $cmd->run(['domain' => $domain, 'allias' => $allias, 'block' => $block, 'value' => $value], new Context(static fn () => null));
        }

        return $this->redirect('/admin/?p=filler&domain=' . urlencode($domain) . '&allias=' . urlencode($allias));
    }
}
