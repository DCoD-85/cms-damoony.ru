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
use Damoony\Ui\Node;

final class ProfileController extends BaseController
{
    public function __construct(
        \Damoony\Admin\AuthInterface $auth,
        \Damoony\Security\Csrf $csrf,
        View $view,
        private readonly Config $adminConfig,
        private readonly string $root,
    ) {
        parent::__construct($auth, $csrf, $view);
    }

    public function index(Request $request): Response
    {
        return $this->renderTab('summary', $request);
    }

    public function logs(Request $request): Response
    {
        return $this->renderTab('logs', $request);
    }

    public function security(Request $request): Response
    {
        $error = '';
        $success = '';

        if ($request->isPost()) {
            $csrfErr = $this->requireCsrf($request);
            if ($csrfErr !== null) {
                return $csrfErr;
            }

            $current = (string) ($request->post['current_password'] ?? '');
            $new = (string) ($request->post['new_password'] ?? '');
            $confirm = (string) ($request->post['confirm_password'] ?? '');

            $hash = (string) $this->adminConfig->get('password_hash', '');

            if (!password_verify($current, $hash)) {
                $error = View::t('profile.sec_err_current');
            } elseif (strlen($new) < 6) {
                $error = View::t('profile.sec_err_length');
            } elseif ($new !== $confirm) {
                $error = View::t('profile.sec_err_match');
            } else {
                $file = $this->root . '/config/admin.config';
                $data = $this->adminConfig->all();
                $data['password_hash'] = password_hash($new, PASSWORD_DEFAULT);
                Fs::writeAtomic($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
                $success = View::t('profile.sec_success');
            }
        }

        $form = Form::create('/admin/?p=profile:security', 'POST')
            ->csrf($this->csrf->field())
            ->add(Field::password('current_password')->label(View::t('profile.sec_current'))->required())
            ->add(Field::password('new_password')->label(View::t('profile.sec_new'))->required())
            ->add(Field::password('confirm_password')->label(View::t('profile.sec_confirm'))->required())
            ->submit(View::t('profile.sec_submit'));

        $content = Builder::div();
        if ($error !== '') {
            $content->append(Message::error($error)->build());
        }
        if ($success !== '') {
            $content->append(Message::success($success)->build());
        }
        $content->append($form->build());

        return $this->renderTab('security', $request, $content);
    }

    private function renderTab(string $active, Request $request, ?Node $customContent = null): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) {
            return $auth;
        }

        $card = Builder::div('admin-card');
        $card->append(Builder::h(1)->content(View::t('profile.title')));

        $grid = Builder::div('profile-grid');

        // Левое меню
        $nav = Builder::div('profile-nav');
        $nav->append(Builder::a('/admin/?p=profile', $active === 'summary' ? 'active' : '')->content(View::t('profile.tab_summary')));
        $nav->append(Builder::a('/admin/?p=profile:logs', $active === 'logs' ? 'active' : '')->content(View::t('profile.tab_logs')));
        $nav->append(Builder::a('/admin/?p=profile:security', $active === 'security' ? 'active' : '')->content(View::t('profile.tab_security')));
        $grid->append($nav);

        // Правая часть
        $contentBox = Builder::div('profile-content');

        if ($customContent !== null) {
            $contentBox->append($customContent);
        } elseif ($active === 'summary') {
            $contentBox->append(Builder::h(2)->content(View::t('profile.tab_summary')));
            $contentBox->append(Builder::p()->content(View::t('profile.login', ['login' => $this->auth->login()])));
            $contentBox->append(Builder::p()->content(View::t('profile.role', ['role' => View::t('profile.role_super')])));
            $contentBox->append(Builder::p()->content(View::t('profile.access', ['access' => View::t('profile.access_full')])));
        } elseif ($active === 'logs') {
            $contentBox->append(Builder::h(2)->content(View::t('profile.tab_logs')));

            $logFile = $this->root . '/var/admin_logs.json';
            $logs = is_file($logFile) ? json_decode((string) file_get_contents($logFile), true) : [];
            if (!is_array($logs)) {
                $logs = [];
            }

            if ($logs === []) {
                $contentBox->append(Message::info(View::t('profile.logs_empty'))->build());
            } else {
                $table = Builder::el('table');
                $table->attrs()->classAdd('log-table');

                $thead = Builder::el('thead');
                $tr = Builder::el('tr');
                $tr->append(Builder::el('th')->content(View::t('profile.th_date')));
                $tr->append(Builder::el('th')->content(View::t('profile.th_ip')));
                $tr->append(Builder::el('th')->content(View::t('profile.th_status')));
                $thead->append($tr);
                $table->append($thead);

                $tbody = Builder::el('tbody');
                foreach (array_reverse(array_slice($logs, -50)) as $log) {
                    $row = Builder::el('tr');
                    $row->append(Builder::el('td')->content((string) ($log['time'] ?? '')));
                    $row->append(Builder::el('td')->content((string) ($log['ip'] ?? '')));

                    $class = ($log['status'] ?? '') === 'success' ? 'log-status-success' : 'log-status-fail';
                    $tdStatus = Builder::el('td')->content((string) ($log['status'] ?? ''));
                    $tdStatus->attrs()->classAdd($class);

                    $row->append($tdStatus);
                    $tbody->append($row);
                }
                $table->append($tbody);
                $contentBox->append($table);
            }
        }

        $grid->append($contentBox);
        $card->append($grid);

        return Response::html($this->view->render($card, ['title' => View::t('profile.title')]));
    }
}
