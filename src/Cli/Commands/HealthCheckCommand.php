<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\ObservabilityConfig;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Data\Db\Connection;
use Throwable;

/**
 * health:check — готовность сервера: конфиг, БД, запись в var/, диск, свежесть бэкапа.
 *
 * exit 1 только при `fail`; `warn` даёт status=degraded, но ok=true.
 */
final class HealthCheckCommand implements CommandInterface
{
    public function __construct(
        private readonly Paths $paths,
        private readonly ?Connection $db,
        private readonly ObservabilityConfig $observability,
    ) {
    }

    public function name(): string
    {
        return 'health:check';
    }

    public function description(): string
    {
        return 'Проверить готовность сервера (конфиг, БД, диск, бэкап)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $checks = [];
        $checks[] = $this->checkSiteConfig();
        $checks[] = $this->checkDatabase();
        $checks[] = $this->checkStorage();
        $checks[] = $this->checkDisk();
        $checks[] = $this->checkBackupAge();

        $failed = array_values(array_filter($checks, static fn (array $c): bool => $c['status'] === 'fail'));
        $warned = array_values(array_filter($checks, static fn (array $c): bool => $c['status'] === 'warn'));

        $status = $failed !== [] ? 'fail' : ($warned !== [] ? 'degraded' : 'ok');

        foreach ($checks as $check) {
            $mark = match ($check['status']) {
                'ok' => '✓',
                'warn' => '⚠',
                default => '✗',
            };
            $context->step($mark . ' ' . $check['name'] . ': ' . $check['detail'], $check['status'] !== 'fail', $check['name']);
        }

        foreach ($failed as $check) {
            $r->error($check['name'] . ': ' . $check['detail']);
        }
        foreach ($warned as $check) {
            $r->warn($check['name'] . ': ' . $check['detail']);
        }

        $r->data = ['status' => $status, 'checks' => $checks];

        return $r;
    }

    /** @return array{name: string, status: string, detail: string} */
    private function checkSiteConfig(): array
    {
        $file = $this->paths->siteConfig();
        if (!is_file($file)) {
            return ['name' => 'config', 'status' => 'fail', 'detail' => 'site.config не найден'];
        }

        try {
            SiteConfig::fromFile($file);

            return ['name' => 'config', 'status' => 'ok', 'detail' => 'site.config валиден'];
        } catch (Throwable $e) {
            return ['name' => 'config', 'status' => 'fail', 'detail' => $e->getMessage()];
        }
    }

    /** @return array{name: string, status: string, detail: string} */
    private function checkDatabase(): array
    {
        if ($this->db === null) {
            return ['name' => 'database', 'status' => 'fail', 'detail' => 'БД не сконфигурирована (engine.config → db)'];
        }

        try {
            $this->db->pdo()->query('SELECT 1');

            return ['name' => 'database', 'status' => 'ok', 'detail' => 'соединение работает'];
        } catch (Throwable $e) {
            return ['name' => 'database', 'status' => 'fail', 'detail' => $e->getMessage()];
        }
    }

    /** @return array{name: string, status: string, detail: string} */
    private function checkStorage(): array
    {
        $dir = $this->paths->root() . '/var';
        if (!is_dir($dir)) {
            return ['name' => 'storage', 'status' => 'fail', 'detail' => 'каталог var/ отсутствует'];
        }
        if (!is_writable($dir)) {
            return ['name' => 'storage', 'status' => 'fail', 'detail' => 'var/ не доступен на запись'];
        }

        return ['name' => 'storage', 'status' => 'ok', 'detail' => 'var/ доступен на запись'];
    }

    /** @return array{name: string, status: string, detail: string} */
    private function checkDisk(): array
    {
        $minMb = $this->observability->diskMinFreeMb();
        $free = @disk_free_space($this->paths->root());
        if ($free === false) {
            return ['name' => 'disk', 'status' => 'warn', 'detail' => 'не удалось получить свободное место'];
        }

        $freeMb = (int) floor($free / 1024 / 1024);
        if ($freeMb < $minMb) {
            return [
                'name' => 'disk',
                'status' => 'fail',
                'detail' => sprintf('свободно %d MB, минимум %d MB', $freeMb, $minMb),
            ];
        }

        return ['name' => 'disk', 'status' => 'ok', 'detail' => sprintf('свободно %d MB', $freeMb)];
    }

    /** @return array{name: string, status: string, detail: string} */
    private function checkBackupAge(): array
    {
        $files = glob($this->paths->root() . '/var/backups/backup_*.zip') ?: [];
        if ($files === []) {
            return ['name' => 'backup', 'status' => 'warn', 'detail' => 'бэкапов ещё нет'];
        }

        $newest = 0;
        foreach ($files as $file) {
            $newest = max($newest, (int) filemtime($file));
        }

        $maxDays = $this->observability->backupMaxAgeDays();
        if ($maxDays <= 0) {
            return ['name' => 'backup', 'status' => 'ok', 'detail' => 'проверка возраста отключена'];
        }

        $ageDays = (int) floor((time() - $newest) / 86400);
        if ($ageDays > $maxDays) {
            return [
                'name' => 'backup',
                'status' => 'fail',
                'detail' => sprintf('последний бэкап %d дн. назад, максимум %d', $ageDays, $maxDays),
            ];
        }

        return ['name' => 'backup', 'status' => 'ok', 'detail' => sprintf('последний бэкап %d дн. назад', $ageDays)];
    }
}
