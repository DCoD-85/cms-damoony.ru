<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;

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

        // Резолвим realDomain: FS-пути строятся только от него.
        $siteFile = $this->paths->siteConfig();
        if (!is_file($siteFile)) {
            $r->error('site.config не найден');
            return $r;
        }
        $site = Config::fromFile($siteFile)->all();
        $realDomain = (string) ($site['domains_real'][$domain] ?? '');
        if ($realDomain === '') {
            $r->error('Домен не найден в site.config');
            return $r;
        }

        $releaseDir = $this->paths->domain($realDomain);
        $sessionDir = $this->paths->sessionDir($tab, $realDomain);

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

            // Копируем медиа-инфраструктуру (skin/css/js/img) в session-зону,
            // чтобы правки skin:edit не затрагивали production.
            $projectRoot = rtrim($this->paths->root(), '/');
            $mediaSource = $projectRoot . '/www/media/' . $realDomain;
            $mediaDest = $projectRoot . '/www/media/.sess_' . $tab . '.' . $realDomain;
            if (is_dir($mediaSource)) {
                $this->copyTree($mediaSource, $mediaDest);
            }
        } catch (\RuntimeException $e) {
            $r->error('Copy failed: ' . $e->getMessage());
            return $r;
        }

        $productionHash = $this->hashProduction($releaseDir);
        $meta = [
            'tab_id' => $tab,
            'domain' => $domain,           // input alias (UI-facing)
            'real_domain' => $realDomain,  // FS-facing (для apply/discard)
            'admin_session_id' => $adminSess,
            'created_at' => time(),
            'production_hash' => $productionHash,
            'permissions' => [
                'page:write' => true,
                'block:write' => true,
                'filler:write' => true,
                'skin:edit' => false,
                'scheme:save' => false,
                'structure:check' => true,
                'net:fetch' => true,
            ],
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
