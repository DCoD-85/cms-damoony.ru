<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Assembly\AssemblyBuilder;
use Damoony\Core\Assembly\AssemblyManifest;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Core\Settings\SiteConfigWriter;
use RuntimeException;
use Throwable;

/**
 * new --assembly=developer — проект из готовой сборки (stubs/assemblies/).
 *
 * Сборка раскладывается в app/{folder}/ и www/media/{folder}/{alias}/ с подстановкой
 * {{domain}}, {{real}}, {{alias}}, {{namespace}}. Конфиг дополняется проектом,
 * миррором и алиасом; языки берутся из assembly.json (langs), с оверрайдами --langs
 * и --default-lang.
 */
final class NewCommand implements CommandInterface
{
    private const ALIAS_RE = '/^[a-z][a-z0-9_]*$/';
    private const LANG_RE = '/^[a-z]{2}$/';

    public function __construct(
        private readonly Paths $paths,
        private readonly SiteConfig $site,
        private readonly SiteConfigWriter $writer,
    ) {
    }

    public function name(): string
    {
        return 'new';
    }

    public function description(): string
    {
        return 'Создать проект из сборки (--assembly=minimal, языки из assembly.json)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $domain = SiteConfig::normalizeHost((string) ($args['domain'] ?? ''));
        $assembly = (string) ($args['assembly'] ?? 'developer');
        $alias = (string) ($args['alias'] ?? 'www');
        $langsArg = trim((string) ($args['langs'] ?? ''));
        $defaultArg = trim((string) ($args['default-lang'] ?? ($args['default_lang'] ?? '')));

        if ($domain === '' || !SiteConfig::isValidHost($domain)) {
            $r->error('Некорректный домен');

            return $r;
        }
        if (preg_match(self::ALIAS_RE, $alias) !== 1) {
            $r->error('Некорректный алиас');

            return $r;
        }

        $existing = $this->site->project($domain);
        $folder = $existing === null ? $domain : $existing->folder;
        $namespace = $existing === null
            ? (string) ($args['namespace'] ?? str_replace(['.', '-'], '_', $domain))
            : $existing->namespace;
        $force = isset($args['force']) && !in_array((string) $args['force'], ['0', 'false'], true);

        try {
            $manifest = AssemblyManifest::fromDirectory($this->paths, $assembly);
        } catch (RuntimeException $e) {
            $r->error($e->getMessage());

            return $r;
        }

        try {
            [$projectLangs, $defaultLang, $aliasLangs] = $this->resolveLangs(
                $manifest,
                $langsArg,
                $defaultArg,
                $alias,
            );
            $this->assertI18n($assembly, $projectLangs, $manifest, $langsArg);
        } catch (RuntimeException $e) {
            $r->error($e->getMessage());

            return $r;
        }

        if ($context->isDryRun()) {
            $context->step(sprintf('Dry-run: проект %s из сборки %s (app/%s)', $domain, $assembly, $folder));
            $r->data = [
                'project' => $domain,
                'folder' => $folder,
                'assembly' => $assembly,
                'alias' => $alias,
                'langs' => $projectLangs,
                'default_lang' => $defaultLang,
                'alias_langs' => $aliasLangs,
                'force' => $force,
                'dry_run' => true,
            ];

            return $r;
        }

        if ($force) {
            $appDir = $this->paths->domain($folder);
            $mediaDir = $this->paths->media($folder, $alias);

            if (is_dir($appDir) && !Fs::rmrf($appDir)) {
                $r->error('Не удалось очистить app/' . $folder);

                return $r;
            }
            if (is_dir($mediaDir) && !Fs::rmrf($mediaDir)) {
                $r->error('Не удалось очистить media/' . $folder . '/' . $alias);

                return $r;
            }

            $context->step('Старые файлы проекта удалены (--force)', true, $domain);
        }

        try {
            new AssemblyBuilder($this->paths)->build($assembly, $domain, [
                'namespace' => $namespace,
                'real' => $folder,
                'alias' => $alias,
            ]);
        } catch (Throwable $e) {
            $r->error('Сборка не применена: ' . $e->getMessage());

            return $r;
        }

        try {
            $this->writer->ensureProject($domain, $folder, $namespace);
            $this->writer->ensureMirror($domain, $domain);
            $this->writer->ensureAlias($domain, $alias, $aliasLangs);
            $this->writer->setProjectLangs($domain, $projectLangs);
            $this->writer->setAliasLangs($domain, $alias, $aliasLangs);
        } catch (RuntimeException $e) {
            $r->error('Конфиг не обновлён: ' . $e->getMessage());

            return $r;
        }

        $r->data = [
            'project' => $domain,
            'folder' => $folder,
            'namespace' => $namespace,
            'assembly' => $assembly,
            'alias' => $alias,
            'langs' => $projectLangs,
            'default_lang' => $defaultLang,
            'alias_langs' => $aliasLangs,
            'app_dir' => Fs::rel($this->paths->domain($folder), $this->paths->root()),
        ];

        $context->step('Проект собран из сборки: ' . $assembly, true, $domain);

        return $r;
    }

    /**
     * @return array{0: list<string>, 1: string, 2: list<string>}
     */
    private function resolveLangs(AssemblyManifest $manifest, string $langsArg, string $defaultArg, string $alias): array
    {
        if ($langsArg !== '') {
            $projectLangs = $this->parseLangs($langsArg);
            $defaultLang = $defaultArg !== '' ? $defaultArg : $manifest->defaultLang();
            if (!in_array($defaultLang, $projectLangs, true)) {
                $defaultLang = $projectLangs[0];
            }

            $ordered = $this->defaultFirst($projectLangs, $defaultLang);

            return [$ordered, $defaultLang, $ordered];
        }

        $projectLangs = $manifest->projectLangs();

        if ($defaultArg !== '' && !in_array($defaultArg, $projectLangs, true)) {
            throw new RuntimeException('--default-lang не входит в языки проекта: ' . $defaultArg);
        }

        $defaultLang = $defaultArg !== '' ? $defaultArg : $manifest->defaultLang();
        $projectLangs = $this->defaultFirst($projectLangs, $defaultLang);
        $aliasLangs = $manifest->aliasLangs($alias);

        if ($defaultArg !== '' && !in_array($defaultLang, $aliasLangs, true)) {
            throw new RuntimeException('--default-lang не входит в языки алиаса ' . $alias . ': ' . $defaultLang);
        }
        if (in_array($defaultLang, $aliasLangs, true)) {
            $aliasLangs = $this->defaultFirst($aliasLangs, $defaultLang);
        }

        return [$projectLangs, $defaultLang, $aliasLangs];
    }

    /**
     * @return list<string>
     */
    private function parseLangs(string $langsArg): array
    {
        $langs = [];
        foreach (explode(',', $langsArg) as $raw) {
            $lang = trim($raw);
            if ($lang === '') {
                continue;
            }
            if (preg_match(self::LANG_RE, $lang) !== 1) {
                throw new RuntimeException('--langs: язык — две строчные буквы, например ru');
            }
            if (in_array($lang, $langs, true)) {
                throw new RuntimeException('--langs: язык повторяется — ' . $lang);
            }
            $langs[] = $lang;
        }

        if ($langs === []) {
            throw new RuntimeException('--langs: нужен хотя бы один язык');
        }

        return $langs;
    }

    /**
     * @param list<string> $langs
     *
     * @return list<string>
     */
    private function defaultFirst(array $langs, string $defaultLang): array
    {
        $ordered = [$defaultLang];
        foreach ($langs as $lang) {
            if ($lang !== $defaultLang) {
                $ordered[] = $lang;
            }
        }

        return $ordered;
    }

    /**
     * @param list<string> $projectLangs
     */
    private function assertI18n(string $assembly, array $projectLangs, AssemblyManifest $manifest, string $langsArg): void
    {
        if (!$manifest->hasLangs() && $langsArg === '') {
            return;
        }

        $dir = $this->paths->root() . '/stubs/assemblies/' . $assembly . '/app/.i18nglobal';
        $missing = [];
        foreach ($projectLangs as $lang) {
            if (!is_file($dir . '/' . $lang . '.json')) {
                $missing[] = '.i18nglobal/' . $lang . '.json';
            }
        }

        if ($missing !== []) {
            throw new RuntimeException('В сборке ' . $assembly . ' нет переводов: ' . implode(', ', $missing));
        }
    }
}
