<?php

declare(strict_types=1);

namespace Damoony\Core\Assembly;

use Damoony\Core\Files\Paths;
use JsonException;
use RuntimeException;

/**
 * Манифест сборки (stubs/assemblies/{name}/assembly.json).
 *
 * Языки — блок `langs` (нет → обратная совместимость ru/en).
 * Алиасы — блок `aliases` (нет → только www).
 */
final class AssemblyManifest
{
    private const LANG_RE = '/^[a-z]{2}$/';
    private const ALIAS_RE = '/^[a-z][a-z0-9_]*$/';
    public const FALLBACK_LANGS = ['ru', 'en'];
    public const FALLBACK_ALIASES = ['www'];

    /**
     * @param list<string>|null $projectLangs
     * @param array<string, list<string>> $aliasLangs
     * @param list<string> $aliases
     */
    private function __construct(
        private readonly string $name,
        private readonly ?array $projectLangs,
        private readonly ?string $defaultLang,
        private readonly array $aliasLangs,
        private readonly array $aliases,
    ) {
    }

    public static function fromDirectory(Paths $paths, string $assembly): self
    {
        $dir = $paths->root() . '/stubs/assemblies/' . $assembly;
        if (!is_dir($dir)) {
            throw new RuntimeException("Сборка '{$assembly}' не найдена в stubs/assemblies/");
        }

        $file = $dir . '/assembly.json';
        if (!is_file($file)) {
            throw new RuntimeException("Файл assembly.json не найден в сборке '{$assembly}'");
        }

        return self::fromJson((string) file_get_contents($file), $assembly);
    }

    public static function fromJson(string $json, string $assembly = ''): self
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('assembly.json: некорректный JSON: ' . $e->getMessage(), 0, $e);
        }

        if (!is_array($data)) {
            throw new RuntimeException("Некорректный assembly.json в сборке '{$assembly}'");
        }

        $name = $data['name'] ?? ($assembly !== '' ? $assembly : 'assembly');
        if (!is_string($name) || trim($name) === '') {
            throw new RuntimeException('assembly.json: name должен быть непустой строкой');
        }

        $aliases = self::parseAliases($data['aliases'] ?? null, $assembly);

        $langs = $data['langs'] ?? null;
        if ($langs === null) {
            return new self($name, null, null, [], $aliases);
        }
        if (!is_array($langs)) {
            throw new RuntimeException('assembly.json: langs должен быть объектом');
        }

        $project = $langs['project'] ?? null;
        if (!is_array($project) || $project === []) {
            throw new RuntimeException('assembly.json: langs.project — непустой массив языков');
        }

        $projectLangs = [];
        foreach ($project as $lang) {
            if (!is_string($lang) || preg_match(self::LANG_RE, $lang) !== 1) {
                throw new RuntimeException('assembly.json: язык проекта — две строчные буквы, например ru');
            }
            if (in_array($lang, $projectLangs, true)) {
                throw new RuntimeException('assembly.json: язык проекта повторяется — ' . $lang);
            }
            $projectLangs[] = $lang;
        }

        $default = $langs['default'] ?? $projectLangs[0];
        if (!is_string($default) || !in_array($default, $projectLangs, true)) {
            throw new RuntimeException('assembly.json: langs.default должен входить в langs.project');
        }

        $langAliases = $langs['aliases'] ?? [];
        if (!is_array($langAliases)) {
            throw new RuntimeException('assembly.json: langs.aliases должен быть объектом');
        }

        $aliasLangs = [];
        foreach ($langAliases as $alias => $list) {
            if (!is_string($alias) || preg_match(self::ALIAS_RE, $alias) !== 1) {
                throw new RuntimeException('assembly.json: некорректный алиас в langs.aliases — ' . (string) $alias);
            }
            if (!in_array($alias, $aliases, true)) {
                throw new RuntimeException('assembly.json: язык алиаса ' . $alias . ' задан, но алиаса нет в aliases');
            }
            if (!is_array($list) || $list === []) {
                throw new RuntimeException('assembly.json: langs.aliases.' . $alias . ' — непустой массив языков');
            }

            $langsForAlias = [];
            foreach ($list as $lang) {
                if (!is_string($lang) || preg_match(self::LANG_RE, $lang) !== 1) {
                    throw new RuntimeException('assembly.json: язык алиаса — две строчные буквы, например ru');
                }
                if (!in_array($lang, $projectLangs, true)) {
                    throw new RuntimeException(
                        'assembly.json: язык алиаса ' . $alias . ' (' . $lang . ') не входит в языки проекта',
                    );
                }
                if (!in_array($lang, $langsForAlias, true)) {
                    $langsForAlias[] = $lang;
                }
            }
            $aliasLangs[$alias] = $langsForAlias;
        }

        return new self($name, $projectLangs, $default, $aliasLangs, $aliases);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function hasLangs(): bool
    {
        return $this->projectLangs !== null;
    }

    /**
     * @return list<string>
     */
    public function aliases(): array
    {
        return $this->aliases;
    }

    /**
     * Языки проекта с дефолтным первым (в этом порядке их видит движок).
     *
     * @return list<string>
     */
    public function projectLangs(): array
    {
        if ($this->projectLangs === null) {
            return self::FALLBACK_LANGS;
        }

        $langs = [$this->defaultLang ?? $this->projectLangs[0]];
        foreach ($this->projectLangs as $lang) {
            if (!in_array($lang, $langs, true)) {
                $langs[] = $lang;
            }
        }

        return $langs;
    }

    public function defaultLang(): string
    {
        return $this->defaultLang ?? ($this->projectLangs[0] ?? self::FALLBACK_LANGS[0]);
    }

    /**
     * @return list<string>
     */
    public function aliasLangs(string $alias): array
    {
        if (isset($this->aliasLangs[$alias])) {
            return $this->aliasLangs[$alias];
        }

        return $this->projectLangs();
    }

    /**
     * @return list<string>
     */
    public function requiredI18nLangs(): array
    {
        return $this->projectLangs();
    }

    /**
     * @return list<string>
     */
    private static function parseAliases(mixed $aliases, string $assembly): array
    {
        if ($aliases === null) {
            return self::FALLBACK_ALIASES;
        }
        if (!is_array($aliases) || $aliases === []) {
            throw new RuntimeException('assembly.json: aliases — непустой массив алиасов');
        }

        $out = [];
        foreach ($aliases as $alias) {
            if (!is_string($alias) || preg_match(self::ALIAS_RE, $alias) !== 1) {
                throw new RuntimeException('assembly.json: некорректный алиас в aliases — ' . (string) $alias);
            }
            if (in_array($alias, $out, true)) {
                throw new RuntimeException('assembly.json: алиас повторяется — ' . $alias);
            }
            $out[] = $alias;
        }

        return $out;
    }
}
