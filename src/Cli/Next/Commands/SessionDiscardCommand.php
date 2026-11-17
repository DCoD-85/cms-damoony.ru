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
 * session:discard для схемы domains_app: сессия в карантин.
 */
final class SessionDiscardCommand implements CommandInterface
{
    public function __construct(
        private readonly Paths $paths,
        private readonly SiteConfig $site,
        private readonly SessionWorkspace $workspace,
    ) {
    }

    public function name(): string
    {
        return 'session:discard';
    }

    public function description(): string
    {
        return 'Отменить сессию (в карантин)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $tab = trim((string) ($args['tab'] ?? ''));

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

        $sessionDir = $this->workspace->appDir($tab, $project->folder);
        $mediaDir = $this->workspace->mediaDir($tab, $project->folder);

        if (!Fs::isDir($sessionDir) && !Fs::isDir($mediaDir)) {
            $r->warn('Сессия не найдена: ' . $tab);
            $context->step('Нечего отменять', true);

            return $r;
        }

        if ($context->isDryRun()) {
            $context->step('Dry-run: сессия ' . $tab . ' будет отменена');
            $r->data = ['tab_id' => $tab, 'project' => $project->key, 'dry_run' => true];

            return $r;
        }

        $stamp = time();
        $appTrash = null;
        $mediaTrash = null;

        if (Fs::isDir($sessionDir)) {
            $appTrash = $this->paths->appRoot() . '/_trash_sess_' . $tab . '_' . $project->folder . '_' . $stamp;
            if (!@rename($sessionDir, $appTrash)) {
                $r->error('Не удалось переместить сессию в карантин');

                return $r;
            }
            $r->file(Fs::rel($appTrash, $this->paths->root()));
        }

        if (Fs::isDir($mediaDir)) {
            $mediaTrash = $this->paths->mediaRoot() . '/.trash/.sess_' . $tab . '.' . $project->folder . '_' . $stamp;
            Fs::mkdir(dirname($mediaTrash));
            if (!@rename($mediaDir, $mediaTrash)) {
                $r->warn('Не удалось переместить media сессии в карантин');
            } else {
                $r->file(Fs::rel($mediaTrash, $this->paths->root()));
            }
        }

        $r->warn('Очистка карантина: php bin/cli.php trash:clear --force --older=7d');
        $r->data = [
            'tab_id' => $tab,
            'project' => $project->key,
            'app_trash' => $appTrash === null ? null : Fs::rel($appTrash, $this->paths->root()),
            'media_trash' => $mediaTrash === null ? null : Fs::rel($mediaTrash, $this->paths->root()),
        ];

        $context->step('Сессия отменена', true);

        return $r;
    }
}
