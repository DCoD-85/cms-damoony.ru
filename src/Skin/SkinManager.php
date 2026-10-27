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

    /** @return list<string> */
    public function list(string $real): array
    {
        $dir = $this->skinDir($real);
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

    public function exists(string $real, string $skin): bool
    {
        if ($skin === '' || !preg_match('/^[a-z][a-z0-9_-]*$/', $skin)) {
            return false;
        }
        return is_dir($this->skinDir($real) . '/' . $skin);
    }

    /**
     * @param list<string> $allowed список разрешённых скинов из конфига (если есть)
     */
    public function resolve(string $real, string $requested, array $allowed = []): string
    {
        // 1. Явный запрос, существует, разрешён
        if ($requested !== '' && $this->exists($real, $requested)) {
            if ($allowed === [] || in_array($requested, $allowed, true)) {
                return $requested;
            }
        }

        // 2. Fallback (default), если существует и разрешён
        if ($this->exists($real, $this->fallback)) {
            if ($allowed === [] || in_array($this->fallback, $allowed, true)) {
                return $this->fallback;
            }
        }

        // 3. Первый разрешённый, который существует
        if ($allowed !== []) {
            foreach ($allowed as $s) {
                if ($this->exists($real, $s)) {
                    return $s;
                }
            }
        }

        // 4. Первый из списка
        $list = $this->list($real);
        return $list[0] ?? $this->fallback;
    }

    public function cssUrl(string $real, string $skin): ?string
    {
        $rel = $this->skinDir($real) . '/' . $skin . '/css/main.css';
        if (!is_file($rel)) {
            return null;
        }

        return '/media/' . $real . '/skin/' . $skin . '/css/main.css';
    }

    public function jsUrl(string $real, string $skin): ?string
    {
        $rel = $this->skinDir($real) . '/' . $skin . '/js/main.js';
        if (!is_file($rel)) {
            return null;
        }

        return '/media/' . $real . '/skin/' . $skin . '/js/main.js';
    }

    private function skinDir(string $real): string
    {
        return rtrim($this->mediaRoot, '/') . '/' . $real . '/skin';
    }
}
