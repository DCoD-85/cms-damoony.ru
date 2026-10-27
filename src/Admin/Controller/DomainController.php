<?php

declare(strict_types=1);

namespace Damoony\Admin\Controller;

use Damoony\Admin\View;
use Damoony\Cli\Commands\DomainNewCommand;
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

final class DomainController extends BaseController
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

        $domains = $this->siteConfig->getByKey(['domains_real'], []);

        $grouped = [];
        if (is_array($domains)) {
            foreach ($domains as $incoming => $real) {
                $grouped[$real][] = $incoming;
            }
        }

        $card = Builder::div('admin-card');

        $header = Builder::div('page-header');
        $header->append(Builder::h(1)->content(View::t('domain.title')));

        $actions = Builder::div('page-actions');
        $actions->append(Builder::a('/admin/?p=stub', 'btn btn-outline')->content(View::t('domain.stub_lib')));
        $actions->append(Builder::a('/admin/?p=domain:new', 'btn btn-primary')->content(View::t('domain.add')));
        $header->append($actions);

        $card->append($header);

        $table = (new Table())->headers(View::t('domain.th_status'), View::t('domain.th_domain'), View::t('domain.th_ns'), View::t('domain.th_files'), View::t('domain.th_aliases'));

        foreach ($grouped as $real => $incomingDomains) {
            $ns = (string) $this->siteConfig->getByKey(['domains_namespace', $real], '-');
            $available = (bool) $this->siteConfig->getByKey(['domains_available', $real], false);
            $hasFiles = is_dir($this->paths->domain($real));

            $aliasesHtml = '';
            $alliases = $this->siteConfig->getByKey(['alliases_real', $real], []);
            if (is_array($alliases)) {
                $aliasArray = [];
                foreach ($alliases as $a => $mapped) {
                    if ($a === '') {
                        continue;
                    }
                    $badgeClass = $available ? 'badge' : 'badge badge-disabled';
                    $aliasArray[] = '<span class="' . $badgeClass . '">' . View::esc((string)$a) . '</span>';
                }
                $aliasesHtml = implode(' ', $aliasArray);
            }

            $statusUrl = '/admin/?p=domain:toggle&domain=' . urlencode($real);
            $statusHtml = $available
                ? '<a href="' . $statusUrl . '" class="status-toggle" title="' . View::t('domain.avail_label') . '"></a>'
                : '<a href="' . $statusUrl . '" class="status-toggle off" title="' . View::t('domain.avail_label') . '"></a>';

            $filesHtml = $hasFiles ? '✓' : '✗';

            $domainLink = Builder::a('/admin/?p=domain:edit&domain=' . urlencode($real), 'domain-link')->content(View::esc($real));

            $row = [
                Text::raw($statusHtml),
                Text::raw($domainLink->release() . '<div style="font-size:12px; color:#888; font-weight:normal;">aliases: ' . View::esc(implode(', ', $incomingDomains)) . '</div>'),
                Text::raw(View::esc($ns)),
                Text::raw($filesHtml),
                Text::raw($aliasesHtml),
            ];

            $rowClass = $available ? 'row-active' : '';
            $table->row($row, $rowClass);
        }

        $card->append($table->build());

        return Response::html($this->view->render($card, ['title' => View::t('domain.title')]));
    }

    // ... остальные методы (toggle, bindings, new, attach, detach, setPrimary, saveComment, edit) остаются без изменений

    public function toggle(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) {
            return $auth;
        }

        $domain = (string) $request->query('domain', '');
        $domains = $this->siteConfig->getByKey(['domains_real'], []);

        if ($domain === '' || !is_array($domains) || !isset($domains[$domain])) {
            return $this->redirect('/admin/?p=domain');
        }

        $data = $this->siteConfig->all();
        $current = (bool) ($data['domains_available'][$domain] ?? false);
        $data['domains_available'][$domain] = !$current;

        Fs::writeAtomic(
            $this->paths->siteConfig(),
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n",
        );

        return $this->redirect('/admin/?p=domain');
    }

    public function bindings(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) {
            return $auth;
        }

        $domain = (string) $request->query('domain', '');
        if ($domain === '') {
            return $this->redirect('/admin/?p=domain');
        }

        $domains = $this->siteConfig->getByKey(['domains_real'], []);
        $incomingList = [];

        if (is_array($domains)) {
            foreach ($domains as $incoming => $real) {
                if ($real === $domain) {
                    $incomingList[$incoming] = $incoming;
                }
            }
        }

        $card = Builder::div('admin-card');
        $breadcrumbs = Builder::div('breadcrumbs');
        $breadcrumbs->append(Builder::a('/admin/?p=domain')->content(View::t('domain.bread_domains')));
        $breadcrumbs->append(Text::raw(' / '));
        $breadcrumbs->append(Builder::a('/admin/?p=domain:edit&domain=' . urlencode($domain))->content($domain));
        $card->append($breadcrumbs);
        $card->append(Builder::h(1)->content(View::t('domain.bind_title')));
        $card->append(View::domainTabs($domain, 'bindings'));

        if ($request->query('saved')) {
            $card->append(Message::success(View::t('msg.saved'))->build());
        }

        $table = (new Table())->headers(View::t('domain.bind_th_in'), View::t('domain.bind_th_com'), View::t('domain.bind_th_act'));

        $index = 0;
        foreach ($incomingList as $incoming) {
            $isMain = ($index === 0);
            $comment = (string) $this->siteConfig->getByKey(['domains_comments', $incoming], '');

            $commentForm = Form::create('/admin/?p=domain:saveComment&domain=' . urlencode($domain) . '&incoming=' . urlencode($incoming), 'POST')
                ->csrf($this->csrf->field())
                ->add(Field::text('comment')->value($comment)->placeholder('...'))
                ->submit('OK');
            $commentFormNode = $commentForm->build();
            $commentFormNode->attrs()->classAdd('table-comment-form');

            $actionsHtml = Builder::div('table-actions');

            if (!$isMain) {
                $actionsHtml->append(Builder::a('/admin/?p=domain:setPrimary&domain=' . urlencode($domain) . '&incoming=' . urlencode($incoming), 'btn btn-outline')->content(View::t('domain.bind_make_main')));
                $actionsHtml->append(Builder::a('/admin/?p=domain:detach&domain=' . urlencode($domain) . '&incoming=' . urlencode($incoming), 'btn btn-danger')->content(View::t('domain.bind_detach')));
            } else {
                $actionsHtml->append(Builder::span('badge')->content(View::t('domain.bind_main')));
            }

            $table->row([
                Text::raw('<b>' . View::esc($incoming) . '</b>'),
                $commentFormNode,
                $actionsHtml,
            ]);
            $index++;
        }

        $card->append($table->build());
        $card->append(Builder::el('br'));
        $card->append(Builder::a('/admin/?p=domain:attach&domain=' . urlencode($domain), 'btn btn-primary')->content(View::t('domain.bind_add')));

        return Response::html($this->view->render($card, ['title' => View::t('domain.bind_title') . ': ' . $domain]));
    }

    public function new(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) {
            return $auth;
        }

        $error = '';
        $domainVal = '';
        $nsVal = '';

        if ($request->isPost()) {
            $csrfErr = $this->requireCsrf($request);
            if ($csrfErr !== null) {
                return $csrfErr;
            }

            $domainVal = (string) ($request->post['domain'] ?? '');
            $nsVal = (string) ($request->post['namespace'] ?? '');

            $cmd = new DomainNewCommand($this->paths);
            $res = $cmd->run(['domain' => $domainVal, 'namespace' => $nsVal], new Context(static fn () => null));

            if ($res->ok) {
                return $this->redirect('/admin/?p=domain');
            }
            $error = implode('; ', $res->errors);
        }

        $form = Form::create('/admin/?p=domain:new', 'POST')
            ->csrf($this->csrf->field())
            ->add(Field::text('domain')->label(View::t('domain.new_domain'))->placeholder('example.ru')->required()->value($domainVal))
            ->add(Field::text('namespace')->label(View::t('domain.new_ns'))->placeholder('example_ru')->value($nsVal))
            ->submit(View::t('domain.new_submit'));

        $card = Builder::div('admin-card');
        $card->append(Builder::h(1)->content(View::t('domain.new_title')));
        if ($error !== '') {
            $card->append(Message::error($error)->build());
        }
        $card->append($form->build());

        $backBtn = Builder::a('/admin/?p=domain', 'btn btn-outline')->content(View::t('domain.back'));
        $backBtn->attrs()->styleAdd('margin-top:20px;');
        $card->append($backBtn);

        return Response::html($this->view->render($card, ['title' => View::t('domain.new_title')]));
    }

    public function attach(Request $request): Response
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
        $incomingVal = '';

        if ($request->isPost()) {
            $csrfErr = $this->requireCsrf($request);
            if ($csrfErr !== null) {
                return $csrfErr;
            }

            $incomingVal = (string) ($request->post['incoming'] ?? '');
            $fromDomain = (string) ($request->post['from_domain'] ?? '');

            if ($fromDomain !== '') {
                $incomingVal = $fromDomain;
            }

            if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $incomingVal)) {
                $error = 'Invalid domain format';
            } elseif ($incomingVal === $domain) {
                $error = 'Cannot bind domain to itself';
            } else {
                $data = $this->siteConfig->all();
                if (isset($data['domains_real'][$incomingVal]) && $data['domains_real'][$incomingVal] !== $domain) {
                    $error = 'This domain is already bound to ' . $data['domains_real'][$incomingVal];
                } else {
                    $data['domains_real'][$incomingVal] = $domain;
                    Fs::writeAtomic($this->paths->siteConfig(), json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
                    return $this->redirect('/admin/?p=domain:bindings&domain=' . urlencode($domain) . '&saved=1');
                }
            }
        }

        $domains = $this->siteConfig->getByKey(['domains_real'], []);
        $transferOptions = [];
        if (is_array($domains)) {
            foreach ($domains as $inc => $real) {
                if ($real !== $domain && $inc !== $domain) {
                    $transferOptions[$inc] = $inc . ' (from ' . $real . ')';
                }
            }
        }

        $form = Form::create('/admin/?p=domain:attach&domain=' . urlencode($domain), 'POST')
            ->csrf($this->csrf->field())
            ->add(Field::text('incoming')->label(View::t('domain.bind_new'))->placeholder('example.com')->value($incomingVal)->hint(View::t('domain.bind_new_hint')));

        if ($transferOptions !== []) {
            $form->add(Field::select('from_domain')->label(View::t('domain.bind_from'))->options(['' => View::t('domain.bind_from_no')] + $transferOptions));
        }

        $form->submit(View::t('domain.bind_attach_submit'));

        $card = Builder::div('admin-card');
        $card->append(Builder::a('/admin/?p=domain:bindings&domain=' . urlencode($domain))->content(View::t('domain.bind_back')));
        $card->append(Builder::h(1)->content(View::t('domain.bind_attach_title', ['domain' => $domain])));

        if ($error !== '') {
            $card->append(Message::error($error)->build());
        }

        $card->append($form->build());

        return Response::html($this->view->render($card, ['title' => View::t('domain.bind_attach_title', ['domain' => $domain])]));
    }

    public function detach(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) {
            return $auth;
        }

        $domain = (string) $request->query('domain', '');
        $incoming = (string) $request->query('incoming', '');

        if ($domain === '' || $incoming === '') {
            return $this->redirect('/admin/?p=domain');
        }

        $domains = $this->siteConfig->getByKey(['domains_real'], []);
        $isMain = false;
        if (is_array($domains)) {
            $first = array_key_first(array_filter($domains, fn ($r) => $r === $domain));
            if ($first === $incoming) {
                $isMain = true;
            }
        }
        if ($isMain) {
            return $this->redirect('/admin/?p=domain:bindings&domain=' . urlencode($domain));
        }

        $error = '';

        if ($request->isPost()) {
            $csrfErr = $this->requireCsrf($request);
            if ($csrfErr !== null) {
                return $csrfErr;
            }

            $action = (string) ($request->post['action'] ?? 'delete');
            $newFolder = (string) ($request->post['new_folder'] ?? '');

            $data = $this->siteConfig->all();

            if ($action === 'delete') {
                unset($data['domains_real'][$incoming]);
            } else {
                if (!preg_match('/^[a-z0-9_\-]+$/', $newFolder)) {
                    $error = 'Invalid folder name (a-z, 0-9, _, - only)';
                } elseif (is_dir($this->paths->domain($newFolder))) {
                    $error = 'Folder with this name already exists';
                } else {
                    $sourceDir = $this->paths->domain($domain);
                    $targetDir = $this->paths->domain($newFolder);

                    if ($action === 'copy') {
                        if (!is_dir($sourceDir)) {
                            $error = 'Source folder not found';
                        } else {
                            $this->copyDir($sourceDir, $targetDir);
                        }
                    } elseif ($action === 'template') {
                        $tplDir = $this->paths->root() . '/_dev/tpl/domain';
                        if (!is_dir($tplDir)) {
                            $error = 'Domain template not found (missing _dev/tpl/domain)';
                        } else {
                            $this->copyDir($tplDir, $targetDir);
                        }
                    }

                    if ($error === '') {
                        $oldNs = str_replace(['.', '-'], '_', $domain);
                        $newNs = str_replace(['.', '-'], '_', $newFolder);
                        $this->replaceNamespace($targetDir, $oldNs, $newNs);

                        $data['domains_real'][$incoming] = $newFolder;
                        $data['domains_namespace'][$newFolder] = $newNs;
                        $data['domains_available'][$newFolder] = $data['domains_available'][$domain] ?? true;
                    }
                }
            }

            if ($error === '') {
                Fs::writeAtomic($this->paths->siteConfig(), json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
                return $this->redirect('/admin/?p=domain:bindings&domain=' . urlencode($domain) . '&saved=1');
            }
        }

        $form = Form::create('/admin/?p=domain:detach&domain=' . urlencode($domain) . '&incoming=' . urlencode($incoming), 'POST')
            ->csrf($this->csrf->field())
            ->add(Field::radio('action')->label(View::t('domain.det_act_delete'))->value('delete')->checked()->hint(View::t('domain.det_act_delete_hint')))
            ->add(Field::radio('action')->label(View::t('domain.det_act_template'))->value('template')->hint(View::t('domain.det_act_template_hint')))
            ->add(Field::radio('action')->label(View::t('domain.det_act_copy'))->value('copy')->hint(View::t('domain.det_act_copy_hint')))
            ->add(Field::text('new_folder')->label(View::t('domain.det_new_folder'))->placeholder('example_new_ru'))
            ->submit(View::t('domain.det_submit'));

        $card = Builder::div('admin-card');
        $card->append(Builder::a('/admin/?p=domain:bindings&domain=' . urlencode($domain))->content(View::t('domain.bind_back')));
        $card->append(Builder::h(1)->content(View::t('domain.bind_detach_title', ['domain' => $incoming])));

        if ($error !== '') {
            $card->append(Message::error($error)->build());
        }

        $card->append($form->build());

        return Response::html($this->view->render($card, ['title' => View::t('domain.bind_detach_title', ['domain' => $incoming])]));
    }

    private function replaceNamespace(string $dir, string $oldNs, string $newNs): void
    {
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($it as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $content = file_get_contents($file->getPathname());
                if ($content !== false) {
                    $content = str_replace("namespace {$oldNs}", "namespace {$newNs}", $content);
                    file_put_contents($file->getPathname(), $content);
                }
            }
        }
    }

    public function setPrimary(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) {
            return $auth;
        }

        $domain = (string) $request->query('domain', '');
        $incoming = (string) $request->query('incoming', '');

        if ($domain !== '' && $incoming !== '') {
            $data = $this->siteConfig->all();
            $realDomains = $data['domains_real'];

            if (isset($realDomains[$incoming])) {
                $val = $realDomains[$incoming];
                unset($realDomains[$incoming]);
                $data['domains_real'] = [$incoming => $val] + $realDomains;

                Fs::writeAtomic($this->paths->siteConfig(), json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
            }
        }

        return $this->redirect('/admin/?p=domain:bindings&domain=' . urlencode($domain) . '&saved=1');
    }

    public function saveComment(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) {
            return $auth;
        }

        $domain = (string) $request->query('domain', '');
        $incoming = (string) $request->query('incoming', '');

        if ($domain === '' || $incoming === '') {
            return $this->redirect('/admin/?p=domain');
        }

        if ($request->isPost()) {
            $csrfErr = $this->requireCsrf($request);
            if ($csrfErr !== null) {
                return $csrfErr;
            }

            $comment = (string) ($request->post['comment'] ?? '');
            $data = $this->siteConfig->all();
            $data['domains_comments'][$incoming] = $comment;

            Fs::writeAtomic($this->paths->siteConfig(), json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
        }

        return $this->redirect('/admin/?p=domain:bindings&domain=' . urlencode($domain) . '&saved=1');
    }

    public function makePrimary(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) {
            return $auth;
        }

        $domain = (string) $request->query('domain', '');
        $incoming = (string) $request->query('incoming', '');

        if ($domain === '' || $incoming === '' || $incoming === $domain) {
            return $this->redirect('/admin/?p=domain');
        }

        $sourceDir = $this->paths->domain($domain);
        $targetDir = $this->paths->domain($incoming);

        if (!is_dir($targetDir) && is_dir($sourceDir)) {
            $this->copyDir($sourceDir, $targetDir);

            $oldNs = str_replace(['.', '-'], '_', $domain);
            $newNs = str_replace(['.', '-'], '_', $incoming);

            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($targetDir));
            foreach ($it as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $content = file_get_contents($file->getPathname());
                    if ($content !== false) {
                        $content = str_replace("namespace {$oldNs}", "namespace {$newNs}", $content);
                        file_put_contents($file->getPathname(), $content);
                    }
                }
            }

            $data = $this->siteConfig->all();
            $data['domains_real'][$incoming] = $incoming;
            $data['domains_real'][$domain] = $incoming;
            $data['domains_namespace'][$incoming] = $newNs;
            $data['domains_available'][$incoming] = $data['domains_available'][$domain] ?? true;

            Fs::writeAtomic($this->paths->siteConfig(), json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");

            return $this->redirect('/admin/?p=domain:bindings&domain=' . urlencode($incoming) . '&saved=1');
        }

        return $this->redirect('/admin/?p=domain:bindings&domain=' . urlencode($domain));
    }

    private function copyDir(string $src, string $dst): void
    {
        @mkdir($dst, 0o775, true);
        $dir = @opendir($src);
        if (!is_resource($dir)) {
            return;
        }
        while (false !== ($file = readdir($dir))) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            if (is_dir($src . '/' . $file)) {
                $this->copyDir($src . '/' . $file, $dst . '/' . $file);
            } else {
                @copy($src . '/' . $file, $dst . '/' . $file);
            }
        }
        closedir($dir);
    }

    public function edit(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) {
            return $auth;
        }

        $domain = (string) $request->query('domain', '');
        if ($domain === '') {
            return $this->redirect('/admin/?p=domain');
        }

        if ($this->siteConfig->getByKey(['domains_real', $domain]) === null) {
            return $this->redirect('/admin/?p=domain');
        }

        $error = '';
        $success = $request->query('saved') ? View::t('msg.saved') : '';

        if ($request->isPost()) {
            $csrfErr = $this->requireCsrf($request);
            if ($csrfErr !== null) {
                return $csrfErr;
            }

            $title = (string) ($request->post['title'] ?? '');
            $available = isset($request->post['available']);
            $stub = (string) ($request->post['stub'] ?? 'maintenance');
            $until = (string) ($request->post['until'] ?? '');

            $data = $this->siteConfig->all();
            $data['domains_titles'][$domain] = $title;
            $data['domains_available'][$domain] = $available;
            $data['domains_stub'][$domain]['current'] = $stub;

            if ($until !== '') {
                $data['domains_stub'][$domain]['until'] = strtotime($until);
            } else {
                unset($data['domains_stub'][$domain]['until']);
            }

            try {
                Fs::writeAtomic($this->paths->siteConfig(), json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
                return $this->redirect('/admin/?p=domain:edit&domain=' . urlencode($domain) . '&saved=1');
            } catch (\Throwable $e) {
                $error = View::t('msg.error', ['msg' => $e->getMessage()]);
            }
        }

        $title = (string) $this->siteConfig->getByKey(['domains_titles', $domain], '');
        $available = (bool) $this->siteConfig->getByKey(['domains_available', $domain], false);
        $stub = (string) $this->siteConfig->getByKey(['domains_stub', $domain, 'current'], 'maintenance');
        $until = $this->siteConfig->getByKey(['domains_stub', $domain, 'until'], 0);
        $untilStr = $until > 0 ? date('Y-m-d\TH:i', (int)$until) : '';

        $form = Form::create('/admin/?p=domain:edit&domain=' . urlencode($domain), 'POST')
            ->csrf($this->csrf->field())
            ->add(Field::text('title')->label(View::t('domain.title_label'))->value($title))
            ->add(Field::checkbox('available')->label(View::t('domain.avail_label'))->checked($available))
            ->add(Field::select('stub')->label(View::t('domain.stub_label'))->options($this->getStubOptions())->selected($stub))
            ->add(Field::datetime('until')->label(View::t('domain.until_label'))->hint(View::t('domain.until_hint'))->value($untilStr))
            ->submit(View::t('domain.save'));

        $card = Builder::div('admin-card');

        $breadcrumbs = Builder::div('breadcrumbs');
        $breadcrumbs->append(Builder::a('/admin/?p=domain')->content(View::t('domain.bread_domains')));
        $breadcrumbs->append(Text::raw(' / '));
        $breadcrumbs->append(Builder::span()->content($domain));

        $card->append($breadcrumbs);
        $card->append(Builder::h(1)->content(View::t('domain.settings_title')));
        $card->append(View::domainTabs($domain, 'settings'));

        if ($success !== '') {
            $card->append(Message::success($success)->build());
        }
        if ($error !== '') {
            $card->append(Message::error($error)->build());
        }

        $card->append($form->build());

        $hr = Builder::el('hr');
        $hr->attrs()->styleAdd('margin: 30px 0; border: 0; border-top: 1px solid #eee;');
        $card->append($hr);

        $dangerZone = Builder::div('danger-zone');
        $dangerZone->append(Builder::p()->content(View::t('domain.delete_warn')));
        $dangerZone->append(Builder::a('/admin/?p=domain:remove&domain=' . urlencode($domain), 'btn btn-danger')->content(View::t('domain.delete_btn')));

        $card->append($dangerZone);

        return Response::html($this->view->render($card, ['title' => View::t('domain.settings_title') . ': ' . $domain]));
    }

    /** @return array<string, string> */
    private function getStubOptions(): array
    {
        $dir = $this->paths->root() . '/stubs';
        $files = is_dir($dir) ? glob($dir . '/*.html') : [];
        $options = [];
        if ($files) {
            foreach ($files as $file) {
                $options[basename($file, '.html')] = basename($file, '.html');
            }
        }
        return $options ?: ['maintenance' => 'maintenance'];
    }
}
