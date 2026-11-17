<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * backup:restore — восстановление проекта из ZIP (файлы + БД).
 *
 * Требует --force: текущие файлы уходят в карантин с маркером `_trash_restore_`.
 */
final class BackupRestoreCommand implements CommandInterface
{
    public function __construct(
        private readonly Paths $paths,
        private readonly SiteConfig $site,
    ) {
    }

    public function name(): string
    {
        return 'backup:restore';
    }

    public function description(): string
    {
        return 'Восстановить проект из ZIP-бэкапа (--force)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $file = (string) ($args['file'] ?? '');
        if ($file === '') {
            $r->error('Укажите --file=[path/to/backup.zip]');

            return $r;
        }

        // Абсолютный путь принимается как есть; относительный — от корня проекта.
        $isAbsolute = str_starts_with($file, '/')
            || str_starts_with($file, '\\')
            || preg_match('/^[a-zA-Z]:[\\\\\/]/', $file) === 1;

        $zipPath = $isAbsolute ? $file : $this->paths->root() . '/' . ltrim($file, '/\\');
        if (!is_file($zipPath)) {
            $r->error('Файл бэкапа не найден: ' . $zipPath);

            return $r;
        }

        if (!isset($args['force'])) {
            $r->error('Восстановление перезапишет файлы и БД. Запустите с --force');

            return $r;
        }

        try {
            $project = $this->site->resolve((string) ($args['domain'] ?? ''));
        } catch (RuntimeException $e) {
            $r->error($e->getMessage());

            return $r;
        }

        $db = $this->dbConfig();
        if (($db['driver'] ?? '') !== 'mysql') {
            $r->error('Только MySQL/MariaDB: driver=' . (string) ($db['driver'] ?? 'не задан'));

            return $r;
        }

        $tmpDir = sys_get_temp_dir() . '/damoony_restore_' . bin2hex(random_bytes(6));

        try {
            Fs::mkdir($tmpDir);
            $this->unzip($zipPath, $tmpDir);

            if (!is_file($tmpDir . '/db.sql')) {
                throw new RuntimeException('В архиве отсутствует db.sql');
            }

            $context->step('Восстановление БД...', true);
            $this->importDatabase($db, $tmpDir . '/db.sql');

            $context->step('Восстановление файлов (текущие в карантин)...', true);
            $stamp = date('Y-m-d_H-i-s');

            $appCurrent = $this->paths->domain($project->folder);
            $mediaCurrent = $this->paths->mediaRoot() . '/' . $project->folder;
            $contentCurrent = $this->paths->contentRoot() . '/' . $project->folder;

            Fs::mkdir($this->paths->mediaRoot() . '/.trash');
            Fs::mkdir($this->paths->contentRoot() . '/.trash');

            if (Fs::isDir($appCurrent)
                && !@rename($appCurrent, $this->paths->appRoot() . '/' . $project->folder . '_trash_restore_' . $stamp)) {
                throw new RuntimeException('Не удалось увести текущие файлы проекта в карантин');
            }
            if (Fs::isDir($mediaCurrent)
                && !@rename($mediaCurrent, $this->paths->mediaRoot() . '/.trash/'
                    . $project->folder . '_trash_restore_' . $stamp)) {
                $r->warn('Не удалось увести media проекта в карантин');
            }
            if (Fs::isDir($contentCurrent)
                && !@rename($contentCurrent, $this->paths->contentRoot() . '/.trash/'
                    . $project->folder . '_trash_restore_' . $stamp)) {
                $r->warn('Не удалось увести content проекта в карантин');
            }

            $this->copyTree($tmpDir . '/app/' . $project->folder, $appCurrent);
            $this->copyTree($tmpDir . '/www/media/' . $project->folder, $mediaCurrent);
            $this->copyTree($tmpDir . '/content/' . $project->folder, $contentCurrent);
        } catch (Throwable $e) {
            Fs::rmrf($tmpDir);
            $r->error($e->getMessage());

            return $r;
        }

        Fs::rmrf($tmpDir);

        $r->warn('Старые файлы в карантине: очистка php bin/cli.php trash:clear --force --older=7d');
        $r->data = ['project' => $project->key, 'restored' => true, 'file' => $file];

        $context->step('Восстановление завершено', true);

        return $r;
    }

    /** @return array<string, mixed> */
    private function dbConfig(): array
    {
        $configFile = $this->paths->root() . '/config/engine.config';
        if (!is_file($configFile)) {
            return [];
        }

        $config = Config::fromFile($configFile)->all();
        $db = $config['db'] ?? null;

        return is_array($db) ? $db : [];
    }

    private function unzip(string $zipPath, string $tmpDir): void
    {
        if (class_exists(ZipArchive::class)) {
            $zip = new ZipArchive();
            if ($zip->open($zipPath) !== true) {
                throw new RuntimeException('Не удалось открыть ZIP');
            }

            $zip->extractTo($tmpDir);
            $zip->close();

            return;
        }

        try {
            $phar = new \PharData($zipPath);
            $phar->extractTo($tmpDir);
        } catch (\Throwable $e) {
            throw new RuntimeException('Не удалось открыть ZIP (PharData): ' . $e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $db
     */
    private function importDatabase(array $db, string $sqlFile): void
    {
        $binary = 'mysql';
        if (PHP_OS_FAMILY === 'Windows' && is_file('E:/localserver/tools/mariadb/bin/mysql.exe')) {
            $binary = '"E:/localserver/tools/mariadb/bin/mysql.exe"';
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
        exec(implode(' ', $parts) . ' < ' . escapeshellarg($sqlFile) . ' 2>&1', $output, $code);

        if ($code !== 0) {
            throw new RuntimeException('mysql завершился с кодом ' . $code . ': ' . implode("\n", $output));
        }
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
}
