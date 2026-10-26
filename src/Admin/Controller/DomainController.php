<?php

declare(strict_types=1);

namespace Damoony\Admin\Controller;

use Damoony\Admin\View;
use Damoony\Cli\Commands\DomainNewCommand;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Files\Paths;
use Damoony\Http\Request;
use Damoony\Http\Response;
use Damoony\Ui\Builder;
use Damoony\Ui\Button;
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
        $table = (new Table())->headers('Домен', 'Namespace', 'Статус', 'Файлы', 'Алиасы', 'Действия');

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

            $row = [
                Text::raw('<div style="font-weight:600;">' . View::esc($real) . '</div><div style="font-size:12px; color:#888;">aliases: ' . View::esc(implode(', ', $incomingDomains)) . '</div>'),
                Text::raw(View::esc($ns)),
                Text::raw($statusHtml),
                Text::raw($filesHtml),
                Text::raw($aliasesHtml),
                $deleteBtn
            ];
            $table->row($row);
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
}