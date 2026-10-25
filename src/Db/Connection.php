<?php

declare(strict_types=1);

namespace Damoony\Db;

use PDO;
use RuntimeException;

final class Connection
{
    private ?PDO $pdo = null;

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    public function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $driver = (string) ($this->config['driver'] ?? 'sqlite');

        if ($driver === 'sqlite') {
            $path = (string) ($this->config['path'] ?? ':memory:');
            $dsn = 'sqlite:' . $path;
            $this->pdo = new PDO($dsn);
        } elseif ($driver === 'mysql') {
            $host = (string) ($this->config['host'] ?? '127.0.0.1');
            $dbname = (string) ($this->config['dbname'] ?? '');
            $charset = (string) ($this->config['charset'] ?? 'utf8mb4');
            if ($dbname === '') {
                throw new RuntimeException('MySQL dbname required');
            }
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $host, $dbname, $charset);
            $this->pdo = new PDO($dsn, (string) ($this->config['user'] ?? ''), (string) ($this->config['password'] ?? ''));
        } else {
            throw new RuntimeException('Unsupported driver: ' . $driver);
        }

        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $this->pdo;
    }
}