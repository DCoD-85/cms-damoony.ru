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
use Damoony\UI\Components\Select;
use Damoony\UI\Components\Option;
use Damoony\UI\Node;
use RuntimeException;

final class algoritm_lang_accounts extends AdminPage
{
    protected function content(): Node|Response|null
    {
        $session = $this->adminSession();
        $auth = $this->context->service('admin_auth');
        if ($session === null || $session['role'] !== 'superadmin' || !$auth instanceof AdminAuthService) {
            return Response::notFound();
        }

        $lang = $this->context->domain->language;
        $message = null;
        $error = null;

        if ($this->isPost() && $this->csrfValid()) {
            $op = (string) $this->context->request->post('op');

            try {
                if ($op === 'create') {
                    $auth->createAdmin(
                        (string) $this->context->request->post('login'),
                        (string) $this->context->request->post('password'),
                        (string) $this->context->request->post('role'),
                        (string) $this->context->request->post('scope'),
                    );
                    $message = 'Админ создан';
                } elseif ($op === 'reset') {
                    $auth->changePassword(
                        (int) $this->context->request->post('account_id'),
                        (string) $this->context->request->post('password'),
                    );
                    $message = 'Пароль изменён, сессии отозваны';
                }
            } catch (RuntimeException $e) {
                $error = $e->getMessage();
            }
        }

        $csrf = $this->csrfToken();
        $wrap = Builder::div('admin-box');
        $wrap->append(Builder::h(1)->content('Аккаунты'));

        if ($message !== null) {
            $wrap->append(Builder::p('admin-success')->content($message));
        }
        if ($error !== null) {
            $wrap->append(Builder::p('admin-error')->content($error));
        }

        $list = Builder::ul('admin-accounts');
        foreach ($auth->accounts() as $account) {
            $item = Builder::li();
            $item->append(Builder::el('strong')->content((string) $account['login']));
            $item->append(Builder::span()->content(' · ' . (string) $account['status']));

            $roles = [];
            foreach ($auth->membersFor((int) $account['id']) as $member) {
                $roles[] = (string) $member['role'] . ((string) $member['scope'] !== '' ? ':' . (string) $member['scope'] : '');
            }
            $item->append(Builder::div()->content('Роли: ' . ($roles === [] ? '—' : implode(', ', $roles))));

            $reset = new Form('admin-reset');
            $reset->method('POST');
            $reset->action('/' . $lang . '/accounts/');
            $reset->add(new Input('hidden', '_csrf', $csrf));
            $reset->add(new Input('hidden', 'op', 'reset'));
            $reset->add(new Input('hidden', 'account_id', (string) $account['id']));
            $reset->add(new Input('password', 'password'));
            $reset->add(new Button('Сбросить пароль'));
            $item->append($reset);

            $list->append($item);
        }
        $wrap->append($list);

        $wrap->append(Builder::h(2)->content('Новый админ'));
        $form = new Form('admin-create');
        $form->method('POST');
        $form->action('/' . $lang . '/accounts/');
        $form->add(new Input('hidden', '_csrf', $csrf));
        $form->add(new Input('hidden', 'op', 'create'));
        $form->add(new Input('text', 'login'));
        $form->add(new Input('password', 'password'));

        $role = new Select('role');
        $role->add(new Option('superadmin', 'superadmin'));
        $role->add(new Option('domainadmin', 'domainadmin'));
        $form->add($role);
        $form->add(new Input('text', 'scope'));
        $form->add(new Button('Создать'));
        $wrap->append($form);

        return $wrap;
    }
}
