<?php
declare(strict_types=1);
namespace {{namespace}}\www;
use Damoony\Core\Http\Response;
use Damoony\Core\Security\Csrf;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Data\Db\Connection;
use Damoony\Data\Db\DataProvider\UserProvider;
use Damoony\UI\Page\Page;
use Damoony\UI\Builder;
use Damoony\UI\Node;
use Damoony\UI\Components\Form;
use Damoony\UI\Components\Input;
use Damoony\UI\Components\Button;

final class algoritm_lang_registration extends Page
{
    protected function content(): Node|Response|null
    {
        $lang = $this->context->domain->language;
        $auth = $this->context->service('auth');
        if ($auth instanceof \Damoony\Data\Auth\AuthInterface && $auth->isAuthenticated()) {
            return Response::redirect('/' . $lang . '/profile/');
        }

        $errors = [];
        $login = '';
        $email = '';

        if ($this->isPost()) {
            $csrf = $this->context->service('csrf');
            if (!$csrf instanceof Csrf || !$csrf->check($_POST)) {
                $errors[] = $this->context->lang('auth.error_csrf');
            }

            $login = strtolower(trim((string) $this->context->request->post('login')));
            $email = trim((string) $this->context->request->post('email'));
            $password = (string) $this->context->request->post('password');
            $password2 = (string) $this->context->request->post('password2');

            if (preg_match('/^[a-z0-9_]{3,32}$/', $login) !== 1) {
                $errors[] = $this->context->lang('auth.register_error_login');
            }
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $errors[] = $this->context->lang('auth.register_error_email');
            }
            if (strlen($password) < 8) {
                $errors[] = $this->context->lang('auth.register_error_password');
            }
            if ($password !== $password2) {
                $errors[] = $this->context->lang('auth.register_error_match');
            }

            $db = $this->context->service('db');
            $siteConfig = $this->context->service('site_config');
            if ($errors === [] && $db instanceof Connection) {
                $project = $siteConfig instanceof SiteConfig
                    ? $siteConfig->project($this->context->domain->real)
                    : null;
                $prefix = $project?->storagePrefix($this->context->domain->allias) ?? '';

                $provider = new UserProvider($db, $prefix, $this->context->domain->real, $this->context->domain->allias);
                if ($provider->exists($login)) {
                    $errors[] = $this->context->lang('auth.register_error_exists');
                } else {
                    $provider->create($login, password_hash($password, PASSWORD_DEFAULT), $email);

                    if ($auth instanceof \Damoony\Data\Auth\AuthInterface && $auth->login($login, $password)) {
                        return Response::redirect('/' . $lang . '/profile/');
                    }
                }
            }
        }

        $form = new Form();
        $form->method('POST');
        $csrf = $this->context->service('csrf');
        if ($csrf instanceof Csrf) {
            $form->csrf($csrf);
        }

        $loginInput = new Input('text', 'login', $login);
        $loginInput->attrs()->add('placeholder', $this->context->lang('auth.login'));
        $form->add($loginInput);

        $emailInput = new Input('email', 'email', $email);
        $emailInput->attrs()->add('placeholder', $this->context->lang('auth.email'));
        $form->add($emailInput);

        $passInput = new Input('password', 'password');
        $passInput->attrs()->add('placeholder', $this->context->lang('auth.password'));
        $form->add($passInput);

        $pass2Input = new Input('password', 'password2');
        $pass2Input->attrs()->add('placeholder', $this->context->lang('auth.password2'));
        $form->add($pass2Input);

        $form->add(new Button($this->context->lang('auth.register_submit')));

        $wrap = Builder::div('auth-box');
        $wrap->append(Builder::h(1)->content($this->context->lang('auth.register_title')));

        if ($errors !== []) {
            $list = Builder::ul('form-errors');
            foreach ($errors as $error) {
                $list->append(Builder::li()->content($error));
            }
            $wrap->append($list);
        }

        $wrap->append($form);
        $wrap->append(Builder::p()->append(
            Builder::a('/' . $lang . '/login/')->content($this->context->lang('auth.register_link_login')),
        ));

        return $wrap;
    }
}
