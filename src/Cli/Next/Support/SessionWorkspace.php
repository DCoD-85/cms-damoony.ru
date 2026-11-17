<?php

declare(strict_types=1);

namespace Damoony\Cli\Next\Support;

use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use RuntimeException;

/**
 * Изолированная AIUI-сессия: app/.sess_{tab}.{folder}/ + www/media/.sess_{tab}.{folder}/.
 *
 * Оптимистичная блокировка — hash по .editor.json production-домена на момент старта.
 */
final class SessionWorkspace
{
    public const TAB_RE = '/^[a-f0-9]{8,32}$/';

    private const DEFAULT_PERMISSIONS = [
        'page:write' => true,
        'block:write' => true,
        'filler:write' => true,
        'skin:edit' => false,
        'scheme:save' => false,
        'structure:check' => true,
        'net:fetch' => true,
    ];

    public function __construct(private readonly Paths $paths)
    {
    }

    public function appDir(string $tab, string $folder): string
    {
        return $this->paths->sessionDir($tab, $folder);
    }

    public function mediaDir(string $tab, string $folder): string
    {
        return $this->paths->mediaRoot() . '/.sess_' . $tab . '.' . $folder;
    }

    public function assertTab(string $tab): void
    {
        if (preg_match(self::TAB_RE, $tab) !== 1) {
            throw new RuntimeException('Некорректный --tab (8-32 hex символов)');
        }
    }

    /**
     * Создаёт сессию как копию production (домен + media).
     *
     * @return array<string, mixed> метаданные сессии
     */
    public function start(string $folder, string $projectKey, string $tab, string $adminSess = ''): array
    {
        $this->assertTab($tab);

        $releaseDir = $this->paths->domain($folder);
        if (!Fs::isDir($releaseDir)) {
            throw new RuntimeException('Папка проекта не найдена: ' . $releaseDir);
        }

        $sessionDir = $this->appDir($tab, $folder);
        if (Fs::isDir($sessionDir)) {
            throw new RuntimeException('Сессия уже существует: ' . $sessionDir);
        }

        $this->copyTree($releaseDir, $sessionDir);

        $mediaSource = $this->paths->mediaRoot() . '/' . $folder;
        $mediaDest = $this->mediaDir($tab, $folder);
        if (Fs::isDir($mediaSource)) {
            $this->copyTree($mediaSource, $mediaDest);
        }

        $meta = [
            'tab_id' => $tab,
            'project' => $projectKey,
            'folder' => $folder,
            'admin_session_id' => $adminSess,
            'created_at' => time(),
            'production_hash' => $this->hashProduction($releaseDir),
            'permissions' => self::DEFAULT_PERMISSIONS,
        ];

        $this->writeMeta($sessionDir, $meta);

        return $meta;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function readMeta(string $sessionDir): ?array
    {
        $file = $sessionDir . '/.session.json';
        if (!is_file($file)) {
            return null;
        }

        $raw = file_get_contents($file);
        if ($raw === false) {
            return null;
        }

        $meta = json_decode($raw, true);

        return is_array($meta) ? $meta : null;
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function writeMeta(string $sessionDir, array $meta): void
    {
        Fs::writeAtomic(
            $sessionDir . '/.session.json',
            json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
        );
    }

    public function hashProduction(string $dir): string
    {
        if (!Fs::isDir($dir)) {
            return hash('sha256', '');
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo
                && $file->isFile()
                && str_ends_with($file->getFilename(), '.editor.json')) {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        $ctx = hash_init('sha256');
        foreach ($files as $file) {
            hash_update($ctx, $file);
            hash_update($ctx, (string) file_get_contents($file));
        }

        return hash_final($ctx);
    }

    public function assertTreeCopyable(string $src): void
    {
        if (!Fs::isDir($src)) {
            throw new RuntimeException('Папка не найдена: ' . $src);
        }
    }

    private function copyTree(string $src, string $dst): void
    {
        if (!Fs::isDir($dst)) {
            Fs::mkdir($dst);
        }

        foreach (scandir($src) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $srcPath = $src . '/' . $item;
            $dstPath = $dst . '/' . $item;

            if (is_link($srcPath)) {
                continue;
            }
            if (is_dir($srcPath)) {
                $this->copyTree($srcPath, $dstPath);
            } elseif (is_file($srcPath) && !copy($srcPath, $dstPath)) {
                throw new RuntimeException("Не удалось скопировать: {$srcPath} -> {$dstPath}");
            }
        }
    }
}
