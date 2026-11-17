<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Core\Settings\SiteConfigWriter;
use RuntimeException;

/**
 * lang:sync для схемы domains_app: языки алиаса = языки проекта.
 */
final class LangSyncCommand implements CommandInterface
{
    public function __construct(
        private readonly Paths $paths,
        private readonly SiteConfig $site,
        private readonly SiteConfigWriter $writer,
    ) {
    }

    public function name(): string
    {
        return 'lang:sync';
    }

    public function description(): string
    {
        return 'Синхронизировать языки алиаса с языками проекта';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $alias = (string) ($args['allias'] ?? '');
        if ($alias === '') {
            $r->error('Укажите --allias');

            return $r;
        }

        try {
            $project = $this->site->resolve((string) ($args['domain'] ?? ''));
        } catch (RuntimeException $e) {
            $r->error($e->getMessage());

            return $r;
        }

        $settings = $project->alias($alias);
        if ($settings === null) {
            $r->error(sprintf('Алиас "%s" не объявлен у проекта "%s"', $alias, $project->key));

            return $r;
        }

        if ($project->langs === []) {
            $r->error(sprintf('У проекта %s нет объявленных языков (сначала lang:add)', $project->key));

            return $r;
        }

        $before = $settings->langs;
        $after = $project->langs;

        if ($context->isDryRun()) {
            $context->step(sprintf('Dry-run: языки %s будут синхронизированы (%s)', $alias, implode(',', $after)));
            $r->data = ['project' => $project->key, 'allias' => $alias, 'before' => $before, 'after' => $after, 'dry_run' => true];

            return $r;
        }

        try {
            $this->writer->setAliasLangs($project->key, $alias, $after);
        } catch (RuntimeException $e) {
            $r->error('Конфиг не обновлён: ' . $e->getMessage());

            return $r;
        }

        $dir = $this->paths->i18nLocal($project->folder, $alias);
        Fs::mkdir($dir);

        foreach ($after as $lang) {
            $file = $dir . '/' . $lang . '.json';
            if (!is_file($file)) {
                Fs::writeAtomic($file, "{}\n");
                $r->file(Fs::rel($file, $this->paths->root()));
            }
        }

        $r->data = ['project' => $project->key, 'allias' => $alias, 'before' => $before, 'after' => $after];
        $context->step(sprintf('Синхронизировано: %d языков', count($after)), true, $alias);

        return $r;
    }
}
