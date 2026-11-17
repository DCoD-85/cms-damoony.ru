<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Cli\Support\SessionWorkspace;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\MaintenanceConfig;

/**
 * session:list для схемы domains_app: сканирует app/.sess_*.
 */
final class SessionListCommand implements CommandInterface
{
    public function __construct(
        private readonly Paths $paths,
        private readonly SessionWorkspace $workspace,
        private readonly MaintenanceConfig $maintenance,
    ) {
    }

    public function name(): string
    {
        return 'session:list';
    }

    public function description(): string
    {
        return 'Список активных AIUI-сессий';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $ttl = $this->maintenance->sessionsTtlHours() * 3600;

        $appRoot = $this->paths->appRoot();
        if (!Fs::isDir($appRoot)) {
            $r->data = ['sessions' => []];
            $context->step('Сессий нет', true);

            return $r;
        }

        $sessions = [];
        $now = time();

        foreach (scandir($appRoot) ?: [] as $entry) {
            if (preg_match('/^\.sess_([a-f0-9]{8,32})\.(.+)$/', $entry, $m) !== 1) {
                continue;
            }

            $tab = $m[1];
            $folder = $m[2];
            $meta = $this->workspace->readMeta($appRoot . '/' . $entry);
            if ($meta === null) {
                continue;
            }

            $createdAt = (int) ($meta['created_at'] ?? 0);
            $age = $createdAt > 0 ? $now - $createdAt : 0;

            $sessions[] = [
                'tab_id' => $tab,
                'folder' => $folder,
                'project' => $meta['project'] ?? null,
                'admin_session_id' => $meta['admin_session_id'] ?? '',
                'created_at' => $createdAt,
                'age_seconds' => $age,
                'ttl_remaining' => max(0, $ttl - $age),
            ];

            $r->file('_sess_' . $tab . '.' . $folder);
            $context->step(
                sprintf('%-20s folder: %s, age: %ds', $tab, $folder, $age),
                true,
                $tab,
            );
        }

        usort($sessions, static fn (array $a, array $b): int => $b['created_at'] <=> $a['created_at']);

        $r->data = ['sessions' => $sessions];
        $context->step(sprintf('Всего: %d', count($sessions)), true);

        return $r;
    }
}
