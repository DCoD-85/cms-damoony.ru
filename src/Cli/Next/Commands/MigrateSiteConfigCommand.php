<?php

declare(strict_types=1);

namespace Damoony\Cli\Next\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Core\Settings\SiteConfigWriter;
use RuntimeException;
use Throwable;

/**
 * site:config:migrate — перенос legacy site.config.old в схему domains_app.
 *
 * Источник: config/site.config.old (domains_real/domains_mirrors/...).
 * Цель: config/site.config. Существующая цель уходит в config/.trash/ (с --force).
 */
final class MigrateSiteConfigCommand implements CommandInterface
{
    public function __construct(
        private readonly Paths $paths,
        private readonly SiteConfigWriter $writer,
    ) {
    }

    public function name(): string
    {
        return 'site:config:migrate';
    }

    public function description(): string
    {
        return 'Перенести site.config.old (domains_real) в схему domains_app';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $source = $this->paths->root() . '/config/site.config.old';
        $target = $this->writer->path();
        $dry = isset($args['dry']);
        $force = isset($args['force']);

        if (!is_file($source)) {
            $r->error('Источник не найден: ' . $source);

            return $r;
        }

        if (is_file($target) && !$force && !$dry) {
            $r->error('Цель уже существует: ' . $target . ' (используйте --force, старый файл уйдёт в карантин)');

            return $r;
        }

        try {
            $legacy = Config::fromFile($source)->all();
            [$migrated, $warnings] = $this->convert($legacy);
        } catch (Throwable $e) {
            $r->error('Миграция невозможна: ' . $e->getMessage());

            return $r;
        }

        foreach ($warnings as $warning) {
            $r->warn($warning);
        }

        $projects = $migrated['domains_app'] ?? [];
        $aliases = $migrated['alliases'] ?? [];

        $context->step(sprintf(
            'Проектов: %d, хостов: %d, алиасов: %d',
            count($projects),
            $this->hostsCount($projects),
            $this->aliasesCount($aliases),
        ));

        if ($dry) {
            $context->step('Dry-run: файл не записан');

            $r->data = [
                'dry_run' => true,
                'projects' => array_keys($projects),
                'preview' => $migrated,
            ];

            return $r;
        }

        if (is_file($target)) {
            $trash = $this->paths->root() . '/config/.trash/site.config_' . time();
            Fs::mkdir(dirname($trash));
            if (!@rename($target, $trash)) {
                $r->error('Не удалось увести старый site.config в карантин');

                return $r;
            }
            $r->file(Fs::rel($trash, $this->paths->root()));
            $context->step('Существующий site.config в карантине', true, Fs::rel($trash, $this->paths->root()));
        }

        try {
            $this->writer->save($migrated);
        } catch (RuntimeException $e) {
            $r->error('Не удалось записать site.config: ' . $e->getMessage());

            return $r;
        }

        // Валидация тем же ридером, которым работает CLI.
        try {
            SiteConfig::fromFile($target);
        } catch (Throwable $e) {
            $r->error('Записанный конфиг не проходит валидацию: ' . $e->getMessage());

            return $r;
        }

        $r->file(Fs::rel($target, $this->paths->root()));

        $r->data = [
            'projects' => array_keys($projects),
            'hosts' => $this->hostsCount($projects),
            'aliases' => $this->aliasesCount($aliases),
            'file' => Fs::rel($target, $this->paths->root()),
        ];

        $context->step('Конфиг мигрирован', true);

        return $r;
    }

    /**
     * @param array<string, mixed> $legacy
     *
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    private function convert(array $legacy): array
    {
        $realMap = $legacy['domains_real'] ?? [];
        if (!is_array($realMap) || $realMap === []) {
            throw new RuntimeException('в источнике нет domains_real');
        }

        $mirrors = is_array($legacy['domains_mirrors'] ?? null) ? $legacy['domains_mirrors'] : [];
        $mirrorsAvailable = is_array($legacy['domains_mirrors_available'] ?? null)
            ? $legacy['domains_mirrors_available']
            : [];
        $aliases = is_array($legacy['alliases'] ?? null) ? $legacy['alliases'] : [];
        $scheme = is_array($legacy['domains_scheme'] ?? null) ? $legacy['domains_scheme'] : [];
        $stub = is_array($legacy['domains_stub'] ?? null) ? $legacy['domains_stub'] : [];
        $langs = is_array($legacy['domains_langs'] ?? null) ? $legacy['domains_langs'] : [];
        $storage = is_array($legacy['domains_aliases_storage'] ?? null) ? $legacy['domains_aliases_storage'] : [];

        $warnings = [];
        $projects = [];
        $newAliases = [];
        $usedHosts = [];

        foreach ($realMap as $real => $namespaceValue) {
            if (!is_string($real) || $real === '') {
                continue;
            }

            // Самоописание (self-mapping) содержит реальный домен, а не namespace.
            $namespace = is_string($namespaceValue) && $namespaceValue !== $real && $namespaceValue !== ''
                ? $namespaceValue
                : str_replace(['.', '-'], '_', $real);

            $projectMirrors = [];
            foreach ($mirrors as $host => $mapped) {
                if (!is_string($host) || $host === '' || $mapped !== $real) {
                    continue;
                }
                if (isset($usedHosts[$host])) {
                    $warnings[] = sprintf('Хост %s пропущен: уже принадлежит проекту %s', $host, $usedHosts[$host]);

                    continue;
                }
                $usedHosts[$host] = $real;
                $projectMirrors[$host] = ['available' => (bool) ($mirrorsAvailable[$host] ?? true)];
            }

            if ($projectMirrors === []) {
                $usedHosts[$real] = $real;
                $projectMirrors[$real] = ['available' => (bool) ($mirrorsAvailable[$real] ?? true)];
            }

            $projectAliases = [];
            foreach ($aliases[$real] ?? [] as $alias => $settings) {
                if (!is_string($alias) || $alias === '') {
                    continue;
                }
                $langsValue = is_array($settings) && is_array($settings['langs'] ?? null)
                    ? array_values(array_filter($settings['langs'], 'is_string'))
                    : [];

                $entry = [
                    'available' => (bool) ($settings['available'] ?? true),
                    'langs' => $langsValue,
                ];
                if (is_array($settings) && is_string($settings['stub'] ?? null) && $settings['stub'] !== '') {
                    $entry['stub'] = $settings['stub'];
                }
                $projectAliases[$alias] = $entry;
            }

            $projectLangs = [];
            if (is_array($langs[$real] ?? null)) {
                $projectLangs = array_values(array_filter($langs[$real], 'is_string'));
            }
            if ($projectLangs === []) {
                // Инвариант alias ⊆ project: подтягиваем языки алиасов.
                foreach ($projectAliases as $entry) {
                    $projectLangs = array_merge($projectLangs, $entry['langs']);
                }
                $projectLangs = array_values(array_unique($projectLangs));
            }

            $project = [
                'folder' => $real,
                'namespace' => $namespace,
                'mirrors' => $projectMirrors,
            ];

            if (is_string($scheme[$real] ?? null) && $scheme[$real] !== '') {
                $project['scheme'] = $scheme[$real];
            }
            $stubCurrent = is_array($stub[$real] ?? null) ? ($stub[$real]['current'] ?? null) : null;
            if (is_string($stubCurrent) && $stubCurrent !== '') {
                $project['stub'] = $stubCurrent;
            }
            if ($projectLangs !== []) {
                $project['langs'] = $projectLangs;
            }
            if (is_array($storage[$real] ?? null)) {
                $projectStorage = [];
                foreach ($storage[$real] as $alias => $options) {
                    if (!is_string($alias) || !is_array($options)) {
                        continue;
                    }
                    $projectStorage[$alias] = [
                        'db' => is_string($options['db'] ?? null) ? $options['db'] : '',
                        'prefix' => is_string($options['prefix'] ?? null) ? $options['prefix'] : '',
                    ];
                }
                if ($projectStorage !== []) {
                    $project['aliases_storage'] = $projectStorage;
                }
            }

            $projects[$real] = $project;
            if ($projectAliases !== []) {
                $newAliases[$real] = $projectAliases;
            }
        }

        foreach ($aliases as $real => $list) {
            if (!is_string($real) || isset($projects[$real])) {
                continue;
            }
            $warnings[] = sprintf('Алиасы для неизвестного проекта %s пропущены', $real);
        }

        return [['domains_app' => $projects, 'alliases' => $newAliases], $warnings];
    }

    /**
     * @param array<string, mixed> $projects
     */
    private function hostsCount(array $projects): int
    {
        $count = 0;
        foreach ($projects as $project) {
            $mirrors = is_array($project['mirrors'] ?? null) ? $project['mirrors'] : [];
            $count += count($mirrors);
        }

        return $count;
    }

    /**
     * @param array<string, mixed> $aliases
     */
    private function aliasesCount(array $aliases): int
    {
        $count = 0;
        foreach ($aliases as $list) {
            $count += is_array($list) ? count($list) : 0;
        }

        return $count;
    }
}
