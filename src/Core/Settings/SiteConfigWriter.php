<?php

declare(strict_types=1);

namespace Damoony\Core\Settings;

use Damoony\Core\Files\Fs;
use JsonException;
use RuntimeException;

/**
 * Запись site.config (схема domains_app) с сохранением неизвестных ключей.
 *
 * Каждая мутация начинается с чтения файла целиком и заканчивается атомарной
 * записью. Перед записью результат валидируется через SiteConfig::fromArray(),
 * поэтому битый конфиг физически не может попасть на диск.
 */
final class SiteConfigWriter
{
    public function __construct(private readonly string $path)
    {
    }

    public static function forRoot(string $root): self
    {
        return new self($root . '/config/site.config.new');
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * @return array<string, mixed>
     */
    public function load(): array
    {
        if (!is_file($this->path)) {
            return ['domains_app' => [], 'alliases' => []];
        }

        $raw = Fs::read($this->path);

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('site.config: некорректный JSON: ' . $e->getMessage(), 0, $e);
        }

        if (!is_array($data)) {
            throw new RuntimeException('site.config: ожидался JSON-объект');
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function save(array $data): void
    {
        if (!isset($data['domains_app']) || !is_array($data['domains_app'])) {
            $data['domains_app'] = [];
        }

        // Валидация до записи: битый конфиг не должен попасть на диск.
        SiteConfig::fromArray($data);

        Fs::writeAtomic(
            $this->path,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
        );
    }

    /**
     * Идемпотентно объявляет проект. Возвращает true, если проект создан.
     */
    public function ensureProject(string $key, string $folder, string $namespace): bool
    {
        $data = $this->load();
        $apps = $this->apps($data);

        if (isset($apps[$key])) {
            $existing = $this->projectApp($apps, $key);
            if (($existing['folder'] ?? null) !== $folder || ($existing['namespace'] ?? null) !== $namespace) {
                throw new RuntimeException(
                    "site.config: проект \"{$key}\" уже есть с другими folder/namespace",
                );
            }

            return false;
        }

        $data['domains_app'][$key] = [
            'folder' => $folder,
            'namespace' => $namespace,
            'mirrors' => [],
        ];
        $this->save($data);

        return true;
    }

    public function ensureMirror(string $key, string $host, bool $available = true): bool
    {
        $host = SiteConfig::normalizeHost($host);
        $data = $this->load();
        $apps = $this->apps($data);

        $mirrors = $this->projectApp($apps, $key)['mirrors'] ?? [];
        if (!is_array($mirrors)) {
            throw new RuntimeException("site.config: domains_app.{$key}.mirrors испорчен");
        }

        $exists = isset($mirrors[$host]);
        $mirrors[$host] = ['available' => $available];
        $data['domains_app'][$key]['mirrors'] = $mirrors;
        $this->save($data);

        return !$exists;
    }

    public function setMirrorAvailable(string $key, string $host, bool $available): void
    {
        $host = SiteConfig::normalizeHost($host);
        $data = $this->load();
        $apps = $this->apps($data);
        $project = $this->projectApp($apps, $key);

        $mirrors = $project['mirrors'] ?? [];
        if (!is_array($mirrors) || !isset($mirrors[$host])) {
            throw new RuntimeException("site.config: миррор \"{$host}\" не найден у проекта \"{$key}\"");
        }

        $data['domains_app'][$key]['mirrors'][$host]['available'] = $available;
        $this->save($data);
    }

    /**
     * @param list<string> $langs
     */
    public function ensureAlias(
        string $key,
        string $alias,
        array $langs = ['ru', 'en'],
        bool $available = true,
    ): bool {
        $data = $this->load();
        $apps = $this->apps($data);
        $this->projectApp($apps, $key);

        if (!isset($data['alliases']) || !is_array($data['alliases'])) {
            $data['alliases'] = [];
        }

        $exists = isset($data['alliases'][$key][$alias]);
        $data['alliases'][$key][$alias] = ['available' => $available, 'langs' => $langs];
        $this->save($data);

        return !$exists;
    }

    public function setAliasAvailable(string $key, string $alias, bool $available): void
    {
        $data = $this->load();

        if (!isset($data['alliases'][$key][$alias])) {
            throw new RuntimeException("site.config: алиас \"{$key}.{$alias}\" не найден");
        }

        $data['alliases'][$key][$alias]['available'] = $available;
        $this->save($data);
    }

    /**
     * @param list<string> $langs
     */
    public function setAliasLangs(string $key, string $alias, array $langs): void
    {
        $data = $this->load();

        if (!isset($data['alliases'][$key][$alias])) {
            throw new RuntimeException("site.config: алиас \"{$key}.{$alias}\" не найден");
        }

        $data['alliases'][$key][$alias]['langs'] = $langs;
        $this->save($data);
    }

    public function removeAlias(string $key, string $alias): bool
    {
        $data = $this->load();

        if (!isset($data['alliases'][$key][$alias])) {
            return false;
        }

        unset($data['alliases'][$key][$alias]);
        $this->save($data);

        return true;
    }

    public function removeMirror(string $key, string $host): bool
    {
        $host = SiteConfig::normalizeHost($host);
        $data = $this->load();
        $apps = $this->apps($data);
        $project = $this->projectApp($apps, $key);

        $mirrors = $project['mirrors'] ?? [];
        if (!is_array($mirrors) || !isset($mirrors[$host])) {
            return false;
        }

        unset($data['domains_app'][$key]['mirrors'][$host]);
        $this->save($data);

        return true;
    }

    public function removeProject(string $key): bool
    {
        $data = $this->load();
        $apps = $this->apps($data);

        if (!isset($apps[$key])) {
            return false;
        }

        unset($data['domains_app'][$key], $data['alliases'][$key]);
        $this->save($data);

        return true;
    }

    /**
     * @param array<string, array<string, mixed>> $apps
     *
     * @return array<string, mixed>
     */
    private function projectApp(array $apps, string $key): array
    {
        $project = $apps[$key] ?? null;
        if (!is_array($project)) {
            throw new RuntimeException("site.config: проект \"{$key}\" не найден в domains_app");
        }

        return $project;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, array<string, mixed>>
     */
    private function apps(array &$data): array
    {
        if (!isset($data['domains_app']) || !is_array($data['domains_app'])) {
            $data['domains_app'] = [];
        }

        /** @var array<string, array<string, mixed>> $apps */
        $apps = $data['domains_app'];

        return $apps;
    }
}
