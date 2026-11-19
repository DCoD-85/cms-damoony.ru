<?php

declare(strict_types=1);

namespace admin_server\www;

use Damoony\Admin\Page\AdminPage;
use Damoony\Admin\Policy\AdminPolicy;
use Damoony\Core\Http\Response;
use Damoony\Data\Admin\AdminAuthService;
use Damoony\UI\Builder;
use Damoony\UI\Node;

final class algoritm_lang_audit extends AdminPage
{
    private const PER_PAGE = 50;

    protected function content(): Node|Response|null
    {
        $session = $this->adminSession();
        $auth = $this->context->service('admin_auth');
        if ($session === null || !AdminPolicy::allows($session, AdminPolicy::AUDIT_VIEW) || !$auth instanceof AdminAuthService) {
            return Response::notFound();
        }

        $lang = $this->context->domain->language;
        $total = $auth->auditCount();
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = max(1, (int) ($this->context->request->query('page') ?? '1'));
        $page = min($page, $pages);
        $offset = ($page - 1) * self::PER_PAGE;

        $wrap = Builder::div('admin-box');
        $wrap->append(Builder::h(1)->content('Аудит'));
        $wrap->append(Builder::p('audit-summary')->content(
            'Страница ' . $page . ' из ' . $pages . ' · всего записей: ' . $total,
        ));

        if ($total === 0) {
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

        foreach ($auth->auditLog(self::PER_PAGE, $offset) as $row) {
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
        $wrap->append($this->pagination($lang, $page, $pages));

        return $wrap;
    }

    private function pagination(string $lang, int $page, int $pages): Node
    {
        $nav = Builder::div('audit-pagination');
        if ($page > 1) {
            $nav->append(Builder::a('/' . $lang . '/audit/?page=1', 'audit-page')->content('« Первая'));
            $nav->append(Builder::a('/' . $lang . '/audit/?page=' . ($page - 1), 'audit-page')->content('‹ Назад'));
        }
        $nav->append(Builder::span('audit-page is-current')->content('Страница ' . $page . ' / ' . $pages));
        if ($page < $pages) {
            $nav->append(Builder::a('/' . $lang . '/audit/?page=' . ($page + 1), 'audit-page')->content('Вперёд ›'));
            $nav->append(Builder::a('/' . $lang . '/audit/?page=' . $pages, 'audit-page')->content('Последняя »'));
        }

        return $nav;
    }
}
