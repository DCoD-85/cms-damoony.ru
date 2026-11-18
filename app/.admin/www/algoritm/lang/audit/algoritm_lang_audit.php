<?php

declare(strict_types=1);

namespace admin_server\www;

use Damoony\Admin\Page\AdminPage;
use Damoony\Core\Http\Response;
use Damoony\Data\Admin\AdminAuthService;
use Damoony\UI\Builder;
use Damoony\UI\Node;

final class algoritm_lang_audit extends AdminPage
{
    protected function content(): Node|Response|null
    {
        $session = $this->adminSession();
        $auth = $this->context->service('admin_auth');
        if ($session === null || $session['role'] !== 'superadmin' || !$auth instanceof AdminAuthService) {
            return Response::notFound();
        }

        $wrap = Builder::div('admin-box');
        $wrap->append(Builder::h(1)->content('Аудит'));

        $rows = $auth->auditLog(200);
        if ($rows === []) {
            $wrap->append(Builder::p()->content('Записей пока нет.'));

            return $wrap;
        }

        $table = Builder::el('table');
        $table->attrs()->classAdd('admin-audit');
        $head = Builder::el('tr');
        foreach (['Время', 'Аккаунт', 'Действие', 'Объект', 'IP'] as $title) {
            $head->append(Builder::el('th')->content($title));
        }
        $table->append($head);

        foreach ($rows as $row) {
            $tr = Builder::el('tr');
            foreach ([
                (string) ($row['created_at'] ?? ''),
                (string) ($row['login'] ?? '—'),
                (string) ($row['action'] ?? ''),
                (string) ($row['target'] ?? ''),
                (string) ($row['ip'] ?? ''),
            ] as $value) {
                $tr->append(Builder::el('td')->content($value));
            }
            $table->append($tr);
        }

        $wrap->append($table);

        return $wrap;
    }
}
