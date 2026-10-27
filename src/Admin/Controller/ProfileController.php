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
use Damoony\Ui\Text;

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

        $formNode = $form->build();

        // Внедряем индикатор надежности после поля new_password
        $indicator = Text::raw('<div class="pwd-strength-bar"><div id="pwd-strength" class="pwd-strength-fill"></div></div>');
        $formNode->append($indicator);

        $content = Builder::div();
        if ($error !== '') {
            $content->append(Message::error($error)->build());
        }
        if ($success !== '') {
            $content->append(Message::success($success)->build());
        }
        $content->append($formNode);

        return $this->renderTab('security', $request, $content);
    }

    public function clearLogs(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) {
            return $auth;
        }

        $logFile = $this->root . '/var/admin_logs.json';
        if (is_file($logFile)) {
            unlink($logFile);
        }
        return $this->redirect('/admin/?p=profile:logs&saved=cleared');
    }

    public function archiveLogs(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) {
            return $auth;
        }

        $logFile = $this->root . '/var/admin_logs.json';
        $archiveDir = $this->root . '/var/archive';

        if (is_file($logFile)) {
            if (!is_dir($archiveDir)) {
                mkdir($archiveDir, 0o775, true);
            }
            $archiveFile = $archiveDir . '/admin_logs_' . date('Y-m-d_H-i-s') . '.json';
            rename($logFile, $archiveFile);
        }
        return $this->redirect('/admin/?p=profile:logs&saved=archived');
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

            // Пагинация
            $page = (int)($request->query('page') ?? 1);
            $perPage = 10;
            $total = count($logs);
            $totalPages = (int) max(1, (int) ceil($total / $perPage));
            $page = (int) max(1, min($page, $totalPages));

            $offset = (int) (($page - 1) * $perPage);
            $logsSlice = array_slice(array_reverse($logs), $offset, $perPage);

            // Кнопки управления
            $actions = Builder::div();
            $actions->attrs()->styleAdd('margin-bottom: 20px; display: flex; gap: 10px;');

            if ($total > 0) {
                $actions->append(Builder::a('/admin/?p=profile:archiveLogs', 'btn btn-outline')->content(View::t('profile.logs_archive')));
                $actions->append(Builder::a('/admin/?p=profile:clearLogs', 'btn btn-danger')->content(View::t('profile.logs_clear')));
            }
            $contentBox->append($actions);

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
                foreach ($logsSlice as $log) {
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

                // Вывод пагинации
                if ($totalPages > 1) {
                    $pagination = Builder::div();
                    $pagination->attrs()->styleAdd('margin-top: 20px; display: flex; gap: 5px;');
                    for ($i = 1; $i <= $totalPages; $i++) {
                        $btn = Builder::a('/admin/?p=profile:logs&page=' . $i, 'btn ' . ($i === $page ? 'btn-primary' : 'btn-outline'))->content((string)$i);
                        $pagination->append($btn);
                    }
                    $contentBox->append($pagination);
                }
            }
        }

        $grid->append($contentBox);
        $card->append($grid);

        return Response::html($this->view->render($card, ['title' => View::t('profile.title')]));
    }
}
