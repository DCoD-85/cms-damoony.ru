<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\Context;
use Damoony\Cli\Result;

final class SessionStartCommand extends AbstractSessionCommand
{
    public function name(): string
    {
        return 'session:start';
    }

    public function description(): string
    {
        return 'Start AI edit session (copy domain to .sess_{tab}.{domain})';
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

        if (!is_dir($releaseDir)) {
            $r->error("Domain dir not found: {$releaseDir}");
            return $r;
        }

        if (is_dir($sessionDir)) {
            $r->error("Session already exists: {$sessionDir}");
            return $r;
        }

        try {
            $this->copyTree($releaseDir, $sessionDir);
        } catch (\RuntimeException $e) {
            $r->error('Copy failed: ' . $e->getMessage());
            return $r;
        }

        $productionHash = $this->hashProduction($releaseDir);
        $meta = [
            'tab_id' => $tab,
            'domain' => $domain,
            'admin_session_id' => $adminSess,
            'created_at' => time(),
            'production_hash' => $productionHash,
        ];
        $this->writeMeta($sessionDir, $meta);

        $r->data = [
            'tab_id' => $tab,
            'domain' => $domain,
            'session_dir' => $sessionDir,
            'preview_url' => '/admin/preview/' . $tab . '/',
            'production_hash' => $productionHash,
        ];
        $context->step("Session started: {$tab}", true);

        return $r;
    }
}
