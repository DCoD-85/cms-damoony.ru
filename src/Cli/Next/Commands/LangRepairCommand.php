<?php

declare(strict_types=1);

namespace Damoony\Cli\Next\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Core\Settings\SiteConfigWriter;
use RuntimeException;

/**
 * lang:repair для схемы domains_app: файлы ↔ конфиг.
 *
 * В новой схеме нет ключей domains_langs/alliases-www, поэтому «repair»
 * сводится к двум операциям: создать недостающие файлы (--fix) и убрать из
 * конфига языки без файлов (--prune). Без флагов — только отчёт.
 */
final class LangRepairCommand implements CommandInterface
{
    public function __construct(
        private readonly Paths $paths,
        private readonly SiteConfig $site,
        private readonly SiteConfigWriter $writer,
    ) {
    }

    public function name(): string
    {
        return 'lang:repair';
    }

    public function description(): string
    {
        return 'Найти drift файлов и конфига языков (--fix / --prune)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        try {
            $project = $this->site->resolve((string) ($args['domain'] ?? ''));
        } catch (RuntimeException $e) {
            $r->error($e->getMessage());

            return $r;
        }

        $alias = (string) ($args['allias'] ?? '');
        $fix = ($args['fix'] ?? '0') === '1';
        $prune = ($args['prune'] ?? '0') === '1';

        if ($alias !== '') {
            $settings = $project->alias($alias);
            if ($settings === null) {
                $r->error(sprintf('Алиас "%s" не объявлен у проекта "%s"', $alias, $project->key));

                return $r;
            }
            $configured = $settings->langs;
        } else {
            $configured = $project->langs;
        }

        $dir = $alias !== ''
            ? $this->paths->i18nLocal($project->folder, $alias)
            : $this->paths->i18nGlobal($project->folder);

        $files = $this->fileLangs($dir);

        $missing = array_values(array_diff($configured, $files));
        $orphanFiles = array_values(array_diff($files, $configured));

        $r->data = [
            'project' => $project->key,
            'allias' => $alias,
            'configured' => $configured,
            'files' => $files,
            'missing_files' => $missing,
            'files_without_config' => $orphanFiles,
            'dry_run' => !$fix && !$prune,
        ];

        foreach ($missing as $lang) {
            $context->step('Нет файла для объявленного языка: ' . $lang, false, $lang);
        }
        foreach ($orphanFiles as $lang) {
            $context->step('Файл без объявленного языка: ' . $lang, true, $lang);
        }

        if ($missing === [] && $orphanFiles === []) {
            $context->step('Drift не обнаружен', true);

            return $r;
        }

        if (!$fix && !$prune) {
            $context->step('Отчёт: используйте --fix (создать файлы) или --prune (убрать из конфига)', true);

            return $r;
        }

        if ($fix && $missing !== []) {
            Fs::mkdir($dir);
            foreach ($missing as $lang) {
                $file = $dir . '/' . $lang . '.json';
                if (!is_file($file)) {
                    Fs::writeAtomic($file, "{}\n");
                    $r->file(Fs::rel($file, $this->paths->root()));
                }
            }
            $context->step(sprintf('Создано файлов: %d', count($missing)), true);
        }

        if ($prune && $orphanFiles !== []) {
            // prune убирает из конфига языки без файлов — это missing, не orphan
            $next = array_values(array_diff($configured, $missing));
            if ($next === []) {
                $r->error('Нельзя убрать все языки из конфига');

                return $r;
            }

            try {
                if ($alias !== '') {
                    $this->writer->setAliasLangs($project->key, $alias, $next);
                } else {
                    $this->writer->setProjectLangs($project->key, $next);
                }
            } catch (RuntimeException $e) {
                $r->error('Конфиг не обновлён: ' . $e->getMessage());

                return $r;
            }

            $context->step(sprintf('Убрано из конфига: %d', count($missing)), true);
        }

        return $r;
    }

    /**
     * @return list<string>
     */
    private function fileLangs(string $dir): array
    {
        if (!Fs::isDir($dir)) {
            return [];
        }

        $langs = [];
        foreach (glob($dir . '/*.json') ?: [] as $file) {
            $lang = basename($file, '.json');
            if (preg_match('/^[a-z]{2}$/', $lang) === 1) {
                $langs[] = $lang;
            }
        }
        sort($langs);

        return $langs;
    }
}
