<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Cli\Support\BackupArchive;
use Damoony\Core\Config;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use RuntimeException;
use ZipArchive;

/**
 * backup:create — файлы проекта (app/content/media) + дамп БД в один ZIP.
 *
 * `--only=app,content,media,db` — частичный бэкап, `--keep=N` — retention.
 * Манифест внутри архива фиксирует состав и размеры.
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
        return 'Создать бэкап проекта (--only=app,content,media,db, --keep=N)';
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

        if (!class_exists(ZipArchive::class) && !class_exists(\PharData::class)) {
            $r->error('Нет ни zip (ZipArchive), ни PharData для упаковки');

            return $r;
        }

        $backupDir = $root . '/var/backups';
        Fs::mkdir($backupDir);

        $archive = new BackupArchive();

        try {
            $parts = $archive->parseParts((string) ($args['only'] ?? ''));
        } catch (RuntimeException $e) {
            $r->error($e->getMessage());

            return $r;
        }

        if (in_array('db', $parts, true) && ($db['driver'] ?? '') !== 'mysql') {
            $r->error('Только MySQL/MariaDB: driver=' . (string) ($db['driver'] ?? 'не задан'));

            return $r;
        }

        $stamp = date('Y-m-d_H-i-s');
        $zipPath = $backupDir . '/backup_' . $project->folder . '_' . $stamp . '.zip';
        $tmpDir = sys_get_temp_dir() . '/damoony_bak_' . bin2hex(random_bytes(6));

        try {
            if (in_array('app', $parts, true)) {
                Fs::mkdir($tmpDir . '/app');
                $this->copyTree($this->paths->domain($project->folder), $tmpDir . '/app/' . $project->folder);
            }
            if (in_array('content', $parts, true)) {
                Fs::mkdir($tmpDir . '/content');
                $this->copyTree($this->paths->contentRoot() . '/' . $project->folder, $tmpDir . '/content/' . $project->folder);
            }
            if (in_array('media', $parts, true)) {
                Fs::mkdir($tmpDir . '/media');
                $this->copyTree($this->paths->mediaRoot() . '/' . $project->folder, $tmpDir . '/media/' . $project->folder);
            }

            if (in_array('db', $parts, true)) {
                $context->step('Дамп БД...', true);
                Fs::writeAtomic($tmpDir . '/db.sql', $this->dumpDatabase($db));
            }

            $context->step('Упаковка ZIP (' . implode(', ', $parts) . ')...', true);
            $manifest = $archive->pack($tmpDir, $zipPath, $parts, [
                'project' => $project->key,
                'folder' => $project->folder,
                'db' => ['driver' => (string) ($db['driver'] ?? ''), 'dbname' => (string) ($db['dbname'] ?? '')],
            ]);
        } catch (RuntimeException $e) {
            Fs::rmrf($tmpDir);
            @unlink($zipPath);
            $r->error($e->getMessage());

            return $r;
        }

        Fs::rmrf($tmpDir);
        $r->file(Fs::rel($zipPath, $root));

        $pruned = [];
        $keep = (int) ($args['keep'] ?? 0);
        if ($keep > 0) {
            $pruned = $archive->prune($backupDir, 'backup_' . $project->folder . '_', $keep);
            foreach ($pruned as $path) {
                $r->warn('Удалён старый бэкап: ' . Fs::rel($path, $root));
            }
        }

        $r->data = [
            'project' => $project->key,
            'file' => Fs::rel($zipPath, $root),
            'size' => (int) @filesize($zipPath),
            'parts' => $parts,
            'parts_stats' => $manifest['parts'] ?? [],
            'pruned' => array_map(static fn (string $p): string => Fs::rel($p, $root), $pruned),
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
}
