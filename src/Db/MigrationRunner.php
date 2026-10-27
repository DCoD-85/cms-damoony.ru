<?php

declare(strict_types=1);

namespace Damoony\Db;

use RuntimeException;

final class MigrationRunner
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $migrationsDir,
    ) {
    }

    /**
     * @return list<string> применённые версии
     */
    public function run(): array
    {
        $pdo = $this->connection->pdo();

        $pdo->exec('CREATE TABLE IF NOT EXISTS migrations (version VARCHAR(64) PRIMARY KEY, applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)');

        $applied = [];
        $stmt = $pdo->query('SELECT version FROM migrations');
        if ($stmt !== false) {
            foreach ($stmt->fetchAll() as $row) {
                if (is_array($row) && isset($row['version'])) {
                    $applied[(string) $row['version']] = true;
                }
            }
        }

        $files = glob(rtrim($this->migrationsDir, '/') . '/*.sql') ?: [];
        sort($files);

        $executed = [];
        foreach ($files as $file) {
            $version = basename($file, '.sql');
            if (isset($applied[$version])) {
                continue;
            }

            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new RuntimeException('Cannot read migration: ' . $file);
            }

            $pdo->beginTransaction();
            try {
                $pdo->exec($sql);
                $stmt = $pdo->prepare('INSERT INTO migrations (version) VALUES (:v)');
                if ($stmt === false) {
                    throw new RuntimeException('Cannot prepare insert');
                }
                $stmt->execute(['v' => $version]);
                if ($pdo->inTransaction()) {
                    $pdo->commit();
                }
                $executed[] = $version;
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw new RuntimeException('Migration failed: ' . $version . ' — ' . $e->getMessage(), 0, $e);
            }
        }

        return $executed;
    }
}
