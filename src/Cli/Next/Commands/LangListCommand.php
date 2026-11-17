<?php

declare(strict_types=1);

namespace Damoony\Cli\Next\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use RuntimeException;

/**
 * lang:list для схемы domains_app.
 *
 * Без --allias — языки проекта (.i18nglobal + domains_app[key].langs),
 * с --allias — языки алиаса (.i18nlocal + alliases[key][alias].langs).
 */
final class LangListCommand implements CommandInterface
{
    private const LANG_RE = '/^[a-z]{2}$/';

    public function __construct(
        private readonly Paths $paths,
        private readonly SiteConfig $site,
    ) {
    }

    public function name(): string
    {
        return 'lang:list';
    }

    public function description(): string
    {
        return 'Список языков проекта/алиаса (файлы + конфиг)';
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
        $withConfig = ($args['with-config'] ?? '0') === '1';

        $settings = null;
        if ($alias !== '') {
            $settings = $project->alias($alias);
            if ($settings === null) {
                $r->error(sprintf('Алиас "%s" не объявлен у проекта "%s"', $alias, $project->key));

                return $r;
            }
        }

        $dir = $alias !== ''
            ? $this->paths->i18nLocal($project->folder, $alias)
            : $this->paths->i18nGlobal($project->folder);

        $fileLangs = $this->fileLangs($dir);
        $configLangs = [];
        if ($withConfig) {
            $configLangs = $settings !== null ? $settings->langs : $project->langs;
        }

        $effective = array_values(array_unique(array_merge($fileLangs, $configLangs)));
        sort($effective);

        $rows = [];
        foreach ($effective as $lang) {
            $hasFile = in_array($lang, $fileLangs, true);
            $inConfig = in_array($lang, $configLangs, true);
            $file = $hasFile ? Fs::rel($dir . '/' . $lang . '.json', $this->paths->root()) : null;

            $rows[] = ['lang' => $lang, 'file' => $file, 'has_file' => $hasFile, 'in_config' => $inConfig];

            if (is_string($file)) {
                $r->file($file);
            }

            $flags = [];
            if ($hasFile) {
                $flags[] = 'file';
            }
            if ($inConfig) {
                $flags[] = 'config';
            }
            $context->step(sprintf('%-4s [%s]', $lang, implode(',', $flags)), true, $lang);
        }

        $r->data = [
            'project' => $project->key,
            'allias' => $alias,
            'with_config' => $withConfig,
            'file_langs' => $fileLangs,
            'config_langs' => $configLangs,
            'effective_langs' => $effective,
            'rows' => $rows,
        ];
        $context->step(sprintf('Всего: %d', count($effective)), true);

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
            if (preg_match(self::LANG_RE, $lang) === 1) {
                $langs[] = $lang;
            }
        }
        sort($langs);

        return $langs;
    }
}
