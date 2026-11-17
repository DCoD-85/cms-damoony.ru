<?php

declare(strict_types=1);

namespace Damoony\Cli\Next\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Next\Support\Quarantine;
use Damoony\Cli\Result;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use RuntimeException;
use Throwable;

/**
 * block:remove для схемы domains_app: каталог блока целиком в карантин.
 */
final class BlockRemoveCommand implements CommandInterface
{
    private const NAME_RE = '/^[a-z][a-z0-9_]*$/';

    public function __construct(
        private readonly Paths $paths,
        private readonly SiteConfig $site,
        private readonly Quarantine $quarantine,
    ) {
    }

    public function name(): string
    {
        return 'block:remove';
    }

    public function description(): string
    {
        return 'Удалить глобальный блок в карантин (--force)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $name = (string) ($args['name'] ?? '');
        $force = isset($args['force']);

        if (preg_match(self::NAME_RE, $name) !== 1) {
            $r->error('Некорректное имя блока');

            return $r;
        }
        if (!$force) {
            $r->error('Укажите --force (файлы будут перемещены в карантин)');

            return $r;
        }

        try {
            $project = $this->site->resolve((string) ($args['domain'] ?? ''));
        } catch (RuntimeException $e) {
            $r->error($e->getMessage());

            return $r;
        }

        $blockDir = $this->paths->blocksGlobal($project->folder) . '/' . $name;
        if (!Fs::isDir($blockDir)) {
            $r->warn('Блок не найден: ' . $name);
            $context->step('Нечего удалять', true, $name);

            return $r;
        }

        if ($context->isDryRun()) {
            $context->step('Dry-run: блок ' . $name . ' будет удалён в карантин');
            $r->data = [
                'project' => $project->key,
                'block' => $name,
                'dir' => Fs::rel($blockDir, $this->paths->root()),
                'dry_run' => true,
            ];

            return $r;
        }

        try {
            $trash = $this->quarantine->inPlace($blockDir);
        } catch (Throwable $e) {
            $r->error($e->getMessage());

            return $r;
        }

        if ($trash !== null) {
            $r->file(Fs::rel($trash, $this->paths->root()));
        }

        $r->warn('Очистка карантина: php bin/cli.php trash:clear --force --older=7d');
        $r->data = [
            'project' => $project->key,
            'block' => $name,
            'trash_dir' => $trash === null ? null : Fs::rel($trash, $this->paths->root()),
        ];

        $context->step('Блок перемещён в карантин', true, $name);

        return $r;
    }
}
