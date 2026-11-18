<?php

declare(strict_types=1);

namespace Damoony\Core\Module;

use RuntimeException;

/**
 * Реестр модулей: проверяет контракт и находит модуль по URL.
 */
final class ModuleRegistry
{
    /** @var array<string, ModuleInterface> */
    private array $modules = [];

    /**
     * @param list<ModuleInterface> $modules
     */
    public function __construct(array $modules)
    {
        $mounts = [];
        foreach ($modules as $module) {
            $id = $module->id();
            if ($id === '') {
                throw new RuntimeException('Module id must not be empty');
            }
            if (isset($this->modules[$id])) {
                throw new RuntimeException('Duplicate module id: ' . $id);
            }
            if ($module->apiVersion() !== ModuleInterface::API_VERSION) {
                throw new RuntimeException(
                    'Module ' . $id . ' requires API v' . $module->apiVersion()
                    . ', core supports v' . ModuleInterface::API_VERSION,
                );
            }

            $mount = $module->mount();
            if ($mount !== null) {
                if ($mount === '' || $mount[0] !== '/') {
                    throw new RuntimeException('Module ' . $id . ': mount must start with /');
                }
                if ($mount !== '/' && str_ends_with($mount, '/')) {
                    throw new RuntimeException('Module ' . $id . ': mount must not end with /');
                }
                if (isset($mounts[$mount])) {
                    throw new RuntimeException('Duplicate mount ' . $mount . ' (' . $id . ')');
                }
                $mounts[$mount] = $id;
            }

            $this->modules[$id] = $module;
        }
    }

    /** @return list<ModuleInterface> */
    public function all(): array
    {
        return array_values($this->modules);
    }

    public function byId(string $id): ?ModuleInterface
    {
        return $this->modules[$id] ?? null;
    }

    /**
     * Ищет самый длинный mount-префикс для пути.
     * Остаток всегда начинается с / (или равен / для точного совпадения).
     */
    public function match(string $path): ?ModuleMatch
    {
        $matched = null;
        $length = -1;

        foreach ($this->modules as $module) {
            $mount = $module->mount();
            if ($mount === null) {
                continue;
            }
            if ($path !== $mount && !str_starts_with($path, $mount . '/')) {
                continue;
            }
            if (strlen($mount) <= $length) {
                continue;
            }

            $length = strlen($mount);
            $remainder = substr($path, strlen($mount));
            $matched = new ModuleMatch($module, $remainder === '' ? '/' : $remainder);
        }

        return $matched;
    }
}
