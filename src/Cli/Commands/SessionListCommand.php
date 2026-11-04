<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\Context;
use Damoony\Cli\Result;

final class SessionListCommand extends AbstractSessionCommand
{
    public function name(): string
    {
        return 'session:list';
    }

    public function description(): string
    {
        return 'List active AI edit sessions';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $appRoot = $this->paths->appRoot();
        if (!is_dir($appRoot)) {
            $r->data = ['sessions' => []];
            return $r;
        }

        $sessions = [];
        foreach (scandir($appRoot) ?: [] as $entry) {
            if (!preg_match('/^\.sess_([a-f0-9]+)\.(.+)$/', $entry, $m)) {
                continue;
            }
            $tab = $m[1];
            $dom = $m[2];
            $meta = $this->readMeta($appRoot . '/' . $entry);
            if ($meta === null) {
                continue;
            }
            $age = time() - (int) ($meta['created_at'] ?? 0);
            $sessions[] = [
                'tab_id' => $tab,
                'domain' => $dom,
                'admin_session_id' => $meta['admin_session_id'] ?? '',
                'age_seconds' => $age,
                'ttl_remaining' => max(0, 86400 - $age),
            ];
        }

        $r->data = ['sessions' => $sessions];
        $context->step('Found ' . count($sessions) . ' session(s)', true);

        return $r;
    }
}
