<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Cli\Commands\Concerns\ResolvesRealDomain;
use Damoony\Core\Config;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;

final class BackupRestoreCommand implements CommandInterface
{
    use ResolvesRealDomain;

    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'backup:restore';
    }

    public function description(): string
    {
        return 'Восстановить домен из ZIP-бэкапа (файлы + БД)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();
        $domain = (string) ($args['domain'] ?? '');
        $file = (string) ($args['file'] ?? '');
        $force = isset($args['force']) && $args['force'] !== '0' && $args['force'] !== 'false';

        if ($domain === '' || $file === '') {
            $r->error('Укажите --domain=[domain] и --file=[path/to/backup.zip]');
            return $r;
        }

        $root = $this->paths->root();
        $zipPath = $root . '/' . ltrim($file, '/\\');
        if (!is_file($zipPath)) {
            $r->error("Файл бэкапа не найден: {$zipPath}");
            return $r;
        }

        if (!$force) {
            $r->error('Восстановление перезапишет текущие файлы и БД. Запустите с флагом --force');
            return $r;
        }

        $realDomain = $this->resolveRealDomain($this->paths, $domain);
        $timestamp = date('Y-m-d_H-i-s');
        $tmpDir = sys_get_temp_dir() . '/damoony_restore_' . uniqid();

        // 1. Распаковка
        $context->step('Распаковка архива...', true);
        mkdir($tmpDir, 0775, true);
        if (class_exists('\ZipArchive')) {
            $zip = new \ZipArchive();
            if ($zip->open($zipPath) === true) {
                $zip->extractTo($tmpDir);
                $zip->close();
            } else {
                $r->error('Не удалось открыть ZIP (ZipArchive)');
                return $r;
            }
        } else {
            try {
                $phar = new \PharData($zipPath);
                $phar->extractTo($tmpDir);
            } catch (\Throwable $e) {
                $r->error('Не удалось открыть ZIP (PharData): ' . $e->getMessage());
                return $r;
            }
        }

        if (!is_file($tmpDir . '/db.sql')) {
            $r->error('В архиве отсутствует db.sql');
            Fs::rmrf($tmpDir);
            return $r;
        }

        // 2. БД
        $context->step('Восстановление БД...', true);
        $engineFile = $root . '/config/engine.config';
        $engine = is_file($engineFile) ? Config::fromFile($engineFile) : Config::fromArray([]);
        $db = $engine->getByKey(['db'], []);

        if (($db['driver'] ?? '') === 'mysql') {
            $host = $db['host'] ?? '127.0.0.1';
            $user = $db['user'] ?? 'root';
            $pass = $db['password'] ?? '';
            $name = $db['dbname'] ?? '';

            $mysql = 'mysql';
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $localMysql = 'E:/localserver/tools/mariadb/bin/mysql.exe';
                if (is_file($localMysql)) {
                    $mysql = '"' . $localMysql . '"';
                }
            }

            $passArg = $pass !== '' ? '--password=' . escapeshellarg($pass) : '';
            $importCmd = sprintf(
                '%s -h %s -u %s %s %s < "%s/db.sql" 2>&1',
                $mysql,
                escapeshellarg($host),
                escapeshellarg($user),
                $passArg,
                escapeshellarg($name),
                $tmpDir
            );
            
            exec($importCmd, $output, $returnCode);
            if ($returnCode !== 0) {
                $r->error("Ошибка mysql (код {$returnCode}):\n" . implode("\n", $output));
                Fs::rmrf($tmpDir);
                return $r;
            }
        } else {
            $r->error('Только MySQL');
            Fs::rmrf($tmpDir);
            return $r;
        }

        // 3. Файлы (с карантином старых)
        $context->step('Восстановление файлов (старые в карантин)...', true);
        
        $appCurrent = $root . '/app/' . $realDomain;
        $mediaCurrent = $root . '/www/media/' . $realDomain;
        
        if (is_dir($appCurrent)) {
            rename($appCurrent, $root . '/app/' . $realDomain . '_trash_restore_' . $timestamp);
        }
        if (is_dir($mediaCurrent)) {
            rename($mediaCurrent, $root . '/www/media/' . $realDomain . '_trash_restore_' . $timestamp);
        }

        if (is_dir($tmpDir . '/app/' . $realDomain)) {
            $this->copyDir($tmpDir . '/app/' . $realDomain, $appCurrent);
        }
        if (is_dir($tmpDir . '/www/media/' . $realDomain)) {
            $this->copyDir($tmpDir . '/www/media/' . $realDomain, $mediaCurrent);
        }

        Fs::rmrf($tmpDir);
        $context->step('Восстановление завершено успешно!', true);
        return $r;
    }

    private function copyDir(string $src, string $dst): void
    {
        if (!is_dir($dst)) mkdir($dst, 0775, true);
        $dir = opendir($src);
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
