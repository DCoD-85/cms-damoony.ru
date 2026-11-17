<?php

declare(strict_types=1);

namespace Damoony\Cli\Support;

use Damoony\Core\Files\Fs;
use RuntimeException;

/**
 * Карантин нового CLI-стека: destructive-операции не удаляют, а переименовывают.
 *
 * Ничего не удаляется окончательно — очистка отдельной командой trash:clear.
 */
final class Quarantine
{
    /**
     * Переместить папку рядом с ней: {path}_trash_{ts}.
     *
     * @return string|null новый путь или null, если перемещать нечего
     */
    public function inPlace(string $path): ?string
    {
        if (!Fs::isDir($path)) {
            return null;
        }

        $target = $this->unique($path . '_trash_' . time());
        $this->move($path, $target);

        return $target;
    }

    /**
     * Переместить папку в общий карантин: {trashParent}/{baseName}_trash_{ts}.
     *
     * @return string|null новый путь или null, если перемещать нечего
     */
    public function toTrashDir(string $path, string $trashParent, string $baseName): ?string
    {
        if (!Fs::isDir($path)) {
            return null;
        }

        Fs::mkdir($trashParent);
        $target = $this->unique($trashParent . '/' . $baseName . '_trash_' . time());
        $this->move($path, $target);

        return $target;
    }

    /**
     * Переместить набор файлов в карантинный каталог (создаётся при необходимости).
     *
     * @param list<string> $files
     *
     * @return list<string> новые пути перемещённых файлов
     */
    public function files(string $trashDir, array $files): array
    {
        Fs::mkdir($trashDir);

        $moved = [];
        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            $target = $this->unique($trashDir . '/' . basename($file));
            if (!@rename($file, $target)) {
                throw new RuntimeException("Не удалось переместить в карантин: {$file}");
            }
            $moved[] = $target;
        }

        return $moved;
    }

    private function move(string $from, string $to): void
    {
        Fs::mkdir(dirname($to));

        if (!@rename($from, $to)) {
            throw new RuntimeException("Не удалось переместить в карантин: {$from}");
        }
    }

    private function unique(string $path): string
    {
        if (!file_exists($path)) {
            return $path;
        }

        for ($i = 1; $i < 1000; $i++) {
            $candidate = $path . '_' . $i;
            if (!file_exists($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('Не удалось подобрать имя карантина: ' . $path);
    }
}
