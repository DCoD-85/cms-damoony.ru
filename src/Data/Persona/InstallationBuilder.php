<?php

declare(strict_types=1);

namespace Damoony\Data\Persona;

use Damoony\Core\Assembly\AssemblyManifest;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use RuntimeException;
use Throwable;

/**
 * Онлайн-сборка установочного архива под конкретную installation.
 *
 * В архив входит ядро (bin/src/vendor/stubs/www/migrations) и уже собранный
 * проект из сборки (app/{domain}, media, config/site.config). engine.config
 * пишет установщик на клиенте.
 */
final class InstallationBuilder
{
    private const TTL_HOURS = 24;

    private const CORE_EXCLUDE = [
        '.git', '.github', '.gitignore', '.gitattributes',
        'app', 'config', 'var', 'content', 'data',
        '_dev', '_doc', '_dist',
        'www/downloads', 'www/request.php', 'www/metrics.php', 'www/api',
        'CREDENTIALS.txt', 'INSTALL_INSTRUCTION.txt', 'composer.phar',
        '.phpunit.cache', '.php-cs-fixer.cache', '.phpunit.result.cache',
    ];

    public function __construct(
        private readonly Paths $paths,
        private readonly PersonaRepository $repo,
    ) {
    }

    /**
     * @param array<string, mixed> $installation
     *
     * @return array<string, mixed> build-строка
     */
    public function build(array $installation): array
    {
        $installationId = (int) $installation['id'];
        $existing = $this->repo->activeBuild($installationId);
        if ($existing !== [] && is_file($this->filePath((string) $existing['file']))) {
            return $existing;
        }

        $domain = (string) $installation['domain'];
        $assembly = (string) $installation['assembly'];
        $langs = array_values(array_filter(explode(',', (string) $installation['langs'])));

        $manifest = AssemblyManifest::fromDirectory($this->paths, $assembly);

        $aliases = array_values(array_filter(explode(',', (string) ($installation['aliases'] ?? ''))));
        $aliases = array_values(array_intersect($aliases, $manifest->aliases()));
        if ($aliases === []) {
            $aliases = $manifest->aliases();
        }

        if (!in_array($manifest->baseLang(), $langs, true)) {
            $langs[] = $manifest->baseLang();
        }
        $langs = array_values(array_intersect($langs, $manifest->projectLangs()));
        if ($langs === []) {
            $langs = [$manifest->baseLang()];
        }
        // Базовый язык всегда первый: он становится языком по умолчанию.
        $langs = array_values(array_unique(array_merge([$manifest->baseLang()], $langs)));

        $dir = sys_get_temp_dir() . '/damoony_build_' . $installationId . '_' . bin2hex(random_bytes(4));
        Fs::mkdir($dir);

        try {
            $this->copyCore($this->paths->root(), $dir);
            $this->copyAssembly($manifest, $assembly, $dir, $domain, $langs, $aliases);
            $this->writeSiteConfig($dir, $domain, $langs, $manifest, $aliases);

            $buildsDir = $this->paths->root() . '/var/builds';
            Fs::mkdir($buildsDir);
            $version = gmdate('YmdHis');
            $file = 'install-' . $installationId . '-' . $version . '.zip';
            $zipPath = $buildsDir . '/' . $file;

            $this->zip($dir, $zipPath);

            $buildId = $this->repo->createBuild(
                $installationId,
                $version,
                $file,
                hash_file('sha256', $zipPath) ?: '',
                (int) filesize($zipPath),
                gmdate('Y-m-d H:i:s', time() + self::TTL_HOURS * 3600),
            );

            return $this->repo->activeBuild($installationId) ?: [
                'id' => $buildId,
                'installation_id' => $installationId,
                'version' => $version,
                'file' => $file,
                'sha256' => hash_file('sha256', $zipPath) ?: '',
                'size' => (int) filesize($zipPath),
                'expires_at' => gmdate('Y-m-d H:i:s', time() + self::TTL_HOURS * 3600),
            ];
        } finally {
            Fs::rmrf($dir);
        }
    }

    public function filePath(string $file): string
    {
        return $this->paths->root() . '/var/builds/' . basename($file);
    }

    private function copyCore(string $root, string $dst): void
    {
        foreach (scandir($root) ?: [] as $item) {
            if ($item === '.' || $item === '..' || in_array($item, self::CORE_EXCLUDE, true)) {
                continue;
            }

            $src = $root . '/' . $item;
            if (is_dir($src)) {
                $this->copyTree($src, $dst . '/' . $item, null);
            } elseif (is_file($src)) {
                Fs::mkdir(dirname($dst . '/' . $item));
                copy($src, $dst . '/' . $item);
            }
        }
    }

    /**
     * @param list<string> $langs
     * @param list<string> $aliases
     */
    private function copyAssembly(
        AssemblyManifest $manifest,
        string $assembly,
        string $dst,
        string $domain,
        array $langs,
        array $aliases,
    ): void {
        $asm = $this->paths->root() . '/stubs/assemblies/' . $assembly;
        $namespace = str_replace(['.', '-'], '_', $domain);

        $vars = [
            '{{namespace}}' => $namespace,
            '{{domain}}' => $domain,
            '{{real}}' => $domain,
            '{{alias}}' => $aliases[0] ?? 'www',
            '{{timestamp}}' => (string) time(),
        ];

        $appSrc = $asm . '/app';
        foreach (scandir($appSrc) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $src = $appSrc . '/' . $item;
            if (is_dir($src) && in_array($item, $manifest->aliases(), true) && !in_array($item, $aliases, true)) {
                continue;
            }
            $this->copyTree($src, $dst . '/app/' . $domain . '/' . $item, $vars);
        }

        // i18n: только выбранные языки (базовый гарантирован выше).
        $i18nDir = $dst . '/app/' . $domain . '/.i18nglobal';
        foreach (glob($i18nDir . '/*.json') ?: [] as $file) {
            if (!in_array(basename($file, '.json'), $langs, true)) {
                @unlink($file);
            }
        }

        foreach ($aliases as $alias) {
            $src = $asm . '/media/' . $alias;
            if (!is_dir($src)) {
                continue;
            }
            $vars['{{alias}}'] = $alias;
            $this->copyTree($src, $dst . '/www/media/' . $domain . '/' . $alias, $vars);
        }

        $this->copyTree($asm . '/shared', $dst . '/www/media/shared', $vars);
    }

    /**
     * @param list<string> $langs
     * @param list<string> $aliases
     */
    private function writeSiteConfig(string $dst, string $domain, array $langs, AssemblyManifest $manifest, array $aliases): void
    {
        $aliasConfig = [];
        foreach ($aliases as $alias) {
            $aliasLangs = array_values(array_intersect($manifest->aliasLangs($alias), $langs));
            $aliasConfig[$alias] = [
                'available' => true,
                'langs' => $aliasLangs === [] ? $langs : $aliasLangs,
            ];
        }

        $config = [
            'domains_app' => [
                $domain => [
                    'folder' => $domain,
                    'namespace' => str_replace(['.', '-'], '_', $domain),
                    'scheme' => 'www',
                    'stub' => 'maintenance',
                    'default_alias' => $aliases[0] ?? 'www',
                    'langs' => $langs === [] ? $manifest->projectLangs() : $langs,
                    'mirrors' => [$domain => ['available' => true]],
                    'aliases' => $aliasConfig,
                ],
            ],
        ];

        Fs::mkdir($dst . '/config');
        file_put_contents(
            $dst . '/config/site.config',
            json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
        );
    }

    /**
     * @param array<string, string>|null $vars
     */
    private function copyTree(string $src, string $dst, ?array $vars): void
    {
        if (!is_dir($src)) {
            return;
        }
        Fs::mkdir($dst);

        foreach (scandir($src) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $s = $src . '/' . $item;
            $d = $dst . '/' . $item;

            if (is_dir($s)) {
                $this->copyTree($s, $d, $vars);
                continue;
            }

            $content = (string) file_get_contents($s);
            if ($vars !== null) {
                $content = str_replace(array_keys($vars), array_values($vars), $content);
            }
            Fs::mkdir(dirname($d));
            file_put_contents($d, $content);
        }
    }

    private function zip(string $dir, string $zipPath): void
    {
        if (class_exists(\ZipArchive::class)) {
            $zip = new \ZipArchive();
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Не удалось создать архив');
            }
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY,
            );
            foreach ($files as $file) {
                if ($file instanceof \SplFileInfo) {
                    $zip->addFile($file->getPathname(), ltrim(substr($file->getPathname(), strlen($dir)), '/\\'));
                }
            }
            $zip->close();

            return;
        }

        try {
            $phar = new \PharData($zipPath, 0, null, \Phar::ZIP);
            $phar->buildFromDirectory($dir);
            try {
                $phar->compressFiles(\Phar::GZ);
            } catch (Throwable) {
                // ZIP без сжатия — допустимо
            }
            unset($phar);
        } catch (Throwable $e) {
            throw new RuntimeException('Сборка архива недоступна: ' . $e->getMessage(), 0, $e);
        }
    }
}
