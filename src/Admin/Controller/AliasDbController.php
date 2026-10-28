<?php

declare(strict_types=1);

namespace Damoony\Admin\Controller;

use Damoony\Admin\View;
use Damoony\Core\Config;
use Damoony\Db\Connection;
use Damoony\Http\Request;
use Damoony\Http\Response;
use Damoony\Ui\Builder;
use Damoony\Ui\Message;
use Damoony\Ui\Text;

final class AliasDbController extends BaseController
{
    public function __construct(
        \Damoony\Admin\AuthInterface $auth,
        \Damoony\Security\Csrf $csrf,
        View $view,
        private readonly Config $engineConfig,
        private readonly Connection $db,
    ) {
        parent::__construct($auth, $csrf, $view);
    }

    public function index(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) {
            return $auth;
        }

        $domain = (string) $request->query('domain', '');
        $allias = (string) $request->query('allias', '');
        if ($domain === '' || $allias === '') {
            return $this->redirect('/admin/?p=domain');
        }

        $success = $request->query('tested') ? View::t('db.test_ok') : '';

        $dbConfig = $this->engineConfig->getByKey(['db'], []);
        $driver = (string)($dbConfig['driver'] ?? 'unknown');
        $dbname = (string)($dbConfig['dbname'] ?? 'unknown');
        $host = (string)($dbConfig['host'] ?? 'localhost');
        $user = (string)($dbConfig['user'] ?? 'root');

        $tableCount = 0;
        $dbSize = '0 MB';
        $isConnected = false;
        $errorMsg = '';

        try {
            $pdo = $this->db->pdo();
            $isConnected = true;

            $stmt = $pdo->query('SHOW TABLES');
            if ($stmt) {
                $tableCount = count($stmt->fetchAll());
            }

            if ($driver === 'mysql') {
                $sizeStmt = $pdo->prepare('SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size FROM information_schema.TABLES WHERE table_schema = :dbname');
                $sizeStmt->execute(['dbname' => $dbname]);
                $size = $sizeStmt->fetchColumn();
                if ($size) {
                    $dbSize = $size . ' MB';
                }
            }
        } catch (\Throwable $e) {
            $errorMsg = $e->getMessage();
        }

        $card = Builder::div('admin-card');

        $breadcrumbs = Builder::div('breadcrumbs');
        $breadcrumbs->append(Builder::a('/admin/?p=domain')->content(View::t('domain.bread_domains')));
        $breadcrumbs->append(Text::raw(' / '));
        $breadcrumbs->append(Builder::a('/admin/?p=domain:edit&domain=' . urlencode($domain))->content($domain));
        $breadcrumbs->append(Text::raw(' / '));
        $breadcrumbs->append(Builder::a('/admin/?p=allias&domain=' . urlencode($domain))->content(View::t('tab.alliases')));
        $card->append($breadcrumbs);

        $card->append(Builder::h(1)->content(View::t('db.title') . ': ' . View::esc($allias)));
        $card->append(View::alliasTabs($domain, $allias, 'db'));

        if ($success !== '') {
            $card->append(Message::success($success)->build());
        }

        // 1. Статус
        $statusBlock = Builder::div();
        if ($isConnected) {
            $statusBlock->append(Message::success(View::t('db.connected'))->build());
        } else {
            $statusBlock->append(Message::error(View::t('db.error') . ': ' . View::esc($errorMsg))->build());
        }

        if ($isConnected) {
            $statusBlock->append(Builder::p()->content(View::t('db.tables') . ": <b>{$tableCount}</b>"));
            $statusBlock->append(Builder::p()->content(View::t('db.size') . ": <b>{$dbSize}</b>"));
        }

        $card->append($statusBlock);

        // 2. Информация о подключении
        $card->append(Builder::h(2)->content(View::t('db.conn_info')));
        $infoTable = Builder::el('table');
        $infoTable->attrs()->classAdd('table');
        $tbody = Builder::el('tbody');

        $rows = [
            ['db.driver', $driver],
            ['db.host', $host],
            ['db.dbname', $dbname],
            ['db.user', $user],
        ];

        foreach ($rows as $row) {
            $tr = Builder::el('tr');
            $tr->append(Builder::el('td')->content(View::t($row[0])));
            $tr->append(Builder::el('td')->content('<b>' . View::esc($row[1]) . '</b>'));
            $tbody->append($tr);
        }
        $infoTable->append($tbody);
        $card->append($infoTable);

        // 3. Тест
        $card->append(Builder::h(2)->content(View::t('db.test')));
        $card->append(Builder::a('/admin/?p=allias_db&domain=' . urlencode($domain) . '&allias=' . urlencode($allias) . '&tested=1', 'btn btn-primary')->content(View::t('db.test')));

        // 4. Примечание
        $card->append(Builder::h(2)->content(View::t('db.note_title')));
        $card->append(Builder::p()->content(View::t('db.note_text')));

        return Response::html($this->view->render($card, ['title' => View::t('db.title')]));
    }
}
