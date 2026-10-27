<?php

declare(strict_types=1);

namespace Damoony\Admin\Controller;

use Damoony\Admin\AuthInterface;
use Damoony\Admin\View;
use Damoony\Http\Request;
use Damoony\Http\Response;
use Damoony\Security\Csrf;
use Damoony\Ui\Builder;
use Damoony\Ui\Field;
use Damoony\Ui\Form;
use Damoony\Ui\Message;

final class LoginController extends BaseController
{
    public function __construct(
        AuthInterface $auth,
        Csrf $csrf,
        View $view,
    ) {
        parent::__construct($auth, $csrf, $view);
    }

    public function index(Request $request): Response
    {
        if ($this->auth->isLoggedIn()) {
            return $this->redirect('/admin/');
        }

        $error = '';

        if ($request->isPost()) {
            $csrfErr = $this->requireCsrf($request);
            if ($csrfErr !== null) {
                return $csrfErr;
            }

            $login = (string) ($request->post['login'] ?? '');
            $password = (string) ($request->post['password'] ?? '');

            if ($this->auth->attempt($login, $password)) {
                return $this->redirect('/admin/');
            }
            $error = View::t('login.error');
        }

        $form = Form::create('/admin/?p=login', 'POST')
            ->csrf($this->csrf->field())
            ->add(Field::text('login')->label(View::t('login.login'))->required()->value((string)($request->post['login'] ?? '')))
            ->add(Field::password('password')->label(View::t('login.password'))->required())
            ->submit(View::t('login.submit'));

        $card = Builder::div('admin-card');
        $card->append(Builder::h(1)->content(View::t('login.title')));

        if ($error !== '') {
            $card->append(Message::error($error)->build());
        }

        $card->append($form->build());

        return Response::html($this->view->render($card, ['title' => View::t('login.title')]));
    }
}
