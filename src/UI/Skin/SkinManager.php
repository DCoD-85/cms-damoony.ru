<?php

declare(strict_types=1);

namespace Damoony\UI\Skin;

final class SkinManager
{
    public function __construct(
        private readonly string $mediaRoot,
    ) {
    }

    /** @return list<string> */
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

    private function skinDir(string $real, string $allias): string
    {
        return rtrim($this->mediaRoot, '/') . '/' . $real . '/' . $allias . '/skin';
    }
}
