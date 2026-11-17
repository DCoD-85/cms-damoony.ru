<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Cli\Support\Quarantine;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use RuntimeException;
use Throwable;

/**
 * scheme:remove для схемы domains_app: 3 файла схемы в карантин.
 */
final class SchemeRemoveCommand implements CommandInterface
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
        return 'scheme:remove';
    }

    public function description(): string
    {
        return 'Удалить схему в карантин (--force)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $name = (string) ($args['name'] ?? '');
        $force = isset($args['force']);

        if (preg_match(self::NAME_RE, $name) !== 1) {
            $r->error('Некорректное имя схемы');

            return $r;
        }
        if (!$force) {
            $r->error('Укажите --force (destructive-операция). Перед запуском сделайте git commit.');

            return $r;
        }

        try {
            $project = $this->site->resolve((string) ($args['domain'] ?? ''));
        } catch (RuntimeException $e) {
            $r->error($e->getMessage());

            return $r;
        }

        $schemesDir = $this->paths->schemesGlobal($project->folder);
        $files = [
            $schemesDir . '/' . $name . '.php',
            $schemesDir . '/' . $name . '.rules.json',
            $schemesDir . '/' . $name . '.editor.json',
        ];

        $existing = array_values(array_filter($files, 'is_file'));
        if ($existing === []) {
            $r->warn('Схема не найдена: ' . $name);
            $context->step('Нечего удалять', true, $name);

            return $r;
        }

        $trashDir = $schemesDir . '/' . $name . '_trash_' . time();

        if ($context->isDryRun()) {
            $context->step('Dry-run: схема ' . $name . ' будет удалена в карантин');
            $r->data = [
                'project' => $project->key,
                'scheme' => $name,
                'files' => array_map('basename', $existing),
                'trash_dir' => Fs::rel($trashDir, $this->paths->root()),
                'dry_run' => true,
            ];

            return $r;
        }

        try {
            $moved = $this->quarantine->files($trashDir, $existing);
        } catch (Throwable $e) {
            $r->error($e->getMessage());

            return $r;
        }

        foreach ($moved as $path) {
            $r->file(Fs::rel($path, $this->paths->root()));
        }

        $r->warn('Очистка карантина: php bin/cli.php trash:clear --force --older=7d');
        $r->data = [
            'project' => $project->key,
            'scheme' => $name,
            'quarantined_files' => array_map('basename', $moved),
            'trash_dir' => Fs::rel($trashDir, $this->paths->root()),
        ];

        $context->step('Схема перемещена в карантин', true, Fs::rel($trashDir, $this->paths->root()));

        return $r;
    }
}
