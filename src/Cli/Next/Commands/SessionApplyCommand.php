<?php

declare(strict_types=1);

namespace Damoony\Cli\Next\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Next\Support\SessionWorkspace;
use Damoony\Cli\Result;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use RuntimeException;

/**
 * session:apply для схемы domains_app: сессия становится production.
 *
 * Оптимистичная блокировка: production_hash из .session.json сверяется с текущим.
 */
final class SessionApplyCommand implements CommandInterface
{
    public function __construct(
        private readonly Paths $paths,
        private readonly SiteConfig $site,
        private readonly SessionWorkspace $workspace,
    ) {
    }

    public function name(): string
    {
        return 'session:apply';
    }

    public function description(): string
    {
        return 'Применить сессию к production (optimistic lock по hash)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $tab = trim((string) ($args['tab'] ?? ''));
        $adminSess = (string) ($args['admin-sess'] ?? $args['admin_sess'] ?? '');

        try {
            $this->workspace->assertTab($tab);
        } catch (RuntimeException $e) {
            $r->error($e->getMessage());

            return $r;
        }

        try {
            $project = $this->site->resolve((string) ($args['domain'] ?? ''));
        } catch (RuntimeException $e) {
            $r->error($e->getMessage());

            return $r;
        }

        if ($adminSess === '') {
            $r->error('Доступ запрещён: нет административной сессии (--admin-sess)');

            return $r;
        }

        $releaseDir = $this->paths->domain($project->folder);
        $sessionDir = $this->workspace->appDir($tab, $project->folder);

        if (!Fs::isDir($sessionDir)) {
            $r->error('Сессия не найдена: ' . $tab);

            return $r;
        }

        $meta = $this->workspace->readMeta($sessionDir);
        if ($meta === null) {
            $r->error('Метаданные сессии отсутствуют: .session.json');

            return $r;
        }

        $currentHash = $this->workspace->hashProduction($releaseDir);
        $expectedHash = (string) ($meta['production_hash'] ?? '');

        if ($currentHash !== $expectedHash) {
            $r->error('Конфликт: production изменился после старта сессии. Обновите превью и повторите.');

            return $r;
        }

        if ($context->isDryRun()) {
            $context->step('Dry-run: сессия ' . $tab . ' будет применена к production');
            $r->data = ['tab_id' => $tab, 'project' => $project->key, 'dry_run' => true];

            return $r;
        }

        $backupDir = $this->paths->appRoot() . '/_trash_sess_' . $tab . '_' . $project->folder . '_' . time();

        if (!@rename($releaseDir, $backupDir)) {
            $r->error('Не удалось переместить production в карантин: ' . $backupDir);

            return $r;
        }

        if (!@rename($sessionDir, $releaseDir)) {
            @rename($backupDir, $releaseDir);
            $r->error('Не удалось применить сессию (production восстановлен из карантина)');

            return $r;
        }

        @unlink($releaseDir . '/.session.json');

        // Media сессии не применяем (как и раньше): уводим в карантин.
        $mediaSession = $this->workspace->mediaDir($tab, $project->folder);
        $mediaTrash = null;
        if (Fs::isDir($mediaSession)) {
            $mediaTrash = $this->paths->mediaRoot() . '/.trash/.sess_' . $tab . '.' . $project->folder . '_' . time();
            Fs::mkdir(dirname($mediaTrash));
            @rename($mediaSession, $mediaTrash);
        }

        $r->file(Fs::rel($releaseDir, $this->paths->root()));

        $r->data = [
            'applied' => true,
            'tab_id' => $tab,
            'project' => $project->key,
            'backup' => Fs::rel($backupDir, $this->paths->root()),
            'media_trash' => $mediaTrash === null ? null : Fs::rel($mediaTrash, $this->paths->root()),
        ];

        $r->warn('Резервная копия production: ' . Fs::rel($backupDir, $this->paths->root()));
        $context->step('Сессия применена к production', true);

        return $r;
    }
}
