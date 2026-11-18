<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Core\Settings\SiteConfigWriter;
use RuntimeException;

/**
 * site:scan — собрать/дополнить site.config по фактическим папкам app/*.
 *
 * Обратная совместимость: старая установка без конфига (или с неполным)
 * сканируется и получает domains_app. По умолчанию — только отчёт,
 * запись — с --write. Существующие значения не перетираются.
 */
final class SiteScanCommand implements CommandInterface
{
    private const SERVICE_DIRS = ['.blocksglobal', '.schemesglobal', '.i18nglobal', '.dbglobal', '.admin'];

    public function __construct(
        private readonly Paths $paths,
        private readonly SiteConfig $site,
        private readonly SiteConfigWriter $writer,
    ) {
    }

    public function name(): string
    {
        return 'site:scan';
    }

    public function description(): string
    {
        return 'Сканировать app/* и собрать domains_app (--write для записи, --json)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();
        $write = isset($args['write']) && !in_array((string) $args['write'], ['0', 'false'], true);

        $scanned = $this->scan();
        if ($scanned === []) {
            $r->warn('В app/ не найдено ни одного проекта');
            $r->data = ['projects' => [], 'written' => false];

            return $r;
        }

        $rows = [];
        foreach ($scanned as $project) {
            $rows[] = $this->describe($project);
        }

        if (!$write || $context->isDryRun()) {
            foreach ($rows as $row) {
                $context->step(sprintf(
                    '[%s] %s (folder: %s, aliases: %s, langs: %s)',
                    $row['action'],
                    $row['project'],
                    $row['folder'],
                    implode(',', array_keys($row['aliases'])),
                    implode(',', $row['langs']),
                ), true, $row['project']);
            }
            $r->data = ['projects' => $rows, 'written' => false];
            if (!$write) {
                $context->step('Отчёт без записи. Для записи: --write', true);
            }

            return $r;
        }

        try {
            $this->apply($scanned);
        } catch (RuntimeException $e) {
            $r->error('site.config не обновлён: ' . $e->getMessage());

            return $r;
        }

        $r->data = ['projects' => $rows, 'written' => true];

        foreach ($rows as $row) {
            $context->step(sprintf(
                '%s %s: алиасы %s, языки %s',
                $row['action'] === 'new' ? '+' : '~',
                $row['project'],
                implode(',', array_keys($row['aliases'])),
                implode(',', $row['langs']),
            ), true, $row['project']);
        }
        $context->step('site.config обновлён: проектов ' . count($rows), true);

        return $r;
    }

    /**
     * @return list<array{key: string, folder: string, namespace: string, aliases: array<string, list<string>>, langs: list<string>, mirrors: list<string>}>
     */
    private function scan(): array
    {
        $appRoot = $this->paths->appRoot();
        if (!is_dir($appRoot)) {
            return [];
        }

        $projects = [];
        foreach (scandir($appRoot) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.') || str_starts_with($entry, '_')) {
                continue;
            }

            $folder = $appRoot . '/' . $entry;
            if (!is_dir($folder)) {
                continue;
            }
            if (str_contains($entry, '_trash_')) {
                continue;
            }
            if (!SiteConfig::isValidHost($entry)) {
                continue;
            }

            $aliases = $this->scanAliases($folder);
            if ($aliases === []) {
                continue;
            }

            $projectLangs = $this->scanLangs($folder . '/.i18nglobal');
            if ($projectLangs === []) {
                $projectLangs = ['ru'];
            }

            foreach ($aliases as $alias => $aliasLangs) {
                $aliases[$alias] = $aliasLangs === [] ? $projectLangs : $aliasLangs;
            }

            $existing = $this->site->project($entry);
            $namespace = $existing !== null ? $existing->namespace : $this->namespaceFor($entry);
            $mirrors = $existing?->hostNames() ?? [$entry];

            $projects[] = [
                'key' => $existing !== null ? $existing->key : $entry,
                'folder' => $existing !== null ? $existing->folder : $entry,
                'namespace' => $namespace,
                'aliases' => $aliases,
                'langs' => $existing !== null && $existing->langs !== [] ? $existing->langs : $projectLangs,
                'mirrors' => $mirrors,
            ];
        }

        return $projects;
    }

    /**
     * @return array<string, list<string>>
     */
    private function scanAliases(string $projectDir): array
    {
        $aliases = [];
        foreach (scandir($projectDir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
                continue;
            }
            if (in_array($entry, self::SERVICE_DIRS, true)) {
                continue;
            }

            $dir = $projectDir . '/' . $entry;
            if (!is_dir($dir) || preg_match('/^[a-z][a-z0-9_]*$/', $entry) !== 1) {
                continue;
            }

            $looksLikeAlias = is_dir($dir . '/algoritm')
                || is_dir($dir . '/.blockslocal')
                || is_dir($dir . '/.schemeslocal')
                || is_dir($dir . '/.i18nlocal');
            if (!$looksLikeAlias) {
                continue;
            }

            $aliases[$entry] = $this->scanLangs($dir . '/.i18nlocal');
        }

        if ($aliases !== []) {
            // www всегда первый: основной алиас (даже если каталога нет)
            $aliases = ['www' => $aliases['www'] ?? []] + $aliases;
        }

        return $aliases;
    }

    /**
     * @return list<string>
     */
    private function scanLangs(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $langs = [];
        foreach (scandir($dir) ?: [] as $file) {
            if (!str_ends_with($file, '.json')) {
                continue;
            }
            $lang = substr($file, 0, -5);
            if (preg_match('/^[a-z]{2}$/', $lang) === 1) {
                $langs[] = $lang;
            }
        }

        sort($langs);
        if (in_array('ru', $langs, true)) {
            $langs = array_values(array_unique(array_merge(['ru'], $langs)));
        }

        return $langs;
    }

    private function namespaceFor(string $host): string
    {
        return str_replace(['.', '-'], '_', strtolower($host));
    }

    /**
     * @param array{key: string, folder: string, namespace: string, aliases: array<string, list<string>>, langs: list<string>, mirrors: list<string>} $project
     *
     * @return array{project: string, folder: string, namespace: string, action: string, aliases: array<string, list<string>>, langs: list<string>, mirrors: list<string>}
     */
    private function describe(array $project): array
    {
        $existing = $this->site->project($project['key']);
        $action = $existing === null ? 'new' : 'exists';

        if ($existing !== null) {
            foreach ($project['aliases'] as $alias => $langs) {
                if (!$existing->hasAlias($alias)) {
                    $action = 'updated';
                }
            }
        }

        return [
            'project' => $project['key'],
            'folder' => $project['folder'],
            'namespace' => $project['namespace'],
            'action' => $action,
            'aliases' => $project['aliases'],
            'langs' => $project['langs'],
            'mirrors' => $project['mirrors'],
        ];
    }

    /**
     * @param list<array{key: string, folder: string, namespace: string, aliases: array<string, list<string>>, langs: list<string>, mirrors: list<string>}> $projects
     */
    private function apply(array $projects): void
    {
        foreach ($projects as $project) {
            $key = $project['key'];
            $existing = $this->site->project($key);

            $this->writer->ensureProject($key, $project['folder'], $project['namespace']);

            foreach ($project['mirrors'] as $mirror) {
                $this->writer->ensureMirror($key, $mirror);
            }

            if ($existing === null || $existing->langs === []) {
                $this->writer->setProjectLangs($key, $project['langs']);
            }

            foreach ($project['aliases'] as $alias => $langs) {
                $aliasSettings = $existing?->alias($alias);
                $aliasLangs = $aliasSettings !== null ? $aliasSettings->langs : [];
                if ($aliasLangs === []) {
                    $aliasLangs = $langs;
                }

                $this->writer->ensureAlias($key, $alias, $aliasLangs);
                $this->writer->setAliasLangs($key, $alias, $aliasLangs);
            }
        }
    }
}
