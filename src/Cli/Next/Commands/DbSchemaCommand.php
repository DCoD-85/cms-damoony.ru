<?php

declare(strict_types=1);

namespace Damoony\Cli\Next\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Data\Db\Connection;
use PDO;
use RuntimeException;
use Throwable;

/**
 * db:schema — таблицы и колонки проекта (read-only, information_schema / sqlite_master).
 *
 * Префикс таблиц берётся из domains_app[key].aliases_storage[alias].prefix.
 */
final class DbSchemaCommand implements CommandInterface
{
    private const ALIAS_RE = '/^[a-z][a-z0-9_]*$/';

    private const GLOBAL_TABLES = [
        'users', 'sessions', 'roles', 'user_roles', 'login_attempts', 'migrations', 'audit_log',
    ];

    public function __construct(
        private readonly SiteConfig $site,
        private readonly ?Connection $db,
    ) {
    }

    public function name(): string
    {
        return 'db:schema';
    }

    public function description(): string
    {
        return 'Таблицы и колонки проекта (read-only)';
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

        $alias = (string) ($args['alias'] ?? 'www');
        if (preg_match(self::ALIAS_RE, $alias) !== 1) {
            $r->error('Некорректный --alias');

            return $r;
        }
        if ($this->db === null) {
            $r->error('БД не сконфигурирована (config/engine.config → db)');

            return $r;
        }

        $prefix = $project->storagePrefix($alias);

        try {
            $pdo = $this->db->pdo();
        } catch (Throwable $e) {
            $r->error('Не удалось подключиться к БД: ' . $e->getMessage());

            return $r;
        }

        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        try {
            $tables = $driver === 'mysql'
                ? $this->mysqlTables($pdo, $prefix)
                : $this->sqliteTables($pdo, $prefix);
        } catch (Throwable $e) {
            $r->error('Не удалось прочитать схему: ' . $e->getMessage());

            return $r;
        }

        $r->data = [
            'project' => $project->key,
            'alias' => $alias,
            'driver' => $driver,
            'prefix' => $prefix,
            'tables' => $tables,
        ];

        $context->step(sprintf('Схема: %d таблиц (prefix "%s")', count($tables), $prefix), true);

        return $r;
    }

    /**
     * @return list<array{name: string, table: string, columns: list<array<string, mixed>>}>
     */
    private function mysqlTables(PDO $pdo, string $prefix): array
    {
        $stmt = $pdo->query(
            'SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_KEY, COLUMN_DEFAULT
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
             ORDER BY TABLE_NAME, ORDINAL_POSITION',
        );
        if ($stmt === false) {
            throw new RuntimeException('information_schema query failed');
        }

        $grouped = [];
        foreach ($stmt->fetchAll() as $row) {
            if (!is_array($row)) {
                continue;
            }

            $physical = (string) $row['TABLE_NAME'];
            if (!$this->matchesPrefix($physical, $prefix)) {
                continue;
            }

            $grouped[$physical] ??= [
                'name' => $physical,
                'table' => $this->stripPrefix($physical, $prefix),
                'columns' => [],
            ];

            $grouped[$physical]['columns'][] = [
                'name' => (string) $row['COLUMN_NAME'],
                'type' => (string) $row['DATA_TYPE'],
                'nullable' => $row['IS_NULLABLE'] === 'YES',
                'key' => (string) $row['COLUMN_KEY'],
                'default' => $row['COLUMN_DEFAULT'],
            ];
        }

        return array_values($grouped);
    }

    /**
     * @return list<array{name: string, table: string, columns: list<array<string, mixed>>}>
     */
    private function sqliteTables(PDO $pdo, string $prefix): array
    {
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
        if ($stmt === false) {
            throw new RuntimeException('sqlite_master query failed');
        }

        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $physicalRaw) {
            $physical = (string) $physicalRaw;
            if (!$this->matchesPrefix($physical, $prefix)) {
                continue;
            }

            $info = $pdo->query('PRAGMA table_info(' . $physical . ')');
            if ($info === false) {
                continue;
            }

            $columns = [];
            foreach ($info->fetchAll() as $col) {
                if (!is_array($col)) {
                    continue;
                }
                $columns[] = [
                    'name' => (string) $col['name'],
                    'type' => (string) $col['type'],
                    'nullable' => (int) $col['notnull'] === 0,
                    'key' => (int) $col['pk'] === 1 ? 'PRI' : '',
                    'default' => $col['dflt_value'],
                ];
            }

            $out[] = [
                'name' => $physical,
                'table' => $this->stripPrefix($physical, $prefix),
                'columns' => $columns,
            ];
        }

        return $out;
    }

    private function matchesPrefix(string $table, string $prefix): bool
    {
        if ($prefix === '') {
            return true;
        }
        if (in_array($table, self::GLOBAL_TABLES, true)) {
            return true;
        }

        return str_starts_with($table, $prefix);
    }

    private function stripPrefix(string $table, string $prefix): string
    {
        if ($prefix !== '' && str_starts_with($table, $prefix)) {
            return substr($table, strlen($prefix));
        }

        return $table;
    }
}
