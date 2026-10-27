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
        if ($auth !== null) return $auth;

        $domain = (string) $request->query('domain', '');
        $allias = (string) $request->query('allias', '');
        
        if ($domain === '' || $allias === '') {
            return $this->redirect('/admin/?p=domain');
        }

        $fillers = $this->siteConfig->getByKey(['fillers', $domain, $allias], []);
        
        $card = Builder::div('admin-card');

        // Хлебные крошки
        $breadcrumbs = Builder::div('breadcrumbs');
        $breadcrumbs->append(Builder::a('/admin/?p=domain')->content('Домены'));
        $breadcrumbs->append(Text::raw(' / '));
        $breadcrumbs->append(Builder::a('/admin/?p=domain:edit&domain=' . urlencode($domain))->content($domain));
        $breadcrumbs->append(Text::raw(' / '));
        $breadcrumbs->append(Builder::a('/admin/?p=allias&domain=' . urlencode($domain))->content('Алиасы'));
        $card->append($breadcrumbs);

        // Заголовок и действия
        $header = Builder::div('page-header');
        $header->append(Builder::h(1)->content('Блоки (Fillers): ' . View::esc($allias)));
        
        $actions = Builder::div('page-actions');
        $actions->append(Builder::a('/admin/?p=filler:new&domain=' . urlencode($domain) . '&allias=' . urlencode($allias), 'btn btn-primary')->content('+ Создать filler'));
        $header->append($actions);
        $card->append($header);

        // Табы алиаса
        $card->append(View::alliasTabs($domain, $allias, 'blocks'));

        // Таблица
        $table = (new Table())->headers('Блок', 'Статус', 'Действия');

        if (is_array($fillers) && $fillers !== []) {
            foreach ($fillers as $block => $enabled) {
                $statusHtml = $enabled 
                    ? '<span class="badge" style="background:#dcfce7; color:#166534;">Включен</span>' 
                    : '<span class="badge" style="background:#fee2e2; color:#991b1b;">Выключен</span>';
                
                $toggleAction = $enabled ? '0' : '1';
                $toggleLabel = $enabled ? 'Выключить' : 'Включить';
                
                $toggleUrl = '/admin/?p=filler:toggle&domain=' . urlencode($domain) . '&allias=' . urlencode($allias) . '&block=' . urlencode((string)$block) . '&value=' . $toggleAction;

                $actionsHtml = Builder::div('table-actions');
                $actionsHtml->append(Builder::a($toggleUrl, 'btn btn-outline')->content($toggleLabel));
                
                $table->row([
                    Text::raw('<b>' . View::esc((string)$block) . '</b>'),
                    Text::raw($statusHtml),
                    $actionsHtml
                ]);
            }
        } else {
            $table->row([Text::raw('Filler\'ов пока нет'), Text::raw(''), Text::raw('')]);
        }

        $card->append($table->build());

        return Response::html($this->view->render($card, ['title' => 'Fillers: ' . $allias]));
    }

    public function new(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) return $auth;

        $domain = (string) $request->query('domain', '');
        $allias = (string) $request->query('allias', '');
        if ($domain === '' || $allias === '') return $this->redirect('/admin/?p=domain');

        $error = '';
        $blockVal = '';

        if ($request->isPost()) {
            $csrfErr = $this->requireCsrf($request);
            if ($csrfErr !== null) return $csrfErr;

            $blockVal = (string) ($request->post['block'] ?? '');
            $cmd = new FillerNewCommand($this->paths);
            $res = $cmd->run(['domain' => $domain, 'allias' => $allias, 'block' => $blockVal], new Context(static fn() => null));

            if ($res->ok) {
                return $this->redirect('/admin/?p=filler&domain=' . urlencode($domain) . '&allias=' . urlencode($allias));
            }
            $error = implode('; ', $res->errors);
        }

        $card = Builder::div('admin-card');

        // Хлебные крошки
        $breadcrumbs = Builder::div('breadcrumbs');
        $breadcrumbs->append(Builder::a('/admin/?p=filler&domain=' . urlencode($domain) . '&allias=' . urlencode($allias))->content('← Назад'));
        $card->append($breadcrumbs);

        $card->append(Builder::h(1)->content('Создать filler для ' . View::esc($allias)));
        $card->append(View::alliasTabs($domain, $allias, 'blocks'));

        if ($error !== '') {
            $card->append(Message::error($error)->build());
        }

        $form = Form::create('/admin/?p=filler:new&domain=' . urlencode($domain) . '&allias=' . urlencode($allias), 'POST')
            ->csrf($this->csrf->field())
            ->add(Field::text('block')->label('Имя блока (например, top)')->required()->value($blockVal))
            ->submit('Создать');

        $card->append($form->build());

        return Response::html($this->view->render($card, ['title' => 'Новый filler']));
    }

    public function toggle(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) return $auth;

        $domain = (string) $request->query('domain', '');
        $allias = (string) $request->query('allias', '');
        $block = (string) $request->query('block', '');
        $value = (string) $request->query('value', '1');

        if ($domain !== '' && $allias !== '' && $block !== '') {
            $cmd = new FillerToggleCommand($this->paths);
            $cmd->run(['domain' => $domain, 'allias' => $allias, 'block' => $block, 'value' => $value], new Context(static fn() => null));
        }

        return $this->redirect('/admin/?p=filler&domain=' . urlencode($domain) . '&allias=' . urlencode($allias));
    }
}