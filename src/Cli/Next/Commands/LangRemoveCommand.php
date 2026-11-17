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
use Damoony\Core\Settings\SiteConfigWriter;
use RuntimeException;
use Throwable;

/**
 * lang:remove для схемы domains_app: файл в карантин + чистка конфига.
 */
final class LangRemoveCommand implements CommandInterface
{
    private const LANG_RE = '/^[a-z]{2}$/';

    public function __construct(
        private readonly Paths $paths,
        private readonly SiteConfig $site,
        private readonly SiteConfigWriter $writer,
        private readonly Quarantine $quarantine,
    ) {
    }

    public function name(): string
    {
        return 'lang:remove';
    }

    public function description(): string
    {
        return 'Удалить язык проекта/алиаса в карантин (--force)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $lang = (string) ($args['lang'] ?? '');
        $force = isset($args['force']);

        if (preg_match(self::LANG_RE, $lang) !== 1) {
            $r->error('Некорректный язык (ISO 639-1)');

            return $r;
        }
        if (!$force) {
            $r->error('Для удаления укажите --force (файл будет перемещён в карантин)');

            return $r;
        }

        try {
            $project = $this->site->resolve((string) ($args['domain'] ?? ''));
        } catch (RuntimeException $e) {
            $r->error($e->getMessage());

            return $r;
        }

        $alias = (string) ($args['allias'] ?? '');

        if ($alias !== '') {
            $settings = $project->alias($alias);
            if ($settings === null) {
                $r->error(sprintf('Алиас "%s" не объявлен у проекта "%s"', $alias, $project->key));

                return $r;
            }
            if (count($settings->langs) <= 1 && in_array($lang, $settings->langs, true)) {
                $r->error('Нельзя удалить последний язык алиаса');

                return $r;
            }

            $dir = $this->paths->i18nLocal($project->folder, $alias);
            $next = array_values(array_filter($settings->langs, static fn (string $l): bool => $l !== $lang));
        } else {
            if (count($project->langs) <= 1 && in_array($lang, $project->langs, true)) {
                $r->error('Нельзя удалить последний язык проекта');

                return $r;
            }

            $blocked = [];
            foreach ($project->aliases() as $name => $settings) {
                if (in_array($lang, $settings->langs, true)) {
                    $blocked[] = $name;
                }
            }
            if ($blocked !== []) {
                $r->error(sprintf(
                    'Язык %s используется алиасами (%s). Сначала удалите его из них: lang:remove --allias=...',
                    $lang,
                    implode(', ', $blocked),
                ));

                return $r;
            }

            $dir = $this->paths->i18nGlobal($project->folder);
            $next = array_values(array_filter($project->langs, static fn (string $l): bool => $l !== $lang));
        }

        if ($context->isDryRun()) {
            $context->step(sprintf('Dry-run: язык %s (%s) будет удалён', $lang, $alias !== '' ? $alias : 'проект'));
            $r->data = ['project' => $project->key, 'allias' => $alias, 'lang' => $lang, 'dry_run' => true];

            return $r;
        }

        $file = $dir . '/' . $lang . '.json';
        if (is_file($file)) {
            try {
                $moved = $this->quarantine->files(
                    $this->paths->root() . '/var/trash/lang_' . $project->folder . '_'
                        . ($alias !== '' ? $alias : 'project') . '_trash_' . time(),
                    [$file],
                );
            } catch (Throwable $e) {
                $r->error($e->getMessage());

                return $r;
            }
            foreach ($moved as $path) {
                $r->file(Fs::rel($path, $this->paths->root()));
            }
            $context->step('Файл перемещён в карантин', true, Fs::rel(dirname($moved[0] ?? ''), $this->paths->root()));
        } else {
            $r->warn('Файл не найден — обновлён только конфиг');
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

        $r->data = ['project' => $project->key, 'allias' => $alias, 'lang' => $lang];
        $context->step(sprintf('Язык удалён: %s', $lang), true, $lang);

        return $r;
    }
}
