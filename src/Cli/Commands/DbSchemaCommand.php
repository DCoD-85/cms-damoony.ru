<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Data\Db\Connection;

final class DbSchemaCommand implements CommandInterface
{
    public function __construct(
        private readonly Config $site,
        private readonly Connection $db,
    ) {
    }

    public function name(): string
    {
        return 'db:schema';
    }

    public function description(): string
    {
        return 'Show database schema (tables + columns) for a domain/alias (read-only)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $domain = $args['domain'] ?? '';
        if ($domain === ''
            || !preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain)
            || str_contains($domain, '..')) {
            $r->error('Invalid or missing --domain.');

            return $r;
        }

        $alias = $args['alias'] ?? 'www';
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $alias)) {
            $r->error('Invalid --alias.');

            return $r;
        }

        $prefix = $this->resolvePrefix($domain, $alias);

        try {
            $pdo = $this->db->pdo();
        } catch (\Throwable $e) {
            $r->error('DB connection failed: ' . $e->getMessage());

            return $r;
        }

        $driver = (string) $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        try {
            $tables = $driver === 'mysql'
                ? $this->readMysqlSchema($pdo, $prefix)
                : $this->readSqliteSchema($pdo, $prefix);
        } catch (\Throwable $e) {
            $r->error('Schema read failed: ' . $e->getMessage());

            return $r;
        }

        $r->data = [
            'domain' => $domain,
            'alias' => $alias,
            'driver' => $driver,
            'prefix' => $prefix,
            'tables' => $tables,
        ];
        $context->step(sprintf('Schema: %d tables (prefix "%s")', count($tables), $prefix), true);

        return $r;
    }

    private function resolvePrefix(string $domain, string $alias): string
    {
        $map = $this->site->getByKey(['domains_aliases_storage'], []);
        if (!is_array($map)) {
            return '';
        }
        $domainMap = $map[$domain] ?? null;
        if (!is_array($domainMap)) {
            return '';
        }
        $aliasMap = $domainMap[$alias] ?? null;
        if (!is_array($aliasMap)) {
            return '';
        }
        $prefix = $aliasMap['prefix'] ?? '';

        return is_string($prefix) ? $prefix : '';
    }

    /**
     * @return list<array{name: string, table: string, columns: list<array<string, mixed>>}>
     */
    private function readMysqlSchema(\PDO $pdo, string $prefix): array
    {
        $stmt = $pdo->query(
            'SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_KEY, COLUMN_DEFAULT
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
             ORDER BY TABLE_NAME, ORDINAL_POSITION',
        );
        if ($stmt === false) {
            throw new \RuntimeException('information_schema query failed');
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
            if (!isset($grouped[$physical])) {
                $grouped[$physical] = [
                    'name' => $physical,
                    'table' => $this->stripPrefix($physical, $prefix),
                    'columns' => [],
                ];
            }
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
    private function readSqliteSchema(\PDO $pdo, string $prefix): array
    {
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
        if ($stmt === false) {
            throw new \RuntimeException('sqlite_master query failed');
        }

        $out = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $physicalRaw) {
            $physical = (string) $physicalRaw;
            if (!$this->matchesPrefix($physical, $prefix)) {
                continue;
            }

            $infoStmt = $pdo->query('PRAGMA table_info(' . $physical . ')');
            if ($infoStmt === false) {
                continue;
            }

            $columns = [];
            foreach ($infoStmt->fetchAll() as $col) {
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

        $global = ['users', 'sessions', 'roles', 'user_roles', 'login_attempts', 'migrations', 'audit_log'];
        if (in_array($table, $global, true)) {
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
