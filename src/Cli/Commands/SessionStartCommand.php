<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Cli\Support\SessionWorkspace;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use RuntimeException;

/**
 * session:start для схемы domains_app: копия проекта в изолированную сессию.
 */
final class SessionStartCommand implements CommandInterface
{
    public function __construct(
        private readonly Paths $paths,
        private readonly SiteConfig $site,
        private readonly SessionWorkspace $workspace,
    ) {
    }

    public function name(): string
    {
        return 'session:start';
    }

    public function description(): string
    {
        return 'Создать изолированную AIUI-сессию (копия проекта)';
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

        if ($context->isDryRun()) {
            $context->step(sprintf('Dry-run: сессия %s для %s будет создана', $tab, $project->key));
            $r->data = ['tab_id' => $tab, 'project' => $project->key, 'dry_run' => true];

            return $r;
        }

        try {
            $meta = $this->workspace->start($project->folder, $project->key, $tab, $adminSess);
        } catch (RuntimeException $e) {
            $r->error($e->getMessage());

            return $r;
        }

        $sessionDir = $this->workspace->appDir($tab, $project->folder);
        $r->file(Fs::rel($sessionDir, $this->paths->root()));
        $r->file(Fs::rel($sessionDir . '/.session.json', $this->paths->root()));

        $r->data = [
            'tab_id' => $tab,
            'project' => $project->key,
            'session_dir' => Fs::rel($sessionDir, $this->paths->root()),
            'preview_url' => '/admin/preview/' . $tab . '/',
            'production_hash' => $meta['production_hash'] ?? '',
        ];

        $context->step('Сессия создана: ' . $tab, true);

        return $r;
    }
}
