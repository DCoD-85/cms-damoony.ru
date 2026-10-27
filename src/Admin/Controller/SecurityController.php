<?php

declare(strict_types=1);

namespace Damoony\Admin\Controller;

use Damoony\Admin\View;
use Damoony\Core\Config;
use Damoony\Files\Fs;
use Damoony\Files\Paths;
use Damoony\Http\Request;
use Damoony\Http\Response;
use Damoony\Ui\Builder;
use Damoony\Ui\Field;
use Damoony\Ui\Form;
use Damoony\Ui\Message;
use Damoony\Ui\Table;
use Damoony\Ui\Text;

final class SecurityController extends BaseController
{
    public function __construct(
        \Damoony\Admin\AuthInterface $auth,
        \Damoony\Security\Csrf $csrf,
        View $view,
        private readonly Paths $paths,
        private readonly Config $engineConfig,
    ) {
        parent::__construct($auth, $csrf, $view);
    }

    public function index(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) {
            return $auth;
        }

        $success = $request->query('saved') ? View::t('msg.saved') : '';

        $card = Builder::div('admin-card');
        $card->append(Builder::h(1)->content(View::t('security.title')));

        if ($success !== '') {
            $card->append(Message::success($success)->build());
        }

        // 1. Сводка (Анализ логов за 24 часа)
        $logFile = $this->paths->root() . '/var/admin_logs.json';
        $logs = is_file($logFile) ? json_decode((string) file_get_contents($logFile), true) : [];
        $fails24h = 0;
        if (is_array($logs)) {
            $yesterday = time() - 86400;
            foreach ($logs as $log) {
                if (($log['status'] ?? '') === 'fail' && strtotime($log['time'] ?? '') > $yesterday) {
                    $fails24h++;
                }
            }
        }

        $card->append(Builder::h(2)->content(View::t('security.summary')));
        $card->append(Builder::p()->content(View::t('security.fails_24h', ['count' => $fails24h])));

        // 2. Активные баны (Rate Limit)
        $card->append(Builder::h(2)->content(View::t('security.bans')));
        $rlDir = $this->paths->root() . '/var/ratelimit';
        $files = is_dir($rlDir) ? glob($rlDir . '/*.json') : [];
        $activeBans = [];

        if ($files) {
            foreach ($files as $file) {
                $data = json_decode((string) file_get_contents($file), true);
                if (is_array($data) && ($data['banned_until'] ?? 0) > time()) {
                    $ip = $data['ip'] ?? 'Unknown (hash)';
                    $activeBans[] = [
                        'ip' => $ip,
                        'file' => basename($file),
                        'until' => date('Y-m-d H:i:s', $data['banned_until']),
                    ];
                }
            }
        }

        if ($activeBans === []) {
            $card->append(Message::info(View::t('security.no_bans'))->build());
        } else {
            $table = (new Table())->headers('IP', View::t('security.until'), View::t('security.actions'));
            foreach ($activeBans as $ban) {
                $btn = Builder::a('/admin/?p=security:unban&file=' . urlencode($ban['file']), 'btn btn-danger')->content(View::t('security.unban'));
                $table->row([
                    Text::raw('<b>' . View::esc($ban['ip']) . '</b>'),
                    Text::raw(View::esc($ban['until'])),
                    $btn,
                ]);
            }
            $card->append($table->build());
        }

        // 3. Настройки Rate Limit
        $card->append(Builder::h(2)->content(View::t('security.settings')));

        $max = (int) $this->engineConfig->getByKey(['rate_limit', 'max'], 120);
        $window = (int) $this->engineConfig->getByKey(['rate_limit', 'window'], 60);
        $ban = (int) $this->engineConfig->getByKey(['rate_limit', 'ban'], 900);

        $form = Form::create('/admin/?p=security:saveSettings', 'POST')
            ->csrf($this->csrf->field())
            ->add(Field::text('max')->label(View::t('security.max'))->value((string)$max)->required())
            ->add(Field::text('window')->label(View::t('security.window'))->value((string)$window)->required())
            ->add(Field::text('ban')->label(View::t('security.ban_time'))->value((string)$ban)->required())
            ->submit(View::t('security.save'));

        $card->append($form->build());

        return Response::html($this->view->render($card, ['title' => View::t('security.title')]));
    }

    public function unban(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) {
            return $auth;
        }

        $file = (string) $request->query('file', '');
        if (preg_match('/^rl_[a-f0-9]{32}\.json$/', $file)) {
            $path = $this->paths->root() . '/var/ratelimit/' . $file;
            if (is_file($path)) {
                unlink($path);
            }
        }
        return $this->redirect('/admin/?p=security&saved=1');
    }

    public function saveSettings(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) {
            return $auth;
        }

        $csrfErr = $this->requireCsrf($request);
        if ($csrfErr !== null) {
            return $csrfErr;
        }

        $file = $this->paths->root() . '/config/engine.config';
        $data = $this->engineConfig->all();

        $data['rate_limit']['max'] = max(1, (int)($request->post['max'] ?? 120));
        $data['rate_limit']['window'] = max(1, (int)($request->post['window'] ?? 60));
        $data['rate_limit']['ban'] = max(1, (int)($request->post['ban'] ?? 900));

        Fs::writeAtomic($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");

        return $this->redirect('/admin/?p=security&saved=1');
    }
}
