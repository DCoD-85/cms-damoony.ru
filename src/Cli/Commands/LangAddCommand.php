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
 * lang:add для схемы domains_app.
 *
 * Без --allias: язык проекта (.i18nglobal + domains_app[key].langs).
 * С --allias: язык алиаса; обязан быть объявлен у проекта (инвариант
 * alias ⊆ project проверяется в SiteConfig).
 */
final class LangAddCommand implements CommandInterface
{
    private const LANG_RE = '/^[a-z]{2}$/';

    public function __construct(
        private readonly Paths $paths,
        private readonly SiteConfig $site,
        private readonly SiteConfigWriter $writer,
    ) {
    }

    public function name(): string
    {
        return 'lang:add';
    }

    public function description(): string
    {
        return 'Добавить язык проекта/алиаса (файл + конфиг)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $lang = (string) ($args['lang'] ?? '');
        if (preg_match(self::LANG_RE, $lang) !== 1) {
            $r->error('Некорректный язык (ISO 639-1)');

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
            if ($project->langs !== [] && !in_array($lang, $project->langs, true)) {
                $r->error(sprintf(
                    'Язык %s не объявлен у проекта %s (сначала lang:add --domain=%s --lang=%s)',
                    $lang,
                    $project->key,
                    (string) ($args['domain'] ?? ''),
                    $lang,
                ));

                return $r;
            }

            $dir = $this->paths->i18nLocal($project->folder, $alias);
        } else {
            $dir = $this->paths->i18nGlobal($project->folder);
        }

        if ($context->isDryRun()) {
            $context->step(sprintf('Dry-run: язык %s (%s) будет добавлен', $lang, $alias !== '' ? $alias : 'проект'));
            $r->data = ['project' => $project->key, 'allias' => $alias, 'lang' => $lang, 'dry_run' => true];

            return $r;
        }

        try {
            if ($alias !== '') {
                $langs = $project->aliasLangs($alias);
                if (!in_array($lang, $langs, true)) {
                    $langs[] = $lang;
                    $this->writer->setAliasLangs($project->key, $alias, $langs);
                } else {
                    $r->warn(sprintf('Язык %s уже объявлен для алиаса %s', $lang, $alias));
                }
            } else {
                if (in_array($lang, $project->langs, true)) {
                    $r->warn(sprintf('Язык %s уже объявлен для проекта %s', $lang, $project->key));
                }

                // Инвариант: языки проекта ⊇ языки всех алиасов. Поэтому при первом
                // lang:add подтягиваем языки, уже объявленные на алиасах.
                $merged = $project->langs;
                foreach ($project->aliases() as $aliasSettings) {
                    $merged = array_merge($merged, $aliasSettings->langs);
                }
                $merged[] = $lang;

                $langs = array_values(array_unique($merged));

                if ($langs !== $project->langs) {
                    $this->writer->setProjectLangs($project->key, $langs);
                }
            }
        } catch (RuntimeException $e) {
            $r->error('Конфиг не обновлён: ' . $e->getMessage());

            return $r;
        }

        Fs::mkdir($dir);
        $file = $dir . '/' . $lang . '.json';
        if (!is_file($file)) {
            Fs::writeAtomic($file, "{}\n");
            $r->file(Fs::rel($file, $this->paths->root()));
        }

        $r->data = ['project' => $project->key, 'allias' => $alias, 'lang' => $lang];
        $context->step(sprintf('Язык добавлен: %s', $lang), true, $lang);

        return $r;
    }
}
