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
        $breadcrumbs->append(Builder::a('/admin/?p=domain')->content(View::t('domain.bread_domains')));
        $breadcrumbs->append(Text::raw(' / '));
        $breadcrumbs->append(Builder::a('/admin/?p=domain:edit&domain=' . urlencode($domain))->content($domain));
        $card->append($breadcrumbs);

        // Заголовок
        $header = Builder::div('page-header');
        $header->append(Builder::h(1)->content(View::t('tab.alliases')));

        $actions = Builder::div('page-actions');
        $actions->append(Builder::a('/admin/?p=allias:new&domain=' . urlencode($domain), 'btn btn-primary')->content(View::t('allias.new_title')));
        $header->append($actions);
        $card->append($header);

        // Табы
        $card->append(View::domainTabs($domain, 'allias'));

        // Таблица
        $table = (new Table())->headers(
            View::t('domain.th_status'),
            View::t('allias.title'),
            View::t('allias.th_path'),
            View::t('domain.bind_th_act'),
        );

        if (is_array($aliases) && $aliases !== []) {
            foreach ($aliases as $name => $mapped) {
                if ($name === '') {
                    continue;
                }

                $available = (bool) $this->siteConfig->getByKey(['alliases_available', $domain, (string)$name], true);

                // Статус
                $statusUrl = '/admin/?p=allias:toggle&domain=' . urlencode($domain) . '&name=' . urlencode((string)$name);
                $statusNode = Builder::a($statusUrl, $available ? 'status-toggle' : 'status-toggle off');
                $statusNode->attrs()->add('title', View::t('domain.avail_label'));

                // Ссылка на редактирование
                $editUrl = '/admin/?p=allias:edit&domain=' . urlencode($domain) . '&allias=' . urlencode((string)$name);
                $aliasLink = Builder::a($editUrl, 'domain-link')->content((string)$name);

                // Путь
                $pathNode = Builder::span();
                $pathNode->content('app/' . $domain . '/' . (string)$name);
                $pathNode->attrs()->styleAdd('font-family:monospace; font-size:12px; background:#f3f4f6; padding:2px 6px; border-radius:4px;');

                // Действия
                $actionsNode = Builder::div('table-actions');
                $actionsNode->append(Builder::a($editUrl, 'btn btn-outline')->content(View::t('page.edit')));
                $actionsNode->append(Builder::a('/admin/?p=allias:remove&domain=' . urlencode($domain) . '&name=' . urlencode((string)$name), 'btn btn-danger')->content(View::t('stub.delete')));

                $table->row([
                    $statusNode,
                    $aliasLink,
                    $pathNode,
                    $actionsNode,
                ]);
            }
        } else {
            $table->row([Text::escaped(''), Text::escaped(View::t('allias.empty')), Text::escaped(''), Text::escaped('')]);
        }

        $card->append($table->build());
        return Response::html($this->view->render($card, ['title' => View::t('tab.alliases')]));
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
        $breadcrumbs->append(Builder::a('/admin/?p=allias&domain=' . urlencode($domain))->content(View::t('domain.bind_back')));
        $card->append($breadcrumbs);

        $card->append(Builder::h(1)->content(View::t('allias.new_title') . ': ' . View::esc($domain)));
        $card->append(View::domainTabs($domain, 'allias'));

        if ($error !== '') {
            $card->append(Message::error($error)->build());
        }

        $form = Form::create('/admin/?p=allias:new&domain=' . urlencode($domain), 'POST')
            ->csrf($this->csrf->field())
            ->add(Field::text('name')->label(View::t('allias.name_label'))->placeholder('admin, api, mobile')->required()->value($nameVal))
            ->submit(View::t('domain.new_submit'));

        $card->append($form->build());

        return Response::html($this->view->render($card, ['title' => View::t('allias.new_title')]));
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

        // Получаем список скинов домена (для чекбоксов и селекта)
        $skinsMap = $this->siteConfig->getByKey(['domains_skins_allowed', $domain], ['default']);
        $skinOptions = [];
        if (is_array($skinsMap)) {
            foreach ($skinsMap as $s) {
                $skinOptions[(string)$s] = (string)$s;
            }
        }
        if ($skinOptions === []) {
            $skinOptions = ['default' => 'default'];
        }

        // 1. ФИКС: Берем success из GET-параметра (PRG)
        $success = $request->query('saved') ? View::t('msg.saved') : '';

        if ($request->isPost()) {
            $csrfErr = $this->requireCsrf($request);
            if ($csrfErr !== null) {
                return $csrfErr;
            }

            $data = $this->siteConfig->all();

            // Основные настройки
            $data['alliases_titles'][$domain][$allias] = (string) ($request->post['title'] ?? '');
            $data['alliases_available'][$domain][$allias] = isset($request->post['available']);
            $data['alliases_stubs'][$domain][$allias] = (string) ($request->post['stub'] ?? 'maintenance');

            $until = (string) ($request->post['until'] ?? '');
            if ($until !== '') {
                $data['alliases_stub_until'][$domain][$allias] = strtotime($until);
            } else {
                unset($data['alliases_stub_until'][$domain][$allias]);
            }

            // Скины
            $data['alliases_skins'][$domain][$allias] = (string) ($request->post['active_skin'] ?? 'default');
            $skinsAllowed = [];
            foreach (array_keys($skinOptions) as $s) {
                if (isset($request->post['skin_' . $s])) {
                    $skinsAllowed[] = $s;
                }
            }
            $data['alliases_skins_allowed'][$domain][$allias] = $skinsAllowed;

            // Языки
            $langs = [];
            if (isset($request->post['lang_ru'])) {
                $langs[] = 'ru';
            }
            if (isset($request->post['lang_en'])) {
                $langs[] = 'en';
            }
            if (isset($request->post['lang_zh'])) {
                $langs[] = 'zh';
            }
            $data['alliases_langs'][$domain][$allias] = $langs;

            Fs::writeAtomic(
                $this->paths->siteConfig(),
                json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n",
            );

            // 2. ФИКС: Редирект после сохранения (PRG), чтобы обновить данные и убить повторный POST
            return $this->redirect('/admin/?p=allias:edit&domain=' . urlencode($domain) . '&allias=' . urlencode($allias) . '&saved=1');
        }

        // --- РЕНДЕР ФОРМЫ (GET) ---
        $title = (string) $this->siteConfig->getByKey(['alliases_titles', $domain, $allias], '');
        $available = (bool) $this->siteConfig->getByKey(['alliases_available', $domain, $allias], true);

        $defaultStub = (string) $this->siteConfig->getByKey(['domains_stub', $domain, 'current'], 'maintenance');
        $stub = (string) $this->siteConfig->getByKey(['alliases_stubs', $domain, $allias], $defaultStub);

        $until = $this->siteConfig->getByKey(['alliases_stub_until', $domain, $allias], 0);
        $untilStr = $until > 0 ? date('Y-m-d\TH:i', (int)$until) : '';

        $currentSkin = (string) $this->siteConfig->getByKey(['alliases_skins', $domain, $allias], 'default');
        $currentSkinsAllowed = $this->siteConfig->getByKey(['alliases_skins_allowed', $domain, $allias], ['default']);
        if (!is_array($currentSkinsAllowed)) {
            $currentSkinsAllowed = ['default'];
        }

        $currentLangs = $this->siteConfig->getByKey(['alliases_langs', $domain, $allias], ['ru']);
        if (!is_array($currentLangs)) {
            $currentLangs = ['ru'];
        }

        $card = Builder::div('admin-card');

        $breadcrumbs = Builder::div('breadcrumbs');
        $breadcrumbs->append(Builder::a('/admin/?p=domain')->content(View::t('domain.bread_domains')));
        $breadcrumbs->append(Text::raw(' / '));
        $breadcrumbs->append(Builder::a('/admin/?p=domain:edit&domain=' . urlencode($domain))->content($domain));
        $breadcrumbs->append(Text::raw(' / '));
        $breadcrumbs->append(Builder::a('/admin/?p=allias&domain=' . urlencode($domain))->content(View::t('tab.alliases')));
        $card->append($breadcrumbs);

        $card->append(Builder::h(1)->content(View::t('allias.title') . ': ' . View::esc($allias)));
        $card->append(View::alliasTabs($domain, $allias, 'settings'));

        if ($success !== '') {
            $card->append(Message::success($success)->build());
        }

        $card->append(Builder::h(2)->content(View::t('allias.settings')));

        $form = Form::create('/admin/?p=allias:edit&domain=' . urlencode($domain) . '&allias=' . urlencode($allias), 'POST')
            ->csrf($this->csrf->field())
            ->add(Field::text('title')->label(View::t('domain.title_label'))->value($title))
            ->add(Field::checkbox('available')->label(View::t('domain.avail_label'))->checked($available))
            ->add(Field::select('stub')->label(View::t('domain.stub_label'))->options($this->getStubOptions())->selected($stub))
            ->add(Field::datetime('until')->label(View::t('domain.until_label'))->hint(View::t('domain.until_hint'))->value($untilStr))
            ->add(Field::select('active_skin')->label(View::t('allias.settings.active_skin'))->options($skinOptions)->selected($currentSkin));

        foreach ($skinOptions as $code => $label) {
            $form->add(Field::checkbox('skin_' . $code)->label(View::t('allias.settings.allow_skin', ['skin' => $label]))->checked(in_array($code, $currentSkinsAllowed, true)));
        }

        $form->add(Field::checkbox('lang_ru')->label(View::t('allias.settings.lang_ru'))->checked(in_array('ru', $currentLangs, true)))
             ->add(Field::checkbox('lang_en')->label('English (EN)')->checked(in_array('en', $currentLangs, true)))
             ->add(Field::checkbox('lang_zh')->label('中文 (ZH)')->checked(in_array('zh', $currentLangs, true)))
             ->submit(View::t('domain.save'));

        $card->append($form->build());

        $hr = Builder::el('hr');
        $hr->attrs()->styleAdd('margin: 30px 0; border: 0; border-top: 1px solid #eee;');
        $card->append($hr);

        $dangerZone = Builder::div('danger-zone');
        $dangerZone->append(Builder::p()->content(View::t('domain.delete_warn')));
        $dangerZone->append(Builder::a('/admin/?p=allias:remove&domain=' . urlencode($domain) . '&name=' . urlencode($allias), 'btn btn-danger')->content(View::t('domain.delete_btn')));
        $card->append($dangerZone);

        return Response::html($this->view->render($card, ['title' => View::t('allias.title') . ': ' . $allias]));
    }

    /** @return array<string, string> */
    private function getStubOptions(): array
    {
        $dir = $this->paths->root() . '/stubs';
        $files = is_dir($dir) ? glob($dir . '/*.html') : [];
        $options = [];
        if ($files) {
            foreach ($files as $file) {
                $name = basename($file, '.html');
                $options[$name] = $name;
            }
        }
        return $options ?: ['maintenance' => 'maintenance'];
    }
}
