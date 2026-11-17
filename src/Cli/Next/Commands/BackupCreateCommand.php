<?php

declare(strict_types=1);

namespace Damoony\Cli\Next\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use RuntimeException;
use ZipArchive;

/**
 * backup:create — файлы проекта + дамп БД в один ZIP в var/backups/.
 *
 * Поддерживается только MySQL/MariaDB (как и раньше): дамп делается mysqldump.
 * При любой ошибке временный каталог удаляется, ZIP не остаётся.
 */
final class BackupCreateCommand implements CommandInterface
{
    public function __construct(
        private readonly Paths $paths,
        private readonly SiteConfig $site,
    ) {
    }

    public function name(): string
    {
        return 'backup:create';
    }

    public function description(): string
    {
        return 'Создать бэкап проекта (файлы + БД) в var/backups/';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        try {
            $project = $this->site->resolve((string) ($args['domain'] ?? ''));
        } catch (RuntimeException $e) {
            $r->error($e->getMessage());

            return $r;
        }

        $root = $this->paths->root();

        $db = $this->dbConfig();
        if (($db['driver'] ?? '') !== 'mysql') {
            $r->error('Только MySQL/MariaDB: driver=' . (string) ($db['driver'] ?? 'не задан'));

            return $r;
        }

        if (!class_exists(ZipArchive::class) && !class_exists(\PharData::class)) {
            $r->error('Нет ни zip (ZipArchive), ни PharData для упаковки');

            return $r;
        }

        $backupDir = $root . '/var/backups';
        Fs::mkdir($backupDir);

        $stamp = date('Y-m-d_H-i-s');
        $zipPath = $backupDir . '/backup_' . $project->folder . '_' . $stamp . '.zip';
        $tmpDir = sys_get_temp_dir() . '/damoony_bak_' . bin2hex(random_bytes(6));

        try {
            Fs::mkdir($tmpDir . '/app');
            Fs::mkdir($tmpDir . '/www/media');

            $this->copyTree($this->paths->domain($project->folder), $tmpDir . '/app/' . $project->folder);
            $this->copyTree($this->paths->mediaRoot() . '/' . $project->folder, $tmpDir . '/www/media/' . $project->folder);

            $context->step('Дамп БД...', true);
            $sql = $this->dumpDatabase($db);
            Fs::writeAtomic($tmpDir . '/db.sql', $sql);

            $context->step('Упаковка ZIP...', true);
            $this->zip($tmpDir, $zipPath);
        } catch (RuntimeException $e) {
            Fs::rmrf($tmpDir);
            @unlink($zipPath);
            $r->error($e->getMessage());

            return $r;
        }

        Fs::rmrf($tmpDir);
        $r->file(Fs::rel($zipPath, $root));

        $r->data = [
            'project' => $project->key,
            'file' => Fs::rel($zipPath, $root),
            'size' => (int) @filesize($zipPath),
        ];

        $context->step('Бэкап создан', true, Fs::rel($zipPath, $root));

        return $r;
    }

    /** @return array<string, mixed> */
    private function dbConfig(): array
    {
        $file = $this->paths->root() . '/config/engine.config';
        if (!is_file($file)) {
            return [];
        }

        $config = Config::fromFile($file)->all();
        $db = $config['db'] ?? null;

        return is_array($db) ? $db : [];
    }

    /**
     * @param array<string, mixed> $db
     */
    private function dumpDatabase(array $db): string
    {
        $binary = 'mysqldump';
        $local = $this->paths->root() . '/../tools/mariadb/bin/mysqldump.exe';
        if (PHP_OS_FAMILY === 'Windows' && is_file('E:/localserver/tools/mariadb/bin/mysqldump.exe')) {
            $binary = '"E:/localserver/tools/mariadb/bin/mysqldump.exe"';
        }

        $parts = [
            $binary,
            '-h', escapeshellarg((string) ($db['host'] ?? '127.0.0.1')),
            '-u', escapeshellarg((string) ($db['user'] ?? 'root')),
        ];

        if ((string) ($db['password'] ?? '') !== '') {
            $parts[] = '--password=' . escapeshellarg((string) $db['password']);
        }

        $parts[] = escapeshellarg((string) ($db['dbname'] ?? ''));

        $output = [];
        $code = 0;
        exec(implode(' ', $parts) . ' 2>&1', $output, $code);

        if ($code !== 0) {
            throw new RuntimeException('mysqldump завершился с кодом ' . $code . ': ' . implode("\n", $output));
        }

        return implode("\n", $output) . "\n";
    }

    private function copyTree(string $src, string $dst): void
    {
        if (!Fs::isDir($src)) {
            return;
        }

        Fs::mkdir($dst);

        foreach (scandir($src) ?: [] as $item) {
            if ($item === '.' || $item === '..' || is_link($src . '/' . $item)) {
                continue;
            }

            $from = $src . '/' . $item;
            $to = $dst . '/' . $item;

            if (is_dir($from)) {
                $this->copyTree($from, $to);
            } elseif (is_file($from) && !copy($from, $to)) {
                throw new RuntimeException('Не удалось скопировать: ' . $from);
            }
        }
    }

    private function zip(string $tmpDir, string $zipPath): void
    {
        if (class_exists(ZipArchive::class)) {
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Не удалось создать ZIP: ' . $zipPath);
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($tmpDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY,
            );

            foreach ($iterator as $file) {
                if (!$file instanceof \SplFileInfo || $file->isDir()) {
                    continue;
                }
                $path = $file->getPathname();
                $zip->addFile($path, substr($path, strlen($tmpDir) + 1));
            }

            $zip->close();

            return;
        }

        // Fallback: PharData (встроен в PHP, расширение zip не требуется).
        if (is_file($zipPath)) {
            @unlink($zipPath);
        }

        try {
            $phar = new \PharData($zipPath, 0, null, \Phar::ZIP);
            $phar->buildFromDirectory($tmpDir);
        } catch (\Throwable $e) {
            throw new RuntimeException('Не удалось создать ZIP (PharData): ' . $e->getMessage());
        }
    }
}
