<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\Context;
use Damoony\Cli\Result;

final class SessionApplyCommand extends AbstractSessionCommand
{
    public function name(): string
    {
        return 'session:apply';
    }

    public function description(): string
    {
        return 'Apply session changes to production (optimistic lock via hash)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $domain = $args['domain'] ?? '';
        $tab = $args['tab'] ?? '';
        $adminSess = (string) ($args['admin-sess'] ?? $args['admin_sess'] ?? '');

        try {
            $this->validateDomain($domain);
            $this->validateTab($tab);
        } catch (\RuntimeException $e) {
            $r->error($e->getMessage());
            return $r;
        }

        $releaseDir = $this->paths->domain($domain);
        $sessionDir = $this->paths->sessionDir($tab, $domain);

        if (!is_dir($sessionDir)) {
            $r->error('Session not found');
            return $r;
        }

        $meta = $this->readMeta($sessionDir);
        if ($meta === null) {
            $r->error('Session meta missing');
            return $r;
        }

        if ($adminSess !== '' && (string) ($meta['admin_session_id'] ?? '') !== $adminSess) {
            $r->error('Access denied: admin session mismatch');
            return $r;
        }

        $currentHash = $this->hashProduction($releaseDir);
        $expectedHash = (string) ($meta['production_hash'] ?? '');

        if ($currentHash !== $expectedHash) {
            $r->error(
                'Conflict: production changed since session start. '
                . 'Refresh preview and try again.',
            );
            return $r;
        }

        $backupDir = $this->paths->appRoot() . '/' . $this->trashName($tab, $domain);
        if (!@rename($releaseDir, $backupDir)) {
            $r->error('Cannot move production to trash: ' . $backupDir);
            return $r;
        }

        if (!@rename($sessionDir, $releaseDir)) {
            // откат
            @rename($backupDir, $releaseDir);
            $r->error('Cannot move session to production (rolled back)');
            return $r;
        }

        @unlink($releaseDir . '/.session.json');

        $r->data = [
            'applied' => true,
            'tab_id' => $tab,
            'domain' => $domain,
            'backup' => $backupDir,
        ];
        $context->step("Applied session {$tab} to production", true);

        return $r;
    }
}
