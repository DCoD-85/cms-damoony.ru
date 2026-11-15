<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Commands\Concerns\ResolvesRealDomain;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;

final class BackupCreateCommand implements CommandInterface
{
    use ResolvesRealDomain;

    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'backup:create';
    }

    public function description(): string
    {
        return 'Создать бэкап домена (файлы + БД) в var/backups/';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();
        $domain = (string) ($args['domain'] ?? '');
        if ($domain === '') {
            $r->error('Укажите --domain=[domain]');
            return $r;
        }

        $realDomain = $this->resolveRealDomain($this->paths, $domain);
        $root = $this->paths->root();

        $backupDir = $root . '/var/backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0o775, true);
        }

        $timestamp = date('Y-m-d_H-i-s');
        $zipName = $backupDir . '/backup_' . $realDomain . '_' . $timestamp . '.zip';
        $tmpDir = sys_get_temp_dir() . '/damoony_bak_' . uniqid();

        mkdir($tmpDir . '/app', 0o775, true);
        mkdir($tmpDir . '/www/media', 0o775, true);

        // 1. Файлы
        $context->step('Сбор файлов домена...', true);
        if (is_dir($root . '/app/' . $realDomain)) {
            $this->copyDir($root . '/app/' . $realDomain, $tmpDir . '/app/' . $realDomain);
        }
        if (is_dir($root . '/www/media/' . $realDomain)) {
            $this->copyDir($root . '/www/media/' . $realDomain, $tmpDir . '/www/media/' . $realDomain);
        }

        // 2. БД
        $context->step('Создание дампа БД...', true);
        $engineFile = $root . '/config/engine.config';
        $engine = is_file($engineFile) ? Config::fromFile($engineFile) : Config::fromArray([]);
        $db = $engine->getByKey(['db'], []);

        if (($db['driver'] ?? '') === 'mysql') {
            $host = $db['host'] ?? '127.0.0.1';
            $user = $db['user'] ?? 'root';
            $pass = $db['password'] ?? '';
            $name = $db['dbname'] ?? '';

            $mysqldump = 'mysqldump';
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $localDump = 'E:/localserver/tools/mariadb/bin/mysqldump.exe';
                if (is_file($localDump)) {
                    $mysqldump = '"' . $localDump . '"';
                }
            }

            $passArg = $pass !== '' ? '--password=' . escapeshellarg($pass) : '';
            $dumpCmd = sprintf(
                '%s -h %s -u %s %s %s 2>&1',
                $mysqldump,
                escapeshellarg($host),
                escapeshellarg($user),
                $passArg,
                escapeshellarg($name),
            );

            exec($dumpCmd, $output, $returnCode);
            if ($returnCode !== 0) {
                $r->error("Ошибка mysqldump (код {$returnCode}):\n" . implode("\n", $output));
                Fs::rmrf($tmpDir);
                return $r;
            }
            file_put_contents($tmpDir . '/db.sql', implode("\n", $output));
        } else {
            $r->error('SQLite не поддерживается в этой версии бэкапа');
            Fs::rmrf($tmpDir);
            return $r;
        }

        // 3. ZIP
        $context->step('Упаковка в ZIP...', true);
        if (class_exists('\ZipArchive')) {
            $zip = new \ZipArchive();
            if ($zip->open($zipName, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($tmpDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::LEAVES_ONLY,
                );
                foreach ($files as $file) {
                    if (!$file->isDir()) {
                        $filePath = $file->getRealPath();
                        $relativePath = substr($filePath, strlen($tmpDir) + 1);
                        $zip->addFile($filePath, $relativePath);
                    }
                }
                $zip->close();
            } else {
                $r->error('Не удалось создать ZIP (ZipArchive)');
                Fs::rmrf($tmpDir);
                return $r;
            }
        } else {
            // Fallback на PharData (встроенный в PHP)
            try {
                if (file_exists($zipName)) {
                    unlink($zipName);
                }
                $phar = new \PharData($zipName, 0, null, \Phar::ZIP);
                $phar->buildFromDirectory($tmpDir);
            } catch (\Throwable $e) {
                $r->error('Не удалось создать ZIP (PharData): ' . $e->getMessage());
                Fs::rmrf($tmpDir);
                return $r;
            }
        }

        Fs::rmrf($tmpDir);
        $context->step('Бэкап создан: ' . $zipName, true);
        return $r;
    }

    private function copyDir(string $src, string $dst): void
    {
        if (!is_dir($dst)) {
            mkdir($dst, 0o775, true);
        }
        $dir = opendir($src);
        if ($dir === false) {
            return;
        }
        while (false !== ($file = readdir($dir))) {
            if ($file !== '.' && $file !== '..') {
                if (is_dir($src . '/' . $file)) {
                    $this->copyDir($src . '/' . $file, $dst . '/' . $file);
                } else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }
}
