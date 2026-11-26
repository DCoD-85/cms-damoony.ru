<?php

declare(strict_types=1);

namespace admin_server\www;

use Damoony\Admin\Page\AdminPage;
use Damoony\Core\Http\Response;
use Damoony\Data\Admin\AdminAuthService;
use Damoony\UI\Builder;
use Damoony\UI\Components\Button;
use Damoony\UI\Components\Form;
use Damoony\UI\Components\Input;
use Damoony\UI\Node;

final class algoritm_lang_2fa extends AdminPage
{
    protected function content(): Node|Response|null
    {
        $session = $this->adminSession();
        $auth = $this->context->service('admin_auth');
        if ($session === null || !$auth instanceof AdminAuthService) {
            return Response::notFound();
        }

        $lang = $this->context->domain->language;
        $accountId = (int) $session['account']['id'];
        $message = null;
        $error = null;

        if ($this->isPost() && $this->csrfValid()) {
            $op = (string) $this->context->request->post('op');
            if ($op === 'start') {
                $auth->startTotp($accountId);
            } elseif ($op === 'confirm') {
                if ($auth->confirmTotp($accountId, (string) $this->context->request->post('code'))) {
                    $message = '2FA включена';
                } else {
                    $error = 'Неверный код';
                }
            } elseif ($op === 'disable') {
                $auth->disableTotp($accountId);
                $message = '2FA выключена';
            }
        }

        $state = $auth->totpState($accountId);
        $csrf = $this->csrfToken();
        $wrap = Builder::div('admin-box');
        $wrap->append(Builder::h(1)->content('Двухфакторная аутентификация'));

        if ($message !== null) {
            $wrap->append(Builder::p('admin-toast is-success')->content($message));
        }
        if ($error !== null) {
            $wrap->append(Builder::p('admin-toast is-error')->content($error));
        }

        if ($state['enabled']) {
            $wrap->append(Builder::p('admin-success')->content('2FA включена для аккаунта ' . $session['account']['login']));
            $form = new Form();
            $form->method('POST');
            $form->action('/' . $lang . '/2fa/');
            $form->add(new Input('hidden', '_csrf', $csrf));
            $form->add(new Input('hidden', 'op', 'disable'));
            $form->add(new Button('Выключить 2FA'));
            $wrap->append($form);
        } elseif ($state['secret'] === '') {
            $wrap->append(Builder::p()->content('2FA пока не настроена.'));
            $form = new Form();
            $form->method('POST');
            $form->action('/' . $lang . '/2fa/');
            $form->add(new Input('hidden', '_csrf', $csrf));
            $form->add(new Input('hidden', 'op', 'start'));
            $form->add(new Button('Настроить 2FA'));
            $wrap->append($form);
        } else {
            $wrap->append(Builder::p()->content('Добавьте секрет в приложение-аутентификатор и введите код.'));
            $wrap->append(Builder::p('admin-2fa-secret')->content('Секрет: ' . $state['secret']));
            $wrap->append(Builder::p('admin-2fa-uri')->content('URI: ' . $state['uri']));

            $form = new Form();
            $form->method('POST');
            $form->action('/' . $lang . '/2fa/');
            $form->add(new Input('hidden', '_csrf', $csrf));
            $form->add(new Input('hidden', 'op', 'confirm'));
            $form->add(new Input('text', 'code'));
            $form->add(new Button('Включить 2FA'));
            $wrap->append($form);
        }

        return $wrap;
    }
}
