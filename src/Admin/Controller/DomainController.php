<?php

declare(strict_types=1);

namespace Damoony\Admin\Controller;

use Damoony\Admin\View;
use Damoony\Cli\Commands\DomainNewCommand;
use Damoony\Cli\Context;
use Damoony\Core\Config;
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
        if ($auth !== null) return $auth;

        $domains = $this->siteConfig->getByKey(['domains_real'], []);
        
        $grouped = [];
        if (is_array($domains)) {
            foreach ($domains as $incoming => $real) {
                $grouped[$real][] = $incoming;
            }
        }

        // Заголовок
        $header = Builder::div('page-header');
        $header->append(Builder::h(1)->content('Домены'));
        
        $actions = Builder::div('page-actions');
        $actions->append(Builder::a('/admin/?p=stub', 'btn btn-outline')->content('Библиотека заглушек'));
        $actions->append(Builder::a('/admin/?p=domain:new', 'btn btn-primary')->content('+ Добавить домен'));
        $header->append($actions);

        // Таблица
        $table = (new Table())->headers('Статус', 'Домен', 'Namespace', 'Файлы', 'Алиасы', 'Действия');

        foreach ($grouped as $real => $incomingDomains) {
            $ns = (string) $this->siteConfig->getByKey(['domains_namespace', $real], '-');
            $available = (bool) $this->siteConfig->getByKey(['domains_available', $real], false);
            $hasFiles = is_dir($this->paths->domain($real));

            $aliasesHtml = '';
            $alliases = $this->siteConfig->getByKey(['alliases_real', $real], []);
            if (is_array($alliases)) {
                foreach ($alliases as $a => $mapped) {
                    if ($a === '') continue;
                    $aliasesHtml .= '<span class="badge">' . View::esc((string)$a) . '</span> ';
                }
            }
            $aliasesHtml .= '<span class="badge badge-add">+</span>';

            $statusHtml = $available 
                ? '<div class="status-toggle"></div>' 
                : '<div class="status-toggle" style="background:#e5e7eb;"></div>';
                
            $filesHtml = $hasFiles ? '✓' : '✗';
            
            $deleteBtn = Builder::a('/admin/?p=domain:remove&domain=' . urlencode($real), 'btn btn-danger')->content('Удалить');
            
            // Ссылка на настройки домена
            $domainLink = Builder::a('/admin/?p=domain:edit&domain=' . urlencode($real), 'domain-link')->content(View::esc($real));

            // Формируем строку: Статус -> Домен -> Namespace -> Файлы -> Алиасы -> Действия
            $row = [
                Text::raw($statusHtml),
                Text::raw($domainLink->release() . '<div style="font-size:12px; color:#888; font-weight:normal;">aliases: ' . View::esc(implode(', ', $incomingDomains)) . '</div>'),
                Text::raw(View::esc($ns)),
                Text::raw($filesHtml),
                Text::raw($aliasesHtml),
                $deleteBtn
            ];
            
            // Подсветка строки, если домен включен
            $rowClass = $available ? 'row-active' : '';
            
            $table->row($row, $rowClass);
        }

        $card = Builder::div('admin-card');
        $card->append($table->build());

        $content = Builder::div();
        $content->append($header);
        $content->append($card);

        return Response::html($this->view->render($content, ['title' => 'Домены']));
    }

    public function new(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) return $auth;

        $error = '';
        $domainVal = '';
        $nsVal = '';

        if ($request->isPost()) {
            $csrfErr = $this->requireCsrf($request);
            if ($csrfErr !== null) return $csrfErr;

            $domainVal = (string) ($request->post['domain'] ?? '');
            $nsVal = (string) ($request->post['namespace'] ?? '');

            $cmd = new DomainNewCommand($this->paths);
            $res = $cmd->run(['domain' => $domainVal, 'namespace' => $nsVal], new Context(static fn() => null));

            if ($res->ok) {
                return $this->redirect('/admin/?p=domain');
            }
            $error = implode('; ', $res->errors);
        }

        $form = Form::create('/admin/?p=domain:new', 'POST')
            ->csrf($this->csrf->field())
            ->add(Field::text('domain')->label('Домен')->placeholder('example.ru')->required()->value($domainVal))
            ->add(Field::text('namespace')->label('Namespace (опционально)')->placeholder('example_ru')->value($nsVal))
            ->submit('Создать');

        $card = Builder::div('admin-card');
        $card->append(Builder::h(1)->content('Добавить домен'));
        if ($error !== '') {
            $card->append(Message::error($error)->build());
        }
        $card->append($form->build());
        
        $backBtn = Builder::a('/admin/?p=domain', 'btn btn-outline')->content('Назад');
        $backBtn->attrs()->styleAdd('margin-top:20px;');
        $card->append($backBtn);

        return Response::html($this->view->render($card, ['title' => 'Новый домен']));
    }

   public function edit(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) return $auth;

        $domain = (string) $request->query('domain', '');
        if ($domain === '') return $this->redirect('/admin/?p=domain');

        // Проверяем, существует ли домен
        if ($this->siteConfig->getByKey(['domains_real', $domain]) === null) {
            return $this->redirect('/admin/?p=domain');
        }

        $error = '';
        $success = $request->query('saved') ? 'Настройки сохранены' : '';

        if ($request->isPost()) {
            $csrfErr = $this->requireCsrf($request);
            if ($csrfErr !== null) return $csrfErr;

            $title = (string) ($request->post['title'] ?? '');
            $available = isset($request->post['available']);
            $stub = (string) ($request->post['stub'] ?? 'maintenance');
            $until = (string) ($request->post['until'] ?? '');

            // Обновляем конфиг
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
                \Damoony\Files\Fs::writeAtomic($this->paths->siteConfig(), json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
                return $this->redirect('/admin/?p=domain:edit&domain=' . urlencode($domain) . '&saved=1');
            } catch (\Throwable $e) {
                $error = 'Ошибка сохранения: ' . $e->getMessage();
            }
        }

        // Получаем текущие значения
        $title = (string) $this->siteConfig->getByKey(['domains_titles', $domain], '');
        $available = (bool) $this->siteConfig->getByKey(['domains_available', $domain], false);
        $stub = (string) $this->siteConfig->getByKey(['domains_stub', $domain, 'current'], 'maintenance');
        $until = $this->siteConfig->getByKey(['domains_stub', $domain, 'until'], 0);
        $untilStr = $until > 0 ? date('Y-m-d\TH:i', (int)$until) : '';

        // Строим форму
        $form = Form::create('/admin/?p=domain:edit&domain=' . urlencode($domain), 'POST')
            ->csrf($this->csrf->field())
            ->add(Field::text('title')->label('Заголовок (title)')->value($title))
            ->add(Field::checkbox('available')->label('Сайт включен (если выключить — покажется заглушка)')->checked($available))
            ->add(Field::select('stub')->label('Заглушка при выключении домена')->options([
                'maintenance' => 'Реконструкция',
                'coming_soon' => 'Скоро открытие',
            ])->selected($stub))
            ->add(Field::text('until')->label('Время авто-включения')->value($untilStr)->hint('Если указать время, сайт автоматически включится в этот момент. (Формат: YYYY-MM-DDTHH:MM)'))
            ->submit('Сохранить');

        $card = Builder::div('admin-card');
        
        // Хлебные крошки
        $breadcrumbs = Builder::div('breadcrumbs');
        $breadcrumbs->append(Builder::a('/admin/?p=domain')->content('Домены'));
        $breadcrumbs->append(Text::raw(' / '));
        $breadcrumbs->append(Builder::span()->content($domain));
        
        $card->append($breadcrumbs);
        $card->append(Builder::h(1)->content('Настройки домена'));
        
        // Табы
        $card->append(View::domainTabs($domain, 'settings'));

        if ($success !== '') {
            $card->append(Message::success($success)->build());
        }
        if ($error !== '') {
            $card->append(Message::error($error)->build());
        }
        
        $card->append($form->build());

        return Response::html($this->view->render($card, ['title' => 'Настройки: ' . $domain]));
    }
}