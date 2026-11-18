<?php

declare(strict_types=1);

namespace admin_server\www;

use Damoony\Admin\Page\AdminPage;
use Damoony\Core\Files\Paths;
use Damoony\Core\Http\Response;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Data\Db\Connection;
use Damoony\Diagnostics\StructureChecker;
use Damoony\UI\Builder;
use Damoony\UI\Node;
use Damoony\UI\Region\FillerState;
use Throwable;

final class algoritm_lang_ops extends AdminPage
{
    protected function content(): Node|Response|null
    {
        $session = $this->adminSession();
        if ($session === null || $session['role'] !== 'superadmin') {
            return Response::notFound();
        }

        $lang = $this->context->domain->language;
        $paths = $this->context->service('paths');
        $site = $this->context->service('site_config');
        if (!$paths instanceof Paths || !$site instanceof SiteConfig) {
            return Response::notFound();
        }

        $wrap = Builder::div('admin-box');
        $wrap->append(Builder::h(1)->content('Мониторинг'));
        $wrap->append(Builder::p()->append(
            Builder::a('/' . $lang . '/')->content('← Назад'),
        ));

        $list = Builder::ul('admin-ops');

        $db = $this->context->service('db');
        $dbOk = false;
        if ($db instanceof Connection) {
            try {
                $db->pdo()->query('SELECT 1');
                $dbOk = true;
            } catch (Throwable) {
                $dbOk = false;
            }
        }
        $list->append(Builder::li()->content('БД: ' . ($dbOk ? 'OK' : 'ошибка')));

        $free = @disk_free_space($paths->root());
        $list->append(Builder::li()->content(
            'Диск: ' . ($free === false ? 'н/д' : round((float) $free / 1073741824, 1) . ' GB свободно'),
        ));

        $checker = new StructureChecker($paths, $site, new FillerState($paths));
        $issues = 0;
        foreach ($site->projects() as $key => $project) {
            foreach ($project->aliasNames() as $alias) {
                $issues += count($checker->checkAllias((string) $key, $alias));
            }
        }
        $list->append(Builder::li()->content('Структура: ' . ($issues === 0 ? 'OK' : $issues . ' проблем')));

        $backupAge = null;
        $backups = glob($paths->root() . '/var/backups/*') ?: [];
        if ($backups !== []) {
            usort($backups, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));
            $backupAge = (int) floor((time() - (int) filemtime($backups[0])) / 86400);
        }
        $list->append(Builder::li()->content(
            'Бэкап: ' . ($backupAge === null ? 'нет' : $backupAge . ' дн. назад'),
        ));

        $cron = $this->lastCron($paths);
        $list->append(Builder::li()->content(
            'Cron: ' . ($cron === null ? 'нет данных' : ($cron['task'] ?? '?') . ' / ' . ($cron['ok'] ?? false ? 'ok' : 'fail')),
        ));

        $wrap->append($list);
        $wrap->append(Builder::p()->append(
            Builder::a('/' . $lang . '/ops/')->content('Обновить'),
        ));

        return $wrap;
    }

    /** @return array<string, mixed>|null */
    private function lastCron(Paths $paths): ?array
    {
        $files = glob($paths->root() . '/var/log/cron-*.jsonl') ?: [];
        if ($files === []) {
            return null;
        }
        usort($files, static fn (string $a, string $b): int => strcmp($b, $a));
        $lines = file($files[0], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $last = end($lines);
        if (!is_string($last)) {
            return null;
        }
        $data = json_decode($last, true);

        return is_array($data) ? $data : null;
    }
}
