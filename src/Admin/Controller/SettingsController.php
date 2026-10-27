<?php

declare(strict_types=1);

namespace Damoony\Admin\Controller;

use Damoony\Admin\View;
use Damoony\Core\Config;
use Damoony\Files\Fs;
use Damoony\Http\Request;
use Damoony\Http\Response;
use Damoony\Ui\Builder;
use Damoony\Ui\Field;
use Damoony\Ui\Form;
use Damoony\Ui\Message;

final class SettingsController extends BaseController
{
    public function __construct(
        \Damoony\Admin\AuthInterface $auth,
        \Damoony\Security\Csrf $csrf,
        View $view,
        private readonly Config $siteConfig,
        private readonly string $root,
    ) {
        parent::__construct($auth, $csrf, $view);
    }

    public function index(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) {
            return $auth;
        }

        $file = $this->root . '/config/site.config';
        $error = '';
        $success = $request->query('saved') ? View::t('msg.saved') : '';

        $title = (string) $this->siteConfig->get('title', 'Damoony');
        $lang = (string) $this->siteConfig->get('language', 'ru-ru');

        if ($request->isPost()) {
            $csrfErr = $this->requireCsrf($request);
            if ($csrfErr !== null) {
                return $csrfErr;
            }

            $title = (string) ($request->post['title'] ?? '');
            $lang = (string) ($request->post['language'] ?? 'ru-ru');

            try {
                $data = $this->siteConfig->all();
                $data['title'] = $title;
                $data['language'] = $lang;

                Fs::writeAtomic($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
                return $this->redirect('/admin/?p=settings&saved=1');
            } catch (\Throwable $e) {
                $error = View::t('msg.error', ['msg' => $e->getMessage()]);
            }
        }

        $form = Form::create('/admin/?p=settings', 'POST')
            ->csrf($this->csrf->field())
            ->add(Field::text('title')->label(View::t('settings.name'))->value($title))
            ->add(Field::select('language')->label(View::t('settings.lang'))->options([
                'ru-ru' => 'Русский (ru-ru)',
                'en-en' => 'English (en-en)',
                'ch-ch' => '中文 (ch-ch)',
            ])->selected($lang))
            ->submit(View::t('domain.save'));

        $card = Builder::div('admin-card');
        $card->append(Builder::h(1)->content(View::t('settings.title')));

        if ($success !== '') {
            $card->append(Message::success($success)->build());
        }
        if ($error !== '') {
            $card->append(Message::error($error)->build());
        }

        $card->append($form->build());
        $backBtn = Builder::a('/admin/?p=domain', 'btn btn-outline')->content(View::t('settings.back'));
        $backBtn->attrs()->styleAdd('margin-top: 20px;');
        $card->append($backBtn);

        return Response::html($this->view->render($card, ['title' => View::t('settings.title')]));
    }
}
