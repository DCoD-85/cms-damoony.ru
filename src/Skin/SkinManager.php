<?php

declare(strict_types=1);

namespace Damoony\Skin;

final class SkinManager
{
    public function __construct(
        private readonly string $mediaRoot,
        private readonly string $fallback = 'default',
    ) {
    }

    /**
     * Список доступных скинов для конкретного (домен + алиас).
     *
     * @return list<string>
     */
    public function list(string $real, string $allias): array
    {
        $dir = $this->skinDir($real, $allias);
        if (!is_dir($dir)) {
            return [];
        }

        $out = [];
        foreach (scandir($dir) ?: [] as $e) {
            if ($e === '.' || $e === '..') {
                continue;
            }
            if (preg_match('/^[a-z][a-z0-9_-]*$/', $e) && is_dir($dir . '/' . $e)) {
                $out[] = $e;
            }
        }
        sort($out);

        return $out;
    }

    public function exists(string $real, string $allias, string $skin): bool
    {
        if ($skin === '' || !preg_match('/^[a-z][a-z0-9_-]*$/', $skin)) {
            return false;
        }
        return is_dir($this->skinDir($real, $allias) . '/' . $skin);
    }

    /**
     * @param list<string> $allowed список разрешённых скинов из конфига (если есть)
     */
    public function resolve(string $real, string $allias, string $requested, array $allowed = []): string
    {
        // 1. Явный запрос, существует, разрешён
        if ($requested !== '' && $this->exists($real, $allias, $requested)) {
            if ($allowed === [] || in_array($requested, $allowed, true)) {
                return $requested;
            }
        }

        // 2. Fallback (default), если существует и разрешён
        if ($this->exists($real, $allias, $this->fallback)) {
            if ($allowed === [] || in_array($this->fallback, $allowed, true)) {
                return $this->fallback;
            }
        }

        // 3. Первый разрешённый, который существует
        if ($allowed !== []) {
            foreach ($allowed as $s) {
                if ($this->exists($real, $allias, $s)) {
                    return $s;
                }
            }
        }

        // 4. Первый из списка
        $list = $this->list($real, $allias);
        return $list[0] ?? $this->fallback;
    }

    public function cssUrl(string $real, string $allias, string $skin): ?string
    {
        $rel = $this->skinDir($real, $allias) . '/' . $skin . '/css/main.css';
        if (!is_file($rel)) {
            return null;
        }

        return '/media/' . $real . '/' . $allias . '/skin/' . $skin . '/css/main.css';
    }

    public function jsUrl(string $real, string $allias, string $skin): ?string
    {
        $rel = $this->skinDir($real, $allias) . '/' . $skin . '/js/main.js';
        if (!is_file($rel)) {
            return null;
        }

        return '/media/' . $real . '/' . $allias . '/skin/' . $skin . '/js/main.js';
    }

    private function skinDir(string $real, string $allias): string
    {
        return rtrim($this->mediaRoot, '/') . '/' . $real . '/' . $allias . '/skin';
    }
}
