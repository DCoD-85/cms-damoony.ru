<?php

declare(strict_types=1);

namespace Damoony\Core\Settings;

use JsonException;
use RuntimeException;

/**
 * Новый ридер config/site.config (схема domains_app).
 *
 * Заменяет тройку старых ключей domains_real / domains_mirrors /
 * domains_mirrors_available:
 *
 *   старый key                 -> новый путь
 *   domains_real[key]          -> domains_app[key].namespace
 *   (папка = key)              -> domains_app[key].folder
 *   domains_mirrors[host]      -> domains_app[?].mirrors[host]
 *   domains_mirrors_available  -> domains_app[?].mirrors[host].available
 *   alliases[key]              -> alliases[key] (ключ = ключ domains_app)
 *
 * Строгая валидация при загрузке: неизвестный проект в alliases, дубликат
 * хоста между проектами, кривой folder/namespace ловятся сразу, а не в рантайме.
 */
final class SiteConfig
{
    private const NAMESPACE_RE = '/^[a-z][a-z0-9_]*$/';
    private const ALIAS_RE = '/^[a-z][a-z0-9_]*$/';
    private const FOLDER_RE = '/^\.?[a-z0-9][a-z0-9._-]*$/i';
    private const LANG_RE = '/^[a-z]{2}$/';
    private const HOSTNAME_RE = '/^(?=.{1,253}$)[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)+$/i';

    /** @param array<string, ProjectSettings> $projects */
    private function __construct(private readonly array $projects)
    {
    }

    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            throw new RuntimeException("site.config не найден: {$path}");
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException("site.config не читается: {$path}");
        }

        return self::fromJson($raw);
    }

    public static function fromJson(string $json): self
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('site.config: некорректный JSON: ' . $e->getMessage(), 0, $e);
        }

        if (!is_array($data)) {
            throw new RuntimeException('site.config: ожидался JSON-объект');
        }

        /** @var array<string, mixed> $data */
        return self::fromArray($data);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $apps = $data['domains_app'] ?? null;
        if (!is_array($apps)) {
            throw new RuntimeException('site.config: отсутствует domains_app (ожидается схема domains_app)');
        }

        $aliasesAll = $data['alliases'] ?? [];
        if (!is_array($aliasesAll)) {
            throw new RuntimeException('site.config: alliases должен быть объектом');
        }

        /** @var array<string, array<string, AliasSettings>> $declaredAliases */
        $declaredAliases = [];

        /**
         * @var array<string, array{
         *     folder: string,
         *     namespace: string,
         *     scheme: string,
         *     stub: string,
         *     default_alias: string,
         *     langs: list<string>,
         *     aliases_storage: array<string, array{db: string, prefix: string}>,
         *     mirrors: array<string, bool>
         * }> $descriptors
         */
        $descriptors = [];

        foreach ($apps as $key => $spec) {
            if (!is_string($key)) {
                throw new RuntimeException('site.config: ключ domains_app должен быть строкой');
            }
            if (str_starts_with($key, '_')) {
                continue;
            }
            if (!is_array($spec)) {
                throw new RuntimeException("site.config: domains_app.{$key} должен быть объектом");
            }

            $descriptors[$key] = self::parseProject($key, $spec);
        }

        foreach ($aliasesAll as $key => $list) {
            if (!is_string($key)) {
                throw new RuntimeException('site.config: ключ alliases должен быть строкой');
            }
            if (str_starts_with($key, '_')) {
                continue;
            }
            if (!isset($descriptors[$key])) {
                throw new RuntimeException(
                    "site.config: alliases для неизвестного проекта \"{$key}\" — нет такого ключа в domains_app",
                );
            }
            if (!is_array($list)) {
                throw new RuntimeException("site.config: alliases.{$key} должен быть объектом");
            }

            $declaredAliases[$key] = self::parseAliases($key, $list);
        }

        self::assertUniqueHosts($descriptors);
        self::assertAliasLangsWithinProject($descriptors, $declaredAliases);

        $projects = [];
        foreach ($descriptors as $key => $descriptor) {
            $projects[$key] = new ProjectSettings(
                key: $key,
                folder: $descriptor['folder'],
                namespace: $descriptor['namespace'],
                scheme: $descriptor['scheme'],
                stub: $descriptor['stub'],
                defaultAlias: $descriptor['default_alias'],
                langs: $descriptor['langs'],
                aliasesStorage: $descriptor['aliases_storage'],
                mirrors: $descriptor['mirrors'],
                aliases: $declaredAliases[$key] ?? [],
            );
        }

        return new self($projects);
    }

    /** @return list<string> */
    public function projectKeys(): array
    {
        return array_keys($this->projects);
    }

    /** @return array<string, ProjectSettings> */
    public function projects(): array
    {
        return $this->projects;
    }

    public function has(string $key): bool
    {
        return isset($this->projects[$key]);
    }

    public function project(string $key): ?ProjectSettings
    {
        return $this->projects[$key] ?? null;
    }

    public function requireProject(string $key): ProjectSettings
    {
        return $this->projects[$key]
            ?? throw new RuntimeException("site.config: проект \"{$key}\" не найден в domains_app");
    }

    /**
     * Резолв пользовательского ввода (--domain): сначала ключ проекта, затем хост-миррор.
     *
     * @throws UnknownProjectException
     */
    public function resolve(string $hostOrKey): ProjectSettings
    {
        $project = $this->project($hostOrKey) ?? $this->projectByHost($hostOrKey);

        return $project ?? throw UnknownProjectException::forInput($hostOrKey);
    }

    /**
     * Папка проекта по пользовательскому вводу — realDomain для Paths.
     *
     * @throws UnknownProjectException
     */
    public function resolveFolder(string $hostOrKey): string
    {
        return $this->resolve($hostOrKey)->folder;
    }

    /**
     * Проект по входному хосту (точный матч миррора).
     */
    public function projectByHost(string $host): ?ProjectSettings
    {
        $host = self::normalizeHost($host);
        foreach ($this->projects as $project) {
            if ($project->mirrors() !== [] && array_key_exists($host, $project->mirrors())) {
                return $project;
            }
        }

        return null;
    }

    /**
     * Папка проекта по хосту — это realDomain для Paths::domain() / algoritm() / media().
     */
    public function folderByHost(string $host): ?string
    {
        return $this->projectByHost($host)?->folder;
    }

    /**
     * Папка проекта по ключу domains_app.
     */
    public function folder(string $key): ?string
    {
        return ($this->projects[$key] ?? null)?->folder;
    }

    public function namespace(string $key): ?string
    {
        return ($this->projects[$key] ?? null)?->namespace;
    }

    /**
     * @return array<string, string> host => ключ проекта
     */
    public function hostMap(): array
    {
        $map = [];
        foreach ($this->projects as $key => $project) {
            foreach ($project->hostNames() as $host) {
                $map[$host] = $key;
            }
        }

        return $map;
    }

    public function isHostAvailable(string $host): bool
    {
        $host = self::normalizeHost($host);
        $project = $this->projectByHost($host);

        return $project !== null && $project->isHostAvailable($host);
    }

    public static function normalizeHost(string $host): string
    {
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;

        return strtolower(rtrim($host, '.'));
    }

    public static function isValidHost(string $host): bool
    {
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return true;
        }

        return preg_match(self::HOSTNAME_RE, $host) === 1
            && preg_match('/\.[a-z]{2,}$/i', $host) === 1;
    }

    /**
     * @param array<mixed> $spec
     *
     * @return array{
     *     folder: string,
     *     namespace: string,
     *     scheme: string,
     *     stub: string,
     *     default_alias: string,
     *     langs: list<string>,
     *     aliases_storage: array<string, array{db: string, prefix: string}>,
     *     mirrors: array<string, bool>
     * }
     */
    private static function parseProject(string $key, array $spec): array
    {
        $folder = $spec['folder'] ?? null;
        if (!is_string($folder) || $folder === '') {
            throw new RuntimeException("site.config: domains_app.{$key}.folder обязателен");
        }
        if (str_contains($folder, '..') || str_contains($folder, '\\') || str_starts_with($folder, '/')) {
            throw new RuntimeException("site.config: domains_app.{$key}.folder недопустим: {$folder}");
        }
        if (preg_match(self::FOLDER_RE, $folder) !== 1) {
            throw new RuntimeException("site.config: domains_app.{$key}.folder недопустим: {$folder}");
        }

        $namespace = $spec['namespace'] ?? null;
        if (!is_string($namespace) || preg_match(self::NAMESPACE_RE, $namespace) !== 1) {
            throw new RuntimeException("site.config: domains_app.{$key}.namespace недопустим");
        }

        $mirrorsRaw = $spec['mirrors'] ?? [];
        if (!is_array($mirrorsRaw)) {
            throw new RuntimeException("site.config: domains_app.{$key}.mirrors должен быть объектом");
        }

        /** @var array<string, bool> $mirrors */
        $mirrors = [];
        foreach ($mirrorsRaw as $host => $options) {
            if (!is_string($host)) {
                throw new RuntimeException("site.config: domains_app.{$key}.mirrors — ключ должен быть строкой");
            }
            $normalized = self::normalizeHost($host);
            if (!self::isValidHost($normalized)) {
                throw new RuntimeException("site.config: domains_app.{$key}.mirrors — недопустимый хост: {$host}");
            }
            if (!is_array($options)) {
                throw new RuntimeException("site.config: domains_app.{$key}.mirrors.{$host} должен быть объектом");
            }

            $available = $options['available'] ?? true;
            $mirrors[$normalized] = is_bool($available) ? $available : (bool) $available;
        }

        return [
            'folder' => $folder,
            'namespace' => $namespace,
            'scheme' => self::optionalName($spec, 'scheme', 'www', $key),
            'stub' => self::optionalName($spec, 'stub', 'maintenance', $key),
            'default_alias' => self::optionalName($spec, 'default_alias', 'www', $key),
            'langs' => self::optionalLangs($spec, $key),
            'aliases_storage' => self::parseAliasesStorage($key, $spec),
            'mirrors' => $mirrors,
        ];
    }

    /**
     * @param array<mixed> $spec
     */
    private static function optionalName(array $spec, string $field, string $default, string $key): string
    {
        $value = $spec[$field] ?? $default;
        if (!is_string($value) || preg_match(self::ALIAS_RE, $value) !== 1) {
            throw new RuntimeException("site.config: domains_app.{$key}.{$field} недопустим");
        }

        return $value;
    }

    /**
     * @param array<mixed> $spec
     *
     * @return list<string>
     */
    private static function optionalLangs(array $spec, string $key): array
    {
        $raw = $spec['langs'] ?? [];
        if (!is_array($raw)) {
            throw new RuntimeException("site.config: domains_app.{$key}.langs должен быть массивом");
        }

        /** @var list<string> $langs */
        $langs = [];
        foreach ($raw as $lang) {
            if (!is_string($lang) || preg_match(self::LANG_RE, $lang) !== 1) {
                throw new RuntimeException("site.config: domains_app.{$key}.langs — недопустимый код языка");
            }
            $langs[] = $lang;
        }

        return $langs;
    }

    /**
     * @param array<mixed> $spec
     *
     * @return array<string, array{db: string, prefix: string}>
     */
    private static function parseAliasesStorage(string $key, array $spec): array
    {
        $raw = $spec['aliases_storage'] ?? [];
        if (!is_array($raw)) {
            throw new RuntimeException("site.config: domains_app.{$key}.aliases_storage должен быть объектом");
        }

        /** @var array<string, array{db: string, prefix: string}> $storage */
        $storage = [];

        foreach ($raw as $alias => $options) {
            if (!is_string($alias) || preg_match(self::ALIAS_RE, $alias) !== 1) {
                throw new RuntimeException(
                    "site.config: domains_app.{$key}.aliases_storage — недопустимое имя алиаса",
                );
            }
            if (!is_array($options)) {
                throw new RuntimeException(
                    "site.config: domains_app.{$key}.aliases_storage.{$alias} должен быть объектом",
                );
            }

            $db = $options['db'] ?? '';
            $prefix = $options['prefix'] ?? '';

            if (!is_string($db)) {
                throw new RuntimeException(
                    "site.config: domains_app.{$key}.aliases_storage.{$alias}.db должен быть строкой",
                );
            }
            if (!is_string($prefix) || preg_match('/^[a-z0-9_]*$/', $prefix) !== 1) {
                throw new RuntimeException(
                    "site.config: domains_app.{$key}.aliases_storage.{$alias}.prefix недопустим",
                );
            }

            $storage[$alias] = ['db' => $db, 'prefix' => $prefix];
        }

        return $storage;
    }

    /**
     * @param array<mixed> $list
     *
     * @return array<string, AliasSettings>
     */
    private static function parseAliases(string $projectKey, array $list): array
    {
        /** @var array<string, AliasSettings> $aliases */
        $aliases = [];

        foreach ($list as $name => $options) {
            if (!is_string($name) || preg_match(self::ALIAS_RE, $name) !== 1) {
                throw new RuntimeException("site.config: alliases.{$projectKey} — недопустимое имя алиаса");
            }
            if (!is_array($options)) {
                throw new RuntimeException("site.config: alliases.{$projectKey}.{$name} должен быть объектом");
            }

            $available = $options['available'] ?? true;
            $langsRaw = $options['langs'] ?? [];

            if (!is_array($langsRaw)) {
                throw new RuntimeException("site.config: alliases.{$projectKey}.{$name}.langs должен быть массивом");
            }

            /** @var list<string> $langs */
            $langs = [];
            foreach ($langsRaw as $lang) {
                if (!is_string($lang) || preg_match(self::LANG_RE, $lang) !== 1) {
                    throw new RuntimeException("site.config: alliases.{$projectKey}.{$name}.langs — недопустимый код языка");
                }
                $langs[] = $lang;
            }

            $aliases[$name] = new AliasSettings(
                name: $name,
                available: is_bool($available) ? $available : (bool) $available,
                langs: $langs,
                stub: self::optionalAliasStub($projectKey, $name, $options),
            );
        }

        return $aliases;
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function optionalAliasStub(string $projectKey, string $alias, array $options): ?string
    {
        $stub = $options['stub'] ?? null;
        if ($stub === null) {
            return null;
        }
        if (!is_string($stub) || preg_match(self::ALIAS_RE, $stub) !== 1) {
            throw new RuntimeException(
                "site.config: alliases.{$projectKey}.{$alias}.stub недопустим",
            );
        }

        return $stub;
    }

    /**
     * Языки алиаса обязаны быть подмножеством языков проекта.
     *
     * Проверяем только если у проекта языки объявлены: конфиги, где языки заданы
     * лишь на алиасах, остаются валидными (обратная совместимость данных).
     *
     * @param array<string, array{
     *     folder: string,
     *     namespace: string,
     *     scheme: string,
     *     stub: string,
     *     default_alias: string,
     *     langs: list<string>,
     *     aliases_storage: array<string, array{db: string, prefix: string}>,
     *     mirrors: array<string, bool>
     * }> $descriptors
     * @param array<string, array<string, AliasSettings>> $declaredAliases
     */
    private static function assertAliasLangsWithinProject(array $descriptors, array $declaredAliases): void
    {
        foreach ($descriptors as $key => $descriptor) {
            $projectLangs = $descriptor['langs'];
            if ($projectLangs === []) {
                continue;
            }

            foreach ($declaredAliases[$key] ?? [] as $alias => $settings) {
                $extra = array_diff($settings->langs, $projectLangs);
                if ($extra !== []) {
                    throw new RuntimeException(sprintf(
                        'site.config: языки алиаса "%s.%s" (%s) не объявлены у проекта (%s)',
                        $key,
                        $alias,
                        implode(', ', $extra),
                        implode(', ', $projectLangs),
                    ));
                }
            }
        }
    }

    /**
     * @param array<string, array{mirrors: array<string, bool>}> $descriptors
     */
    private static function assertUniqueHosts(array $descriptors): void
    {
        /** @var array<string, string> $seen */
        $seen = [];

        foreach ($descriptors as $key => $descriptor) {
            foreach (array_keys($descriptor['mirrors']) as $host) {
                if (isset($seen[$host])) {
                    throw new RuntimeException(
                        "site.config: хост \"{$host}\" уже принадлежит проекту \"{$seen[$host]}\"",
                    );
                }
                $seen[$host] = $key;
            }
        }
    }
}
